# Request ID — trace correlation

## Why you would want this — a concrete scenario

A user writes to your support : *"My CSV export crashed this morning around 9:42."*

You open your logs. Between 9:40 and 9:45, **47 `/exports` requests** went through. Six returned errors. Some belong to the user (their company has a shared IP behind a corporate proxy, so filtering by IP returns three different humans). You can guess based on the timestamp and the user agent, but you're never 100% sure which error was hers. You burn 15 minutes cross-referencing and still email her a "could you give us a bit more detail?".

**With request-id**, your server stamps every response with `X-Request-Id: r4qH3yKp9N0c-XZ7M8sWQg` and your error page tells the user :

```
Sorry, something went wrong.
Reference for support: r4qH3yKp9N0c-XZ7M8sWQg
```

She gives you that ID. You paste it into your log aggregator's search bar. **One result** : the exact request, the stack trace, the failing query, the input payload. 5 seconds.

The same ID is on every log line your server wrote while processing that request — controller, services, database, third-party HTTP calls — so you can read the full causal chain top-to-bottom.

When you DON'T need this : nothing. Even a monolith benefits — the cost is one helper call per request and a few extra bytes in the response. For distributed architectures with multiple services calling each other, see [Distributed tracing](tracing.md) instead (W3C Trace Context, with the same idea propagated across service boundaries).

---

`oihana/php-middleware` ships two procedural helpers to set up clean **request ID** propagation across your API:

- [`requestIdFromRequest()`](#requestidfromrequest) — reads (or generates) the ID carried by an incoming `X-Request-Id`.
- [`withRequestIdHeader()`](#withrequestidheader) — stamps the ID on the response.

Plus an enum [`RequestIdField`](#requestidfield) for the conventional names.

The goal: assign a short unique identifier (~128 bits) to every request, propagate it through all server-side logs, and return it to the client via a `X-Request-Id` header. When a user reports a bug, they can pass that ID to support, who can pull the full server-side trace instantly.

## `requestIdFromRequest()`

```php
namespace oihana\middleware\helpers\requestId ;

function requestIdFromRequest( ServerRequestInterface $request , string $headerName = 'X-Request-Id' ) : string ;
```

Two-step strategy:

1. **If the request already carries an `X-Request-Id`** (typically forwarded by a load balancer, API gateway, or calling service), the helper reuses it — **provided** it passes a conservative shape check: 1 to 128 characters, restricted to the URL-safe alphabet `[A-Za-z0-9_-]`.
2. **Otherwise** (header missing, empty, or outside the alphabet), the helper generates a fresh ID via `oihana\core\encoding\randomBase64Url()` (128 bits of CSPRNG, 22 base64url characters).

### Why validate the incoming header

A client can send anything in an `X-Request-Id` (HTTP headers are client-controlled). Without validation, an attacker could:

- **Pollute the logs** with an excessively long ID or characters that break a structured log format.
- **Inject headers** via CRLF if the PSR-7 implementation in use is lax (Slim PSR-7 catches this attack upstream, but other implementations are less strict — defense-in-depth is warranted).
- **Confuse correlation** by reusing an existing legitimate ID (to muddy the trail).

The `[A-Za-z0-9_-]{1,128}` validation covers 100% of legitimate IDs (UUIDs, base64url, hex, simple slugs) while rejecting forged payloads.

### Usage

```php
use function oihana\middleware\helpers\requestId\requestIdFromRequest ;
use oihana\middleware\enums\RequestIdField ;

// Default header name (X-Request-Id)
$id = requestIdFromRequest( $request ) ;

// Custom header name (e.g. to align with another service)
$id = requestIdFromRequest( $request , 'X-Trace-Id' ) ;

// With the enum constant
$id = requestIdFromRequest( $request , RequestIdField::HEADER_NAME ) ;
```

## `withRequestIdHeader()`

```php
namespace oihana\middleware\helpers\requestId ;

function withRequestIdHeader( ResponseInterface $response , string $id , string $headerName = 'X-Request-Id' ) : ResponseInterface ;
```

Stamps the request ID on the response so downstream consumers (browser devtools, log aggregators, support tickets, tracing pipelines) can correlate the response with the server-side trace.

Returns a **new** `ResponseInterface` (PSR-7 immutable). Any pre-existing value for the same header name is replaced.

### Usage

```php
use function oihana\middleware\helpers\requestId\withRequestIdHeader ;

$response = withRequestIdHeader( $response , $id ) ;
// => Response with `X-Request-Id: <id>`
```

## `RequestIdField`

```php
namespace oihana\middleware\enums ;

class RequestIdField
{
    public const string HEADER_NAME    = 'X-Request-Id' ;
    public const string ATTRIBUTE_NAME = 'requestId' ;
}
```

- **`HEADER_NAME`** — conventional header name to use on both sides (incoming request + outgoing response).
- **`ATTRIBUTE_NAME`** — conventional PSR-7 attribute name to propagate the ID through the middleware chain via `$request->withAttribute(...)`.

Conventions, not requirements — feel free to use your own names if you prefer.

## Full recipe: Slim middleware

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use function oihana\middleware\helpers\requestId\requestIdFromRequest ;
use function oihana\middleware\helpers\requestId\withRequestIdHeader ;

use oihana\middleware\enums\RequestIdField ;

class RequestIdMiddleware implements MiddlewareInterface
{
    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        // 1. Read or generate
        $id = requestIdFromRequest( $request , RequestIdField::HEADER_NAME ) ;

        // 2. Propagate through the chain via a PSR-7 attribute
        $request = $request->withAttribute( RequestIdField::ATTRIBUTE_NAME , $id ) ;

        // 3. Handle
        $response = $handler->handle( $request ) ;

        // 4. Stamp the response
        return withRequestIdHeader( $response , $id , RequestIdField::HEADER_NAME ) ;
    }
}
```

Place this **at the top of the middleware stack** so every other middleware (auth, audit, logging, error handler, etc.) can retrieve the ID via `$request->getAttribute(RequestIdField::ATTRIBUTE_NAME)`.

### Logger side

```php
$id = $request->getAttribute( RequestIdField::ATTRIBUTE_NAME ) ;
$logger->info( 'Customer fetched' , [ 'requestId' => $id , 'customerId' => $customerId ] ) ;
```

Every log line emitted for a given request shares this `requestId`, which makes correlation trivial in any aggregator (ELK, Loki, Datadog, Splunk…).

## Going further: distributed tracing

The request ID is the base building block. For a distributed system, see **W3C tracing** (`traceparent` / `tracestate`) which pushes correlation further across services. Helper planned for a future release of `oihana/php-middleware`.

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [Security headers](security-headers.md) — to combine with HSTS / CSP.
- [CSRF](csrf.md) — for cross-site protection.
