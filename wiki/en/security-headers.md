# Security headers

`oihana/php-middleware` ships two procedural helpers to apply the most common HTTP security response headers to a PSR-7 response:

- [`withSecurityHeaders()`](#withsecurityheaders) — the single entry point that applies HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy and Content-Security-Policy in one call.
- [`buildCspHeader()`](#buildcspheader) — sub-helper that composes a `Content-Security-Policy` header value from a directive array.

Both are PSR-7 immutable: they return a **new** `ResponseInterface` — the supplied instance is never mutated.

## `withSecurityHeaders()`

```php
namespace oihana\middleware\helpers\security ;

function withSecurityHeaders( ResponseInterface $response , array $options = [] ) : ResponseInterface ;
```

The `$options` array is keyed by [`SecurityHeadersOption`](../../src/oihana/middleware/enums/SecurityHeadersOption.php) constants. Every option is **opt-in**: omitting a key leaves the response untouched on that front.

### Supported options

| Option | Type | Effect |
| :--- | :--- | :--- |
| `HSTS` | `int\|null` | `Strict-Transport-Security: max-age=N`. `null` or `0` ⇒ no header. |
| `HSTS_INCLUDE_SUBDOMAINS` | `bool` (default `true`) | Adds `; includeSubDomains` when HSTS is emitted. |
| `HSTS_PRELOAD` | `bool` (default `false`) | Adds `; preload` (see https://hstspreload.org). |
| `FRAME_OPTIONS` | `string\|null` | Value of `X-Frame-Options`. Use `FrameOptions::DENY` or `FrameOptions::SAME_ORIGIN`. |
| `CONTENT_TYPE_NOSNIFF` | `bool` (default `false`) | Emits `X-Content-Type-Options: nosniff` when `true`. |
| `REFERRER_POLICY` | `string\|null` | Value of `Referrer-Policy`. Use the `ReferrerPolicy::*` constants. |
| `CSP` | `string\|array\|null` | Value of `Content-Security-Policy`. If `array`, forwarded to `buildCspHeader()`. |
| `CSP_REPORT_ONLY` | `bool` (default `false`) | When `true`, emits `Content-Security-Policy-Report-Only` instead of `Content-Security-Policy`. Useful to test a policy in production without enforcing it. |

### Usage

```php
use function oihana\middleware\helpers\security\withSecurityHeaders ;
use oihana\middleware\enums\SecurityHeadersOption ;
use oihana\middleware\enums\FrameOptions ;
use oihana\middleware\enums\ReferrerPolicy ;
use oihana\middleware\enums\CspDirective ;

$response = withSecurityHeaders( $response ,
[
    SecurityHeadersOption::HSTS                 => 31536000 ,
    SecurityHeadersOption::FRAME_OPTIONS        => FrameOptions::DENY ,
    SecurityHeadersOption::CONTENT_TYPE_NOSNIFF => true ,
    SecurityHeadersOption::REFERRER_POLICY      => ReferrerPolicy::STRICT_ORIGIN_WHEN_CROSS_ORIGIN ,
    SecurityHeadersOption::CSP                  =>
    [
        CspDirective::DEFAULT_SRC => "'self'" ,
        CspDirective::IMG_SRC     => [ "'self'" , 'data:' ] ,
    ] ,
]) ;
```

Output on the response:

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; img-src 'self' data:
```

### CSP in report-only mode

```php
$response = withSecurityHeaders( $response ,
[
    SecurityHeadersOption::CSP             => $strictPolicy ,
    SecurityHeadersOption::CSP_REPORT_ONLY => true ,
]) ;
// => Content-Security-Policy-Report-Only: <strictPolicy>
```

Lets you deploy a strict policy in production while observing violations reported through `report-uri` / `report-to` — without breaking the app. Once zero violations are observed, switch to enforcement mode (`CSP_REPORT_ONLY: false`).

## `buildCspHeader()`

```php
namespace oihana\middleware\helpers\security ;

function buildCspHeader( array $directives ) : string ;
```

Composes a `Content-Security-Policy` header value from an associative array `directive => sources`.

### Accepted forms for each source

| Form | Example | Result |
| :--- | :--- | :--- |
| `string` | `'self' https://cdn.example.com` | Passed through |
| `list<string>` | `["'self'", 'https://cdn.example.com']` | Joined by space |
| `true` or `''` | `true` | Bare flag directive (e.g. `upgrade-insecure-requests`) |

Directives are joined by `'; '`. Empty input returns the empty string — the caller can then skip emitting the header entirely.

### Usage

```php
use function oihana\middleware\helpers\security\buildCspHeader ;
use oihana\middleware\enums\CspDirective ;

$value = buildCspHeader(
[
    CspDirective::DEFAULT_SRC               => "'self'" ,
    CspDirective::SCRIPT_SRC                => [ "'self'" , 'https://cdn.example.com' ] ,
    CspDirective::IMG_SRC                   => "'self' data:" ,
    CspDirective::UPGRADE_INSECURE_REQUESTS => true ,
]) ;
// => "default-src 'self'; script-src 'self' https://cdn.example.com; img-src 'self' data:; upgrade-insecure-requests"
```

The `CspDirective` enum exposes the most commonly used CSP Level 3 directives (`default-src`, `script-src`, `style-src`, `img-src`, `font-src`, `connect-src`, `media-src`, `object-src`, `frame-src`, `worker-src`, `manifest-src`, `base-uri`, `form-action`, `frame-ancestors`, `report-uri`, `report-to`, `upgrade-insecure-requests`). For a less common directive, pass the raw string as the key — the helper accepts it.

### Defense against invalid values

`buildCspHeader` throws `InvalidArgumentException` for:

- a `false` value (omit the key, or use `true` for a flag) ;
- an empty directive name ;
- an empty source in a list ;
- an unsupported value type.

These checks catch composition mistakes on the caller side early, rather than silently emitting a malformed CSP.

## See also

- [Getting started](getting-started.md) — wiring the helper inside a PSR-15 middleware.
- [CORS](cors.md) — the other helper family in this package.
- [CSP Level 3 spec](https://www.w3.org/TR/CSP3/) — official reference for the directives.
- [Referrer Policy spec](https://www.w3.org/TR/referrer-policy/) — semantics of the values.
