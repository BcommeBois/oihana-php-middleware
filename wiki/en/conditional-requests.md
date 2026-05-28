# Conditional requests (304 Not Modified)

## Why you would want this — a concrete scenario

Your blog's home page sits at `/`. 1000 visitors hit it every hour. Without conditional requests :

- Every request runs your CMS query, your template engine, your asset pipeline.
- 1000 × 80 ms of server time = 80 seconds of CPU per hour, for content that didn't change.
- The full 250 KB HTML body is sent over the wire 1000 times.

A user returns to the page 3 minutes after their first visit. The page hasn't changed. The user's browser still has the previous response in its disk cache, but with no freshness metadata it doesn't know if the cached copy is still valid. It re-requests the full page. 250 KB of bandwidth burnt. The user waits another 400 ms.

**With ETag + conditional GET**, the first response carries :

```
HTTP/1.1 200 OK
ETag: "v42-2026052810"
Content-Length: 254312

<full HTML>
```

The browser stores both the body AND the `ETag`. When the user comes back, the browser asks again — but this time with a precondition :

```
GET / HTTP/1.1
If-None-Match: "v42-2026052810"
```

Your server checks the current ETag of the resource. Same value → the cached body is still fresh. Respond :

```
HTTP/1.1 304 Not Modified
ETag: "v42-2026052810"
```

**No body. 50 bytes on the wire instead of 254 KB.** The browser pulls its cached copy off disk, renders instantly.

Multiply this across all your visitors, all your endpoints. The CPU and bandwidth savings on read-heavy traffic are substantial — that's why CDNs build their entire business on this mechanism.

The catch : you still have to compute the ETag (or the `Last-Modified` date) to compare. That's cheaper than rebuilding the body — usually a hash of the last database update, the cache key, or a version counter. But it's not free.

When this is **not** useful : endpoints where computing the ETag costs nearly as much as serving the body (e.g. ETag = hash of the body itself for a tiny JSON object). The whole point is to short-circuit BEFORE the expensive work.

---

`oihana/php-middleware` ships two coupled procedural helpers for the conditional GET pattern :

```php
namespace oihana\middleware\helpers\cache ;

function isNotModified(
    ServerRequestInterface $request ,
    string                 $etag ,
    ?DateTimeInterface     $lastModified = null ,
) : bool ;

function respondNotModified( ResponseInterface $response , string $etag ) : ResponseInterface ;
```

`isNotModified()` evaluates the request's preconditions ; `respondNotModified()` builds the canonical 304 response.

## Precondition evaluation

Per [RFC 9110 §13.1.3](https://www.rfc-editor.org/rfc/rfc9110#section-13.1.3) :

1. If the request carries an `If-None-Match` header, **it takes precedence** — `If-Modified-Since` is ignored.
2. Otherwise, if the request carries an `If-Modified-Since` AND a `$lastModified` is supplied, the dates are compared.

### `If-None-Match` semantics

| Incoming `If-None-Match` | Match condition |
| :--- | :--- |
| `*` | Always `true` — the resource exists, the wildcard is satisfied. |
| `"v42"` | `true` when the incoming value matches `$etag` (weak comparison — `W/` prefix stripped on both sides). |
| `"v40", "v41", "v42"` | `true` when ANY entry matches `$etag` (weak comparison). |
| `W/"v42"` (weak) | Matches `"v42"` (strong) under weak comparison — the prefix is stripped before comparing. |

Weak comparison is the rule for `If-None-Match` per RFC 9110 §8.8.3.2 ; strong comparison is for `If-Match` / `If-Range` (out of scope for this helper).

### `If-Modified-Since` semantics

| Condition | Result |
| :--- | :--- |
| `$lastModified` is `null` | `false` (no reference date to compare against) |
| `If-Modified-Since` header malformed (not a valid HTTP-date) | `false` (defensive — can't trust the date) |
| `$lastModified->getTimestamp() <= If-Modified-Since timestamp` | `true` (the resource hasn't been modified since the client last fetched it) |
| `$lastModified` is more recent than `If-Modified-Since` | `false` |

HTTP-date parsing is delegated to [`oihana\http\helpers\dates\parseHttpDate()`](https://github.com/BcommeBois/oihana-php-http) which handles all three RFC 9110 §5.6.7 formats (IMF-fixdate, RFC 850, asctime).

## Response shape

`respondNotModified()` produces a response that follows [RFC 9110 §15.4.5](https://www.rfc-editor.org/rfc/rfc9110#status.304) :

- Status `304 Not Modified`.
- `ETag` header stamped (same value the caller would put on a `200`).
- Empty body.
- Other update-worthy headers (`Cache-Control`, `Vary`, `Date`) are NOT stamped by the helper — the caller adds them via the standard `withHeader()` chain before or after the helper call.

## Usage

```php
use DateTimeImmutable ;

use function oihana\middleware\helpers\cache\isNotModified ;
use function oihana\middleware\helpers\cache\respondNotModified ;

$etag         = '"v42-' . $resource->updatedAt->format( 'YmdHis' ) . '"' ;
$lastModified = $resource->updatedAt ;

if ( isNotModified( $request , $etag , $lastModified ) )
{
    return respondNotModified( $responseFactory->createResponse() , $etag ) ;
}

// Build the full response ; stamp ETag + Last-Modified for the next conditional GET.
$body = $template->render( $resource ) ;

return $response
    ->withHeader( 'ETag'          , $etag )
    ->withHeader( 'Last-Modified' , $lastModified->format( DATE_RFC7231 ) )
    ->withHeader( 'Cache-Control' , 'public, max-age=300' )
    ->withBody  ( $bodyFactory->createStream( $body ) ) ;
```

## Full recipe : ETag middleware wrapping an expensive handler

```php
use DateTimeImmutable ;
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Message\ResponseFactoryInterface ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use function oihana\middleware\helpers\cache\isNotModified ;
use function oihana\middleware\helpers\cache\respondNotModified ;

class EtagMiddleware implements MiddlewareInterface
{
    public function __construct
    (
        private readonly EtagResolver             $resolver ,        // your app — returns ['etag' => ..., 'lastModified' => DateTime] for a URL
        private readonly ResponseFactoryInterface $responseFactory ,
    ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        $resolved = $this->resolver->resolve( (string) $request->getUri() ) ;

        if ( $resolved === null )
        {
            // No etag computable — pass through.
            return $handler->handle( $request ) ;
        }

        [ 'etag' => $etag , 'lastModified' => $lastModified ] = $resolved ;

        if ( isNotModified( $request , $etag , $lastModified ) )
        {
            return respondNotModified( $this->responseFactory->createResponse() , $etag ) ;
        }

        $response = $handler->handle( $request ) ;

        return $response
            ->withHeader( 'ETag'          , $etag )
            ->withHeader( 'Last-Modified' , $lastModified->format( DATE_RFC7231 ) ) ;
    }
}
```

**Wiring key points :**

- **Compute the ETag from cheap signals** : a row's `updated_at`, a version counter, a cache key. NOT a hash of the full response body — that defeats the purpose.
- **Pair with `Cache-Control: max-age=...`** : `max-age` controls freshness ; `ETag` is the revalidation token when the freshness expires. They're complementary, not alternatives.
- **Use weak etags (`W/"..."`)** when the body may have semantically-equivalent variations (whitespace, header order). Use strong etags only when byte-identical content matters (range requests).

## Out of scope

This helper covers **read-side conditional GET**. It does NOT :

- **Evaluate `If-Match` or `If-Unmodified-Since`** — those are for write-side optimistic concurrency control (`PUT`/`PATCH` with "don't overwrite if changed"). The semantics are similar but inverted ; if you need them, ask and we can add `respondPreconditionFailed()` in a future lot.
- **Compute the ETag for you** — you provide it ; the helper compares. ETag computation is application-specific.
- **Handle `If-Range`** — range requests with conditional preconditions are a niche. Out of scope.
- **Send 304 for non-GET methods** — RFC 9110 reserves `304` for GET/HEAD. For PUT/PATCH/DELETE, a failed precondition is `412 Precondition Failed`.

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [Cache-Control](cache-control.md) — sibling helper for the freshness side of HTTP caching.
- [RFC 9110 §13.1](https://www.rfc-editor.org/rfc/rfc9110#section-13.1) — conditional requests semantics.
- [RFC 9110 §8.8.3](https://www.rfc-editor.org/rfc/rfc9110#section-8.8.3) — weak vs strong etag comparison.
