# CORS

`oihana/php-middleware` ships a single procedural helper to handle CORS end-to-end:

```php
namespace oihana\middleware\helpers\cors ;

function applyCorsHeaders( ServerRequestInterface $request , ResponseInterface $response , array $options = [] ) : ResponseInterface ;
```

PSR-7 immutable: returns a **new** `ResponseInterface` — the supplied instance is never mutated. **The status code is not touched** — it is up to the calling middleware to decide whether to short-circuit a preflight with a 204 or proceed to the next handler.

## Supported options

The `$options` array is keyed by [`CorsOption`](../../src/oihana/middleware/enums/CorsOption.php) constants.

| Option | Type | Effect |
| :--- | :--- | :--- |
| `ALLOWED_ORIGINS` | `list<string>\|'*'\|null` | Explicit list of allowed origins, or the wildcard `'*'`, or `null` to leave everything alone. |
| `ALLOWED_METHODS` | `list<string>` | Methods emitted in `Access-Control-Allow-Methods` (preflight only). |
| `ALLOWED_HEADERS` | `list<string>` | Headers emitted in `Access-Control-Allow-Headers` (preflight). When omitted, the helper echoes the content of `Access-Control-Request-Headers`. |
| `EXPOSED_HEADERS` | `list<string>` | Headers exposed to JS via `Access-Control-Expose-Headers`. Emitted on every allowed-origin CORS request. |
| `ALLOW_CREDENTIALS` | `bool` (default `false`) | Emits `Access-Control-Allow-Credentials: true` when `true`. |
| `MAX_AGE` | `int` | Preflight cache TTL in seconds. Preflight only. |

## Algorithm

1. **CORS request detection.** If the request has no `Origin` header, it is not a CORS request — the response is returned untouched.

2. **`Access-Control-Allow-Origin` resolution**:
   - `ALLOWED_ORIGINS: '*'` ⇒ `Allow-Origin: *`, no `Vary`. **Throws `InvalidArgumentException` when combined with `ALLOW_CREDENTIALS: true`** — browsers reject this combo.
   - `ALLOWED_ORIGINS: list<string>` and the request `Origin` is in the list ⇒ `Allow-Origin: <origin>` + `Vary: Origin` (added idempotently: no duplicate when already present).
   - Otherwise (no allowlist, origin not in list) ⇒ response returned untouched. The caller decides 403 / 200 separately.

3. **`Access-Control-Allow-Credentials: true`** emitted when `ALLOW_CREDENTIALS: true` and the origin is allowed.

4. **`Access-Control-Expose-Headers`** emitted when `EXPOSED_HEADERS` is non-empty. Joined by `', '`.

5. **Preflight detection**: `OPTIONS` method AND non-empty `Access-Control-Request-Method` header.
   - `Access-Control-Allow-Methods` emitted when `ALLOWED_METHODS` is non-empty.
   - `Access-Control-Allow-Headers` emitted: if `ALLOWED_HEADERS` is non-empty, joined by `', '`. Otherwise, echoes the content of `Access-Control-Request-Headers` (if present).
   - `Access-Control-Max-Age` emitted when `MAX_AGE` is an `int > 0`.

## Examples

### Explicit allowlist with credentials

```php
use function oihana\middleware\helpers\cors\applyCorsHeaders ;
use oihana\middleware\enums\CorsOption ;

$response = applyCorsHeaders( $request , $response ,
[
    CorsOption::ALLOWED_ORIGINS   => [ 'https://app.example.com' , 'https://admin.example.com' ] ,
    CorsOption::ALLOWED_METHODS   => [ 'GET' , 'POST' , 'DELETE' ] ,
    CorsOption::ALLOWED_HEADERS   => [ 'Authorization' , 'Content-Type' ] ,
    CorsOption::EXPOSED_HEADERS   => [ 'X-Request-Id' ] ,
    CorsOption::ALLOW_CREDENTIALS => true ,
    CorsOption::MAX_AGE           => 3600 ,
]) ;
```

On a preflight `OPTIONS` coming from `https://app.example.com`:

```
Access-Control-Allow-Origin: https://app.example.com
Vary: Origin
Access-Control-Allow-Credentials: true
Access-Control-Expose-Headers: X-Request-Id
Access-Control-Allow-Methods: GET, POST, DELETE
Access-Control-Allow-Headers: Authorization, Content-Type
Access-Control-Max-Age: 3600
```

### Public read-only API without credentials

```php
$response = applyCorsHeaders( $request , $response ,
[
    CorsOption::ALLOWED_ORIGINS => '*' ,
    CorsOption::ALLOWED_METHODS => [ 'GET' ] ,
]) ;
```

Emits `Access-Control-Allow-Origin: *` without `Vary`. Recommended setup for a public read-only API.

### Wiring as a Slim middleware

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;
use Slim\Psr7\Response ;

use function oihana\middleware\helpers\cors\applyCorsHeaders ;
use oihana\middleware\enums\CorsOption ;
use oihana\enums\http\HttpMethod ;
use oihana\enums\http\HttpStatusCode ;

class CorsMiddleware implements MiddlewareInterface
{
    public function __construct( private readonly array $options ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        // Short-circuit preflight with 204
        if ( $request->getMethod() === HttpMethod::OPTIONS )
        {
            $response = new Response( HttpStatusCode::NO_CONTENT ) ;
        }
        else
        {
            $response = $handler->handle( $request ) ;
        }

        return applyCorsHeaders( $request , $response , $this->options ) ;
    }
}
```

## CORS predicates

Two small predicates help middlewares decide whether the CORS branch is even relevant for a given request, without spelling out the underlying header names.

```php
namespace oihana\middleware\helpers\cors ;

function isCorsRequest  ( ServerRequestInterface $request ) : bool ;
function isCorsPreflight( ServerRequestInterface $request ) : bool ;
```

| Helper | Returns `true` when… |
| :--- | :--- |
| `isCorsRequest()` | The request carries an `Origin` header. |
| `isCorsPreflight()` | The request method is `OPTIONS` AND the request carries an `Access-Control-Request-Method` header. |

Note : a bare `OPTIONS` (no `Access-Control-Request-Method`) is **not** a preflight — it might be a routing query or a server-info probe. `isCorsPreflight()` returns `false` in that case so the middleware can pass it through to the regular handler.

```php
use function oihana\middleware\helpers\cors\applyCorsHeaders ;
use function oihana\middleware\helpers\cors\isCorsPreflight ;
use function oihana\middleware\helpers\cors\isCorsRequest ;

if ( isCorsPreflight( $request ) )
{
    return applyCorsHeaders( $request , $responseFactory->createResponse( 204 ) , $options ) ;
}

$response = $handler->handle( $request ) ;

if ( isCorsRequest( $request ) )
{
    $response = applyCorsHeaders( $request , $response , $options ) ;
}

return $response ;
```

## Common pitfalls the helper avoids

- **`*` + credentials**: forbidden by the spec, browsers reject. The helper throws an exception rather than push an invalid response silently.
- **Naïve wildcard**: with an explicit allowlist, the helper echoes the request `Origin`, not a fragile wildcard.
- **Missing `Vary: Origin`**: without that header, CDNs / shared caches may serve a CORS response to the wrong origin. The helper adds it automatically when the allowlist is dynamic.
- **Duplicate `Vary: Origin`**: if the response already contains `Vary: Origin`, the helper does not add it a second time.

## See also

- [Getting started](getting-started.md) — wiring in a PSR-15 middleware.
- [Security headers](security-headers.md) — the other helper family in this package.
- [Fetch spec — CORS protocol](https://fetch.spec.whatwg.org/#http-cors-protocol) — official reference (CORS is defined by the Fetch standard, not by a dedicated RFC).
