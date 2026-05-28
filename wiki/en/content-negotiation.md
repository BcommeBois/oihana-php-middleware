# Content negotiation

`oihana/php-middleware` ships a thin PSR-7 wrapper to select the best server-side MIME type from a client `Accept` header:

```php
namespace oihana\middleware\helpers\negotiation ;

function negotiateMimeType( ServerRequestInterface $request , array $supported , ?string $default = null ) : ?string ;
```

Delegates the actual matching to [`oihana\http\helpers\negotiation\negotiate()`](https://github.com/BcommeBois/oihana-php-http) (from the `oihana/php-http` dependency), which honours RFC 7231 quality values and the standard `Accept` wildcards.

## Semantics

| `Accept` header | `$supported` | Returns |
| :--- | :--- | :--- |
| `application/json` | `['application/json', 'text/html']` | `'application/json'` |
| `text/html;q=0.9, application/json` | `['application/json', 'text/html']` | `'application/json'` (q=1.0 wins over q=0.9) |
| `text/*` | `['application/json', 'text/csv', 'text/html']` | `'text/csv'` (first `text/*` match in server order) |
| universal wildcard | `['text/html', 'application/json']` | `'text/html'` (server preference order) |
| `application/json;q=0, text/html` | `['application/json', 'text/html']` | `'text/html'` (q=0 is an explicit refusal — skipped) |
| `application/xml` | `['application/json', 'text/html']` | `$default` (or `null`) |
| missing | `['application/json', 'text/html']` | `$default` (or `null`) |

## Usage

```php
use function oihana\middleware\helpers\negotiation\negotiateMimeType ;

$mime = negotiateMimeType( $request ,
[
    'application/json' ,
    'text/html' ,
    'text/csv' ,
] ,
'application/json' ) ;

// $mime is one of the listed MIME types, or 'application/json' if no match.
```

## Full recipe: Slim middleware setting a `mimeType` attribute

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use function oihana\middleware\helpers\negotiation\negotiateMimeType ;

class ContentNegotiationMiddleware implements MiddlewareInterface
{
    public function __construct
    (
        private readonly array  $supported = [ 'application/json' , 'text/html' ] ,
        private readonly string $default   = 'application/json' ,
        private readonly string $attribute = 'mimeType' ,
    ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        $mime    = negotiateMimeType( $request , $this->supported , $this->default ) ;
        $request = $request->withAttribute( $this->attribute , $mime ) ;

        return $handler->handle( $request ) ;
    }
}
```

Downstream handlers read the chosen MIME type from the PSR-7 attribute and pick the right serializer / template engine accordingly.

## Out of scope

This helper covers **MIME-type negotiation only**. Power users wanting to negotiate other `Accept*` headers (`Accept-Language`, `Accept-Encoding`, `Accept-Charset`) should call [`oihana\http\helpers\negotiation\negotiate()`](https://github.com/BcommeBois/oihana-php-http) directly — `negotiateMimeType()` is just a one-line PSR-7 adapter for the `Accept` header. It also does NOT:

- **Parse `?format=` query string fallbacks** — that's an application concern (some apps want it, some don't).
- **Set the response `Content-Type`** — that's the handler's job, after it knows what it actually rendered.
- **Throw on unsupported types** — it returns `$default` (or `null`) so callers can decide how to handle "no acceptable type" (e.g. respond `406 Not Acceptable`).

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [`oihana/php-http` content negotiation](https://github.com/BcommeBois/oihana-php-http) — the underlying `negotiate()` and `parseAcceptHeader()` primitives, reusable for any `Accept*` header.
- [RFC 7231 §5.3](https://datatracker.ietf.org/doc/html/rfc7231#section-5.3) — content negotiation grammar.
