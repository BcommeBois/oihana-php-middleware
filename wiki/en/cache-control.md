# Cache-Control

## Why you would want this — a concrete scenario

Your `/api/products` endpoint serves a list of products that updates twice a day. Without a `Cache-Control` header, here is what happens :

- The user's browser hits your endpoint on every navigation. 500 ms of database query, 500 ms of JSON encoding, 500 ms of network — every time.
- Your CDN (Cloudflare, Fastly, CloudFront) won't cache the response, because it has no idea how long the response is fresh. Every visitor goes all the way to your origin.
- Your monitoring shows the endpoint at the top of "slowest" and "most-called" — for data that doesn't change for hours.

You add a `Cache-Control` header. But you type it by hand :

```php
$response->withHeader( 'Cache-Control' , 'public, max_age=43200' ) ;
```

Spot the bug ? `max_age` instead of `max-age`. Underscores don't exist in HTTP — the directive is **silently ignored** by every cache. Your response now says "public" (cacheable by any cache) but with no freshness lifetime, so CDNs use their heuristic default (often a few seconds at most). You think you cached for 12 hours ; you cached for 5 seconds.

**With `buildCacheControl()`**, every directive name is a typed constant and the composition handles the syntax for you :

```php
use oihana\middleware\enums\CacheDirective ;
use function oihana\middleware\helpers\cache\buildCacheControl ;

$response->withHeader( 'Cache-Control' , buildCacheControl(
[
    CacheDirective::PUBLIC                 => true ,    // cacheable by browsers + CDNs
    CacheDirective::MAX_AGE                => 43200 ,   // 12 hours for browsers
    CacheDirective::S_MAXAGE               => 86400 ,   // 24 hours for CDNs
    CacheDirective::STALE_WHILE_REVALIDATE => 3600 ,    // serve stale while refreshing in background
] ) ) ;
// → "public, max-age=43200, s-maxage=86400, stale-while-revalidate=3600"
```

Same risk gone for every directive : `s-maxage`, `must-revalidate`, `stale-while-revalidate`, `immutable` — all typed, all spelled right.

When this is **not** useful : single-line `Cache-Control: no-store` for a single endpoint. The helper earns its keep when you have multiple directives or several endpoints with different policies, and you want the consistency of typed keys.

---

`oihana/php-middleware` ships a procedural helper to compose `Cache-Control` header values :

```php
namespace oihana\middleware\helpers\cache ;

function buildCacheControl( array $directives ) : string ;
```

Plus the [`CacheDirective`](../../src/oihana/middleware/enums/CacheDirective.php) enum with 13 standard directive names (RFC 9111 + RFC 5861 + RFC 8246).

## Accepted value shapes

| Value | Behaviour | Example |
| :--- | :--- | :--- |
| `true` | Flag directive emitted bare | `[ PUBLIC => true ]` → `public` |
| `false` | **Silently omitted** (canonical "off" semantics) | `[ NO_CACHE => false ]` → (nothing) |
| Non-negative `int` | Emitted as `directive=N` | `[ MAX_AGE => 3600 ]` → `max-age=3600` |
| Negative `int` | Silently omitted | `[ MAX_AGE => -1 ]` → (nothing) |
| `string` | Emitted verbatim as `directive=value` (rare — quoted-string form) | `[ NO_CACHE => '"Set-Cookie"' ]` → `no-cache="Set-Cookie"` |

The `false` ⇒ omit behaviour differs intentionally from [`buildCspHeader()`](security-headers.md#buildcspheader) which throws on `false`. `Cache-Control` directives have a meaningful "off" state (not emitting the directive disables it) ; CSP directives don't.

## Standard directives

All exposed as constants on [`CacheDirective`](../../src/oihana/middleware/enums/CacheDirective.php).

### Freshness (delta-seconds, require `int`)

| Constant | Token | Effect |
| :--- | :--- | :--- |
| `MAX_AGE` | `max-age` | Freshness lifetime in seconds, applies to all caches. |
| `S_MAXAGE` | `s-maxage` | Freshness lifetime for SHARED caches only (CDN, reverse proxies). Overrides `max-age` for them. |
| `STALE_WHILE_REVALIDATE` | `stale-while-revalidate` | Seconds after expiry a cache MAY serve stale while refreshing in background (RFC 5861). |
| `STALE_IF_ERROR` | `stale-if-error` | Seconds after expiry a cache MAY serve stale if the origin fails (RFC 5861). |

### Scope (flags)

| Constant | Token | Effect |
| :--- | :--- | :--- |
| `PUBLIC` | `public` | Explicitly cacheable by any cache. |
| `PRIVATE` | `private` | Only the user's private cache (browser) may store. |

### Cache controls (flags)

| Constant | Token | Effect |
| :--- | :--- | :--- |
| `NO_CACHE` | `no-cache` | Caches MUST revalidate before serving. NOT the same as `no-store`. |
| `NO_STORE` | `no-store` | Caches MUST NOT store. Strictest privacy. |
| `MUST_REVALIDATE` | `must-revalidate` | Stale responses MUST be revalidated, no serving stale even on origin failure. |
| `PROXY_REVALIDATE` | `proxy-revalidate` | Same as `must-revalidate` but for SHARED caches only. |
| `MUST_UNDERSTAND` | `must-understand` | Cache MUST understand the status code's semantics or refuse to store. |
| `NO_TRANSFORM` | `no-transform` | Intermediaries MUST NOT modify the payload (no lossy image recompression, etc.). |
| `IMMUTABLE` | `immutable` | Body will not change during freshness, caches SHOULD NOT revalidate even on user reload (RFC 8246). |

## Usage

```php
use function oihana\middleware\helpers\cache\buildCacheControl ;
use oihana\middleware\enums\CacheDirective ;

// 1 — Public API endpoint, cacheable for 1 hour
$response = $response->withHeader( 'Cache-Control' , buildCacheControl(
[
    CacheDirective::PUBLIC  => true ,
    CacheDirective::MAX_AGE => 3600 ,
] ) ) ;

// 2 — Sensitive endpoint, never cache, never store
$response = $response->withHeader( 'Cache-Control' , buildCacheControl(
[
    CacheDirective::NO_STORE => true ,
    CacheDirective::PRIVATE  => true ,
] ) ) ;

// 3 — Versioned static asset (hashed filename) — cache forever
$response = $response->withHeader( 'Cache-Control' , buildCacheControl(
[
    CacheDirective::PUBLIC    => true ,
    CacheDirective::MAX_AGE   => 31536000 , // 1 year
    CacheDirective::IMMUTABLE => true ,
] ) ) ;

// 4 — Aggressive CDN caching with stale-while-revalidate
$response = $response->withHeader( 'Cache-Control' , buildCacheControl(
[
    CacheDirective::PUBLIC                 => true ,
    CacheDirective::MAX_AGE                => 60 ,
    CacheDirective::S_MAXAGE               => 86400 ,  // CDN keeps it 24h
    CacheDirective::STALE_WHILE_REVALIDATE => 3600 ,   // serve stale up to 1h while refreshing
] ) ) ;
```

## Pitfalls the helper avoids

- **Typos in directive names** — `max_age` instead of `max-age` silently disables caching. Typed constants make this impossible.
- **Negative delta-seconds** — `max-age=-1` is interpreted by some caches as "always stale". The helper omits it.
- **Inconsistent join character** — directives must be `, `-separated, NOT `;`-separated (which would be a parameter syntax). The helper enforces the comma.

## Out of scope

This helper builds the **value** of the `Cache-Control` header. It does NOT :

- **Apply the header to a response** — the caller does `$response->withHeader('Cache-Control', buildCacheControl(...))` themselves. Keeping the helper pure makes it reusable for request `Cache-Control` (rare but legal) or for logging / testing.
- **Evaluate the request `Cache-Control`** — that's the cache's job (`max-age=0`, `no-cache` on a request override the cached response's freshness). Out of scope here.
- **Manage `Expires`, `Pragma`, `Vary`** — those are separate headers with their own grammars. Use `withHeader()` directly.

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [Conditional requests](conditional-requests.md) — sibling helper family for `ETag` / `If-None-Match` / `Last-Modified` / `If-Modified-Since` (304 responses).
- [RFC 9111 — HTTP Caching](https://www.rfc-editor.org/rfc/rfc9111.html) — the authoritative spec.
- [RFC 5861 — `stale-while-revalidate` / `stale-if-error`](https://www.rfc-editor.org/rfc/rfc5861.html).
- [RFC 8246 — `immutable`](https://www.rfc-editor.org/rfc/rfc8246.html).
