# Rate limiting

`oihana/php-middleware` ships a procedural helper to enforce a fixed-window rate-limit policy on PSR-7 requests:

```php
namespace oihana\middleware\helpers\rateLimit ;

function enforceRateLimit
(
    ServerRequestInterface $request ,
    RateLimitStore         $store   ,
    array                  $config  = []
) : RateLimitDecision ;

function withRateLimitHeaders
(
    ResponseInterface $response ,
    RateLimitDecision $decision ,
    bool              $rfc9421 = false ,
) : ResponseInterface ;
```

The helper takes the decision (allow or block) and lets you build the actual `429` response — there is no opinionated body, no framework binding, no JWT / cookie / DB hooks. The store back-end is pluggable via the `RateLimitStore` interface; an in-memory implementation is shipped, a Memcached-backed one lives in [`oihana/php-memcached`](https://github.com/BcommeBois/oihana-php-memcached).

## Algorithm

**Fixed window**: the wall clock is sliced into windows of `WINDOW` seconds anchored on `floor(now / window) * window`. Each request increments an atomic counter for the `(scope, identity, windowStart)` triple. When the counter overflows `LIMIT`, the decision flips to `!allowed` until the window resets.

Why fixed window:

- maps trivially onto a single atomic increment — every production back-end (Memcached, Redis, APCu) supports it natively;
- 1 store key per active window, lowest memory footprint;
- the `RateLimit-*` / `X-RateLimit-*` headers are designed around it (one limit, one reset).

Token bucket and sliding-window-counter are not provided. They are not necessary for typical API rate-limiting and would force a richer store contract (CAS / locks). They can be added later as separate helpers without breaking this one.

## Supported options

The `$config` array is keyed by [`RateLimitOption`](../../src/oihana/middleware/enums/RateLimitOption.php) constants.

| Option | Type | Default | Effect |
| :--- | :--- | :--- | :--- |
| `LIMIT` | `int` | `100` | Maximum requests allowed per window. Non-positive values fall back to the default. |
| `WINDOW` | `int` (seconds) | `60` | Width of the rate-limit window. Non-positive values fall back to the default. |
| `KEY` | `string\|callable\|null` | `null` | Identifier the counter is keyed on (see below). |
| `KEY_PREFIX` | `string` | `'ratelimit'` | Prefix prepended to every store key. Useful to isolate independent limiters that share a backend. |
| `SCOPE` | `string\|null` | `null` | Optional segment inserted between the prefix and the key (e.g. `'auth'`, `'write'`, `'read'`). |
| `NOW` | `int\|null` | `time()` | Clock injection for deterministic tests. |

### `KEY` resolution

| Form | Effect |
| :--- | :--- |
| `string` (non-empty) | Used verbatim. |
| `callable` `fn(ServerRequestInterface): string` | Invoked on every call — lets you hash an email, return a service `_key`, derive from a JWT claim, etc. An empty return value falls back to the `'unknown'` sentinel. |
| `null` / omitted | Falls back to the client IP resolved via [`oihana\http\helpers\ips\getClientIp()`](../../../oihana-php-http/src/oihana/http/helpers/ips/getClientIp.php). When no usable address is found, the `'unknown'` sentinel is used so the helper never silently degrades into "no key, no quota". |

The resulting store key is `"{KEY_PREFIX}:{SCOPE?}:{identity}:{windowStart}"`.

## `RateLimitDecision`

Returned by `enforceRateLimit()`. Readonly value object — see [`RateLimitDecision`](../../src/oihana/middleware/rateLimit/RateLimitDecision.php).

| Property | Type | Meaning |
| :--- | :--- | :--- |
| `$allowed` | `bool` | `true` when the request fits the quota, `false` when the counter overflew. |
| `$limit` | `int` | Quota in effect for the window (verbatim from `LIMIT`). |
| `$remaining` | `int` | Requests still allowed before reset. Clamped to `0` once the quota is exhausted. |
| `$reset` | `int` | Absolute Unix timestamp when the window closes. |
| `$retryAfter` | `int` | Seconds until `$reset`, or `0` when `$allowed`. Suitable as `Retry-After` value on a `429`. |

## `withRateLimitHeaders()` — header families

Two header families are supported. Toggle via the `$rfc9421` flag:

| Flag | Family | Headers emitted |
| :--- | :--- | :--- |
| `false` (default) | Legacy de-facto | `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` |
| `true` | IETF draft (RFC 9421) | `RateLimit-Limit`, `RateLimit-Remaining`, `RateLimit-Reset` |

`Retry-After` is emitted on every `!$decision->allowed` response in both families.

## Usage

```php
use function oihana\middleware\helpers\rateLimit\enforceRateLimit ;
use function oihana\middleware\helpers\rateLimit\withRateLimitHeaders ;

use oihana\middleware\enums\RateLimitOption ;
use oihana\middleware\rateLimit\InMemoryRateLimitStore ;

$store = new InMemoryRateLimitStore() ;

$decision = enforceRateLimit( $request , $store ,
[
    RateLimitOption::LIMIT  => 10 ,
    RateLimitOption::WINDOW => 60 ,
    RateLimitOption::SCOPE  => 'auth' ,
]) ;

if ( !$decision->allowed )
{
    $response = $responseFactory->createResponse( 429 ) ;
    $response->getBody()->write( '{"error":"too many requests"}' ) ;
    return withRateLimitHeaders( $response , $decision )
        ->withHeader( 'Content-Type' , 'application/json' ) ;
}

$response = $handler->handle( $request ) ;

return withRateLimitHeaders( $response , $decision ) ;
```

## Full recipe: Slim middleware with explicit key resolution

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Message\ResponseFactoryInterface ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use oihana\middleware\enums\RateLimitOption ;
use oihana\middleware\rateLimit\RateLimitStore ;

use function oihana\middleware\helpers\rateLimit\enforceRateLimit ;
use function oihana\middleware\helpers\rateLimit\withRateLimitHeaders ;

class AuthRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct
    (
        private readonly RateLimitStore           $store           ,
        private readonly ResponseFactoryInterface $responseFactory ,
        private readonly int                      $limit  = 10 ,
        private readonly int                      $window = 60 ,
    ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        $decision = enforceRateLimit( $request , $this->store ,
        [
            RateLimitOption::LIMIT  => $this->limit  ,
            RateLimitOption::WINDOW => $this->window ,
            RateLimitOption::SCOPE  => 'auth' ,
        ]) ;

        if ( !$decision->allowed )
        {
            $response = $this->responseFactory->createResponse( 429 ) ;
            $response->getBody()->write( '{"error":"too many requests"}' ) ;
            return withRateLimitHeaders( $response , $decision )
                ->withHeader( 'Content-Type' , 'application/json' ) ;
        }

        return withRateLimitHeaders( $handler->handle( $request ) , $decision ) ;
    }
}
```

**Wiring key points:**

- **Place before the business logic and after request-id / tracing** — so blocked responses still carry their correlation ID.
- **Externalise the store** — share a single instance across the app so per-key counters accumulate correctly.
- **One middleware per scope** — auth, write, read, etc. Each instance carries its own `LIMIT` / `WINDOW` / `SCOPE` triple. The shared store keeps counters segregated by `SCOPE`.

## Choosing a store

| Store | Where it lives | Use when… |
| :--- | :--- | :--- |
| [`InMemoryRateLimitStore`](../../src/oihana/middleware/rateLimit/InMemoryRateLimitStore.php) | This package | Unit tests, CLI scripts, single-process tools, demos. **Not for multi-worker HTTP traffic** — each worker would keep its own counters. |
| `MemcachedRateLimitStore` | [`oihana/php-memcached`](https://github.com/BcommeBois/oihana-php-memcached) (separate package) | Production HTTP traffic. Shared across all workers / nodes via Memcached. |
| Custom (Redis, APCu, …) | Your project | Any backend that exposes an atomic increment-with-TTL primitive. Implement the [`RateLimitStore`](../../src/oihana/middleware/rateLimit/RateLimitStore.php) interface. |

## Out of scope

This helper is limited to **fixed-window quota enforcement on a single counter per request**. It does NOT:

- **Resolve rules from the request** — choosing `auth` vs `write` vs `read` based on path/method is your middleware's responsibility.
- **Combine multiple counters in one call** — if you need both per-IP and per-email quotas on the same endpoint, call `enforceRateLimit()` twice with two scopes / two keys and short-circuit on either failure.
- **Decode JWTs or query a database** — the `KEY` callable hook lets you do that yourself.
- **Build the 429 body** — that's an application concern (content negotiation, problem-details JSON, etc.).
- **Implement token bucket / sliding window** — out of scope for v0.3, see "Algorithm" above.

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [Request ID](request-id.md) — propagate a trace ID even on 429 responses for support traceability.
- [Maintenance mode](maintenance.md) — sibling helper for graceful 503 responses.
- [IETF draft RFC 9421](https://datatracker.ietf.org/doc/html/draft-ietf-httpapi-ratelimit-headers) — "RateLimit Header Fields for HTTP".
- [`oihana/php-memcached`](https://github.com/BcommeBois/oihana-php-memcached) — production-grade `RateLimitStore` backed by Memcached.
