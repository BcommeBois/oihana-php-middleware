# Idempotency keys (`Idempotency-Key`)

A request is **idempotent** when receiving it twice has the same effect as receiving it once. `GET` is idempotent by nature ; a `POST` that creates an order is not. The `Idempotency-Key` header makes it so : the client draws one unique identifier per action (a UUID), sends it with **every attempt**, and the server answers a repeated attempt with **what it answered the first time**, without running the action again.

## Why you'd want this — a concrete scenario

**The lost answer.** A salesperson creates an order from a tablet, in a poorly covered warehouse. Your API receives the request, stores the order, prepares the `201`… and the connection drops before the answer arrives. For the tablet nothing happened : it retries thirty seconds later. Without idempotency, your API stores **a second order**, identical. The worse the network, the more duplicates — and nobody is told.

With an idempotency key, the second attempt carries the same key as the first one :

```
HTTP/1.1 201 Created
Content-Type: application/json
Location: /orders/42
Idempotent-Replayed: true

{"id":42}
```

The order exists once ; the tablet gets its key, exactly as if the first answer had arrived. `Idempotent-Replayed: true` tells it this is a replay.

---

`oihana/php-middleware` ships the pieces, with no framework lock-in :

```php
namespace oihana\middleware\helpers\idempotency ;

function beginIdempotency( ServerRequestInterface $request , IdempotencyStore $store , array $options = [] ) : IdempotencyDecision ;

function completeIdempotency( IdempotencyDecision $decision , ResponseInterface $response , IdempotencyStore $store , array $options = [] ) : ResponseInterface ;

function respondIdempotentReplay( ResponseInterface $response , IdempotencyRecord $record ) : ResponseInterface ;

function idempotencyFingerprint( ServerRequestInterface $request ) : string ;

function isIdempotencyRecordable( int $status ) : bool ;
```

`beginIdempotency()` makes the **decision** ; `completeIdempotency()` keeps the response once the action has run ; you build the error responses — their body and format stay those of your API. Storage is pluggable through the `IdempotencyStore` interface ; an in-memory implementation ships for tests.

## How a request flows

`beginIdempotency()` returns an [`IdempotencyDecision`](../../src/oihana/middleware/idempotency/IdempotencyDecision.php) whose `$outcome` is one of the [`IdempotencyOutcome`](../../src/oihana/middleware/enums/IdempotencyOutcome.php) constants :

| Outcome | When | Typical answer |
| :--- | :--- | :--- |
| `SKIP` | the method is not guarded (`GET`, `HEAD`, `OPTIONS`…), or no header while the key is optional | the request runs ; nothing is kept |
| `MISSING` | no header while the key is required (`REQUIRED`) | `400` |
| `INVALID` | an empty header, or one longer than `MAX_KEY_LENGTH` — refused rather than ignored, otherwise the request would run unguarded | `400` |
| `PROCEED` | the key was free : it is now **claimed** by this request | run the action, then `completeIdempotency()` |
| `MISMATCH` | the key is known, but for **another body** — the client reuses a key for another action | `422` |
| `REPLAY` | the key is known, same body, response kept | `respondIdempotentReplay()` — the action does not run |
| `IN_PROGRESS` | the key is known, same body, but the first request **is still running** (two tabs, a retry fired too early) | `429` + `Retry-After: $decision->retryAfter` |

Then, on `PROCEED` :

- `completeIdempotency()` **keeps** the response when [`isIdempotencyRecordable()`](../../src/oihana/middleware/helpers/idempotency/isIdempotencyRecordable.php) accepts it — `2xx`, `3xx` and the `4xx` that depend on the request only (`400`, `404`, `409`, `412`, `422`…) — and **releases** the key otherwise : `5xx`, `401`, `403`, `408`, `425`, `429` must be able to succeed on a replay ;
- when the action throws, **release the key yourself** (`$store->release( $decision->key )`) before rethrowing ; otherwise it stays claimed until its lease lapses.

### Why `429` rather than `409` for a key in progress

The IETF draft (`draft-ietf-httpapi-idempotency-key-header`) suggests `409 Conflict` for a key in progress. Many clients, though, file every `409` as « a conflict a person must settle » ; `429` + `Retry-After` is a « try again in a moment » every HTTP client already understands. The helper does not build the response : pick the status that suits your clients.

## The store key and the fingerprint

The store key is `"{KEY_PREFIX}:{sha256(scope, METHOD, path, key)}"` : two accounts, two methods or two paths never share a key.

🚨 **Pass the authenticated account as `SCOPE`.** Without it, every caller shares the same keys : anyone reading a key in a log could replay another account's answer.

The **fingerprint** ([`idempotencyFingerprint()`](../../src/oihana/middleware/helpers/idempotency/idempotencyFingerprint.php)) is a SHA-256 of :

- the **parsed body** (`getParsedBody()`), the keys of every object sorted recursively — the same object gives the same fingerprint whatever the order of its members ; failing that, the **raw body** ;
- each **uploaded file** : its field path, name, type and the SHA-256 of its content, read by chunks — no byte is kept.

🚨 A `multipart/form-data` body is never taken raw : the client draws a new boundary on every attempt, so the bytes of two identical replays differ.

## Supported options

The `$options` array is keyed by the [`IdempotencyOption`](../../src/oihana/middleware/enums/IdempotencyOption.php) constants. Options are read **on every call** : you may choose them per route, or per client application.

| Option | Type | Default | Read by | Effect |
| :--- | :--- | :--- | :--- | :--- |
| `SCOPE` | `string\|callable\|null` | `null` | `begin` | The account the key belongs to. A `callable` `fn(ServerRequestInterface): ?string` is called on every request. |
| `REQUIRED` | `bool` | `false` | `begin` | `true` : a guarded request without the header is `MISSING` rather than `SKIP`. |
| `METHODS` | `list<string>` | `POST`, `PUT`, `PATCH`, `DELETE` | `begin` | The guarded methods. |
| `MAX_KEY_LENGTH` | `int` | `255` | `begin` | The longest key accepted, in bytes. |
| `LEASE` | `int` (seconds) | `120` | `begin` | How long a running request holds its key. Past it, the claim of a dead process is taken over by the next replay. Keep it above your maximum execution time. |
| `RETRY_AFTER` | `int` (seconds) | `2` | `begin` | The delay suggested on `IN_PROGRESS`. |
| `KEY_PREFIX` | `string` | `'idempotency'` | `begin` | Prefix of every store key. |
| `TTL` | `int` (seconds) | `604800` (7 days) | `complete` | How long a response is kept. It must cover the longest delay between a first attempt and its last replay — a device switched off for a weekend included. |
| `HEADERS` | `list<string>` | `Content-Type`, `Content-Location`, `Location`, `ETag`, `Last-Modified` | `complete` | The headers kept with the response. |

A mistyped or non-positive value falls back on the default.

## Full recipe : a PSR-15 middleware

```php
use Throwable ;

use Psr\Http\Message\ResponseFactoryInterface ;
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use oihana\middleware\enums\IdempotencyOption ;
use oihana\middleware\enums\IdempotencyOutcome ;
use oihana\middleware\idempotency\IdempotencyStore ;

use function oihana\middleware\helpers\idempotency\beginIdempotency ;
use function oihana\middleware\helpers\idempotency\completeIdempotency ;
use function oihana\middleware\helpers\idempotency\respondIdempotentReplay ;

class IdempotencyMiddleware implements MiddlewareInterface
{
    public function __construct
    (
        private readonly IdempotencyStore         $store           ,
        private readonly ResponseFactoryInterface $responseFactory ,
    ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        $options = [ IdempotencyOption::SCOPE => $request->getAttribute( 'userId' ) ] ;

        $decision = beginIdempotency( $request , $this->store , $options ) ;

        switch ( $decision->outcome )
        {
            case IdempotencyOutcome::SKIP :
                return $handler->handle( $request ) ;

            case IdempotencyOutcome::REPLAY :
                return respondIdempotentReplay( $this->responseFactory->createResponse() , $decision->record ) ;

            case IdempotencyOutcome::IN_PROGRESS :
                return $this->responseFactory->createResponse( 429 )
                            ->withHeader( 'Retry-After' , (string) $decision->retryAfter ) ;

            case IdempotencyOutcome::MISMATCH :
                return $this->responseFactory->createResponse( 422 ) ;

            case IdempotencyOutcome::MISSING :
            case IdempotencyOutcome::INVALID :
                return $this->responseFactory->createResponse( 400 ) ;
        }

        try
        {
            $response = $handler->handle( $request ) ;
        }
        catch ( Throwable $exception )
        {
            $this->store->release( $decision->key ) ;
            throw $exception ;
        }

        return completeIdempotency( $decision , $response , $this->store , $options ) ;
    }
}
```

**Key wiring points :**

- **Place it after authentication and authorization** — `SCOPE` reads the authenticated account, a replay goes through the permissions again (a revoked right is not bypassed by a kept response), and a `401` / `403` never touches the store.
- **Place it after body parsing** — the fingerprint reads `getParsedBody()`.
- **CORS** — a browser client must be allowed to send `Idempotency-Key` (in `Access-Control-Allow-Headers`) and to read `Idempotent-Replayed` (in `Access-Control-Expose-Headers`).

## Picking a store

| Store | Where it lives | Use when… |
| :--- | :--- | :--- |
| [`InMemoryIdempotencyStore`](../../src/oihana/middleware/idempotency/InMemoryIdempotencyStore.php) | This package | Unit tests, CLI scripts, single-process tools. **Not for multi-worker HTTP traffic** — a replay landing on another worker would run the action a second time. |
| Custom (database, Redis, …) | Your project | Implement [`IdempotencyStore`](../../src/oihana/middleware/idempotency/IdempotencyStore.php). |

What a production store must guarantee :

- 🚨 **An atomic `begin()`** — two requests carrying the same key at the same instant must never both get it. The primitive exists everywhere : an insert refused on an existing key (a database unique key, `Memcached::add()`, Redis `SET NX`).
- **Keep for the whole `TTL`** — a key erased too early (a cache flushed or evicted) lets the next replay run the action again.
- **Serialise with `IdempotencyRecord::toArray()` / `fromArray()`** — the keys are the [`IdempotencyField`](../../src/oihana/middleware/enums/IdempotencyField.php) constants.

## Out of scope

These helpers keep **one response per key**. They do NOT :

- **Build the error responses** — the body of your `400`, `422` and `429` follows your API's format.
- **Resolve which routes require a key** — compute `REQUIRED` yourself, per route or per client.
- **Keep a response whose body cannot be read again** — a non-seekable body cannot be read without being consumed : `completeIdempotency()` then releases the key instead of keeping it.
- **Detect a duplicate created by a person** — two separate entries carry two different keys : for the server, they are two actions.

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [CORS](cors.md) — expose `Idempotent-Replayed` to a browser client.
- [Rate limiting](rate-limiting.md) — the same store + decision + helper pattern.
- [IETF draft `Idempotency-Key`](https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/) — the header specification.
