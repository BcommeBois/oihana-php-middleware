# oihana/php-middleware — User guide

![Language](https://img.shields.io/badge/language-English-blue)

`oihana/php-middleware` is a composable PHP library of procedural helpers for HTTP middleware: typed application of security response headers (HSTS, CSP, X-Frame-Options, Referrer-Policy, X-Content-Type-Options) and full CORS handling with preflight. PSR-7 compatible, zero magic strings, designed to slot into any PSR-15 middleware (Slim, Mezzio, Laminas, etc.) without imposing a framework.

## Audience

PHP developers building an API who need to:

- apply a consistent HTTP security policy on every response (HSTS to enforce HTTPS, CSP to bound asset sources, X-Frame-Options for clickjacking, Referrer-Policy for information leakage, X-Content-Type-Options for MIME sniffing) ;
- handle CORS properly with preflight management, origin allowlist, credentials, correctly emitted `Vary: Origin`, and defense against the `'*'` + credentials combo that browsers reject ;
- avoid magic strings everywhere thanks to typed enums (`ReferrerPolicy`, `FrameOptions`, `CspDirective`, `SecurityHeadersOption`, `CorsOption`).

## Quick start

```php
use function oihana\middleware\helpers\security\withSecurityHeaders ;
use function oihana\middleware\helpers\cors\applyCorsHeaders ;

use oihana\middleware\enums\SecurityHeadersOption ;
use oihana\middleware\enums\FrameOptions ;
use oihana\middleware\enums\ReferrerPolicy ;
use oihana\middleware\enums\CspDirective ;
use oihana\middleware\enums\CorsOption ;

// 1. Security headers on the response
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

// 2. CORS with allowlist + preflight
$response = applyCorsHeaders( $request , $response ,
[
    CorsOption::ALLOWED_ORIGINS   => [ 'https://app.example.com' ] ,
    CorsOption::ALLOWED_METHODS   => [ 'GET' , 'POST' , 'DELETE' ] ,
    CorsOption::ALLOWED_HEADERS   => [ 'Authorization' , 'Content-Type' ] ,
    CorsOption::ALLOW_CREDENTIALS => true ,
    CorsOption::MAX_AGE           => 3600 ,
]) ;
```

## Table of contents

- **[Getting started](getting-started.md)** — installation, PSR-7 mocking, first examples.
- **[Security headers](security-headers.md)** — `withSecurityHeaders`, `buildCspHeader`, enums `ReferrerPolicy` / `FrameOptions` / `CspDirective` / `SecurityHeadersOption`.
- **[CORS](cors.md)** — `applyCorsHeaders` with preflight, allowlist, credentials, exposed-headers, `CorsOption` enum.
- **[CSRF](csrf.md)** — `generateCsrfToken`, `verifyCsrfToken`, `CsrfField` enum. Stateless signed double-submit pattern, HMAC-SHA256, optional TTL.
- **[Request ID](request-id.md)** — `requestIdFromRequest`, `withRequestIdHeader`, `RequestIdField` enum. `X-Request-Id` propagation with conservative validation of the incoming header.
- **[Maintenance mode](maintenance.md)** — `respondMaintenanceMode`, `MaintenanceOption` enum. Clean 503 response with `Retry-After` (int / DateTime / string) and optional body.
- **[Rate limiting](rate-limiting.md)** — `enforceRateLimit`, `withRateLimitHeaders`, `RateLimitStore` interface, `InMemoryRateLimitStore`, `RateLimitDecision`, `RateLimitOption` enum. Fixed-window quota with pluggable store, legacy `X-RateLimit-*` or RFC 9421 draft headers.

## Source code

The library code lives under [`src/oihana/middleware/`](../../src/oihana/middleware/).

## See also

- [Packagist `oihana/php-middleware`](https://packagist.org/packages/oihana/php-middleware) — package page.
- [`oihana/php-http`](https://github.com/BcommeBois/oihana-php-http) — composable HTTP primitives (IP, cookies, signatures, content negotiation), consumed as a dependency.
- [`oihana/php-enums`](https://github.com/BcommeBois/oihana-php-enums) — typed HTTP constants (`HttpHeader`, `HttpMethod`, `HttpStatusCode`).
