# Content negotiation

## Why you would want this — a concrete scenario

Your `/api/users` endpoint is consumed by :

- **The React frontend** that wants JSON to render a user list.
- **The finance analyst** who exports the same data to Excel — they want CSV.
- **A future RSS reader** somebody on your team will build next quarter — they want XML.

Three different MIME types, one URL, one set of data. Two ways to handle it :

**Without content negotiation** — you sprinkle a `?format=json` query parameter everywhere :

```
GET /api/users?format=json
GET /api/users?format=csv
GET /api/users?format=xml
```

Now you also need to handle bad values (`?format=html`), normalize aliases (`xls` vs `excel` vs `csv`), document the param on every endpoint, sync the list between backend and frontend. And HTTP caches will treat each format as a different URL — fragmenting your CDN cache.

**With content negotiation**, the client just tells you what it accepts via the standard HTTP `Accept` header :

```
GET /api/users
Accept: application/json
```

```
GET /api/users
Accept: text/csv;q=1.0, application/json;q=0.5
```

Same URL, multiple representations, standard HTTP. The `Vary: Accept` mechanism lets your CDN cache each variant correctly. Quality values (`q=0.9`) let clients express preferences. Wildcards (`text/*`) let them say "any text format you have".

`negotiateMimeType()` reads the `Accept` header, matches against the list of MIME types your server can actually produce, returns the best match. You then pick the right serializer / template / formatter and respond with the matching `Content-Type`.

When this is **not** useful : if your endpoint serves a single representation (only ever JSON), the negotiation is overhead — just set `Content-Type: application/json` and move on.

---

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

## Beyond MIME types — `Accept-Language`, `Accept-Encoding`, `Accept-Charset`

Three sibling helpers cover the rest of the `Accept-*` family. Each is a one-line PSR-7 adapter to `oihana\http\helpers\negotiation\negotiate()` — same semantics as `negotiateMimeType()`, different header.

```php
namespace oihana\middleware\helpers\negotiation ;

function negotiateLanguage( ServerRequestInterface $request , array $supported , ?string $default = null ) : ?string ;
function negotiateEncoding( ServerRequestInterface $request , array $supported , ?string $default = null ) : ?string ;
function negotiateCharset ( ServerRequestInterface $request , array $supported , ?string $default = null ) : ?string ;
```

| Helper | Reads header | Typical use |
| :--- | :--- | :--- |
| `negotiateLanguage()` | `Accept-Language` | i18n — pick the locale to render. |
| `negotiateEncoding()` | `Accept-Encoding` | Pick `br` / `gzip` / `identity` for response compression. |
| `negotiateCharset()` | `Accept-Charset` | Pick `utf-8` / `iso-8859-1`. **`Accept-Charset` is deprecated by RFC 9110 §12.5.2** — modern browsers no longer send it. Useful only for legacy or non-browser HTTP clients. |

```php
use function oihana\middleware\helpers\negotiation\negotiateLanguage ;

// Client sends `Accept-Language: fr-CH,fr;q=0.8,en;q=0.5`
$locale = negotiateLanguage( $request , [ 'en' , 'fr' , 'de' ] , 'en' ) ;
// → 'fr' (matches the second entry — `fr-CH` is an exact-only candidate)
```

Note for `negotiateLanguage()` : matching is by exact tag, not by BCP 47 subtag inheritance. `fr-CA` and `fr` are distinct candidates ; for full subtag lookup (a client asking for `fr-CH` falling back to `fr`), layer a dedicated lib on top.

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [`oihana/php-http` content negotiation](https://github.com/BcommeBois/oihana-php-http) — the underlying `negotiate()` and `parseAcceptHeader()` primitives, reusable for any `Accept*` header.
- [RFC 7231 §5.3](https://datatracker.ietf.org/doc/html/rfc7231#section-5.3) — content negotiation grammar.
