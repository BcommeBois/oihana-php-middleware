# Oihana PHP Middleware

![Oihana PHP Middleware](https://raw.githubusercontent.com/BcommeBois/oihana-php-middleware/main/assets/images/oihana-php-middleware-logo-inline-512x160.png)

Composable PHP HTTP middleware helpers. 

Part of the **Oihana PHP** ecosystem, this package ships procedural helpers for HTTP security headers, CORS, CSRF, request-id propagation, maintenance mode, fixed-window rate limiting, observability and content negotiation — PSR-7 compatible, zero magic strings.

[![Latest Version](https://img.shields.io/packagist/v/oihana/php-middleware.svg?style=flat-square)](https://packagist.org/packages/oihana/php-middleware)
[![Total Downloads](https://img.shields.io/packagist/dt/oihana/php-middleware.svg?style=flat-square)](https://packagist.org/packages/oihana/php-middleware)
[![License](https://img.shields.io/packagist/l/oihana/php-middleware.svg?style=flat-square)](LICENSE)

## 📚 Documentation

Full API reference (generated with phpDocumentor): `https://bcommebois.github.io/oihana-php-middleware`

User guides (FR + EN) live under [`wiki/`](wiki/).

## 📦 Installation

Requires [PHP 8.4+](https://php.net/releases/). Install via [Composer](https://getcomposer.org/):

```bash
composer require oihana/php-middleware
```

## ✨ What you can do

### Security headers

- **`withSecurityHeaders()`** — single helper to apply HSTS, `Content-Security-Policy`, `X-Frame-Options`, `Referrer-Policy`, `X-Content-Type-Options`, the three Cross-Origin policies (COOP / COEP / CORP) and `Permissions-Policy` to a PSR-7 `Response` in one call. Typed values via `ReferrerPolicy`, `FrameOptions`, `CrossOriginOpenerPolicy`, `CrossOriginEmbedderPolicy`, `CrossOriginResourcePolicy` and `PermissionsPolicyFeature` enums — no magic strings.
- **`buildCspHeader()`** — compose a `Content-Security-Policy` value from an associative array of directives. `CspDirective` enum exposes the canonical directive names.
- **`buildPermissionsPolicyHeader()`** — compose a `Permissions-Policy` value with a smart allowlist API (`false`, `true` / `'*'`, `'self'`, single origin or array — `self` stays a token, origins auto-quoted).
- **`withDefaultSecurityBaseline()`** — opinionated alias of `withSecurityHeaders()` shipping a "safe-for-most-apps" baseline (HSTS 1 year, `X-Frame-Options: DENY`, nosniff, `Referrer-Policy: strict-origin-when-cross-origin`, COOP / CORP `same-origin`) with caller-supplied overrides merged on top.

### CORS

- **`applyCorsHeaders()`** — origin allowlist with configurable methods, headers, exposed headers, credentials and max-age. Handles the preflight `OPTIONS` request automatically. Defensive defaults: no `*` when `credentials = true`, `Vary: Origin` added when the allowlist is dynamic.
- **`isCorsRequest()`** / **`isCorsPreflight()`** — pure predicates so middlewares can short-circuit on same-origin requests or detect a preflight without spelling out the underlying header names.

### CSRF

- **`generateCsrfToken()`** / **`verifyCsrfToken()`** — stateless signed double-submit pattern. Wire format `<id>.<exp>.<sig>` with a 128-bit base64url random `<id>`, an absolute expiry timestamp and a base64url HMAC-SHA256 signature keyed by your secret. Optional TTL. Constant-time verification — never throws on bad input, returns `bool`. `CsrfField` enum ships the conventional `cookie` and `header` names.

### Request ID

- **`requestIdFromRequest()`** — reads `X-Request-Id` from the incoming request and returns it when it passes a conservative shape check (1 to 128 chars, URL-safe alphabet), otherwise generates a fresh 128-bit base64url identifier. Defense-in-depth against log-pollution attacks via a forged incoming header.
- **`withRequestIdHeader()`** — stamps the request ID on the response (PSR-7 immutable).
- **`RequestIdField`** enum — `HEADER_NAME` (`X-Request-Id`) and `ATTRIBUTE_NAME` (`requestId`) for wiring the propagation through the middleware chain.

### Maintenance mode

- **`respondMaintenanceMode()`** — turns a PSR-7 response into a clean `503 Service Unavailable` with optional `Retry-After` header (accepts `int` delta-seconds, `DateTimeInterface` formatted as IMF-fixdate, or raw `string`) and optional body. `MaintenanceOption` enum carries the option keys.

### Rate limiting

- **`enforceRateLimit()`** — fixed-window rate-limit enforcement on PSR-7 requests. Identity resolved verbatim from `KEY` (string), via callable `fn(Request): string`, or by fallback to the client IP. Returns an immutable `RateLimitDecision` (`allowed`, `limit`, `remaining`, `reset`, `retryAfter`).
- **`withRateLimitHeaders()`** — stamps `Limit / Remaining / Reset` on the response from a decision, plus `Retry-After` when blocked. Defaults to the legacy `X-RateLimit-*` family, opt-in to the RFC 9421 draft `RateLimit-*` family.
- **`RateLimitStore`** interface — single atomic method `increment(string $key, int $window): int`. Shipped `InMemoryRateLimitStore` (process-local, for tests and CLI tools) ; production-grade `MemcachedRateLimitStore` in [`oihana/php-memcached`](https://github.com/BcommeBois/oihana-php-memcached).

### Observability

- **`withResponseTime()`** — stamps the elapsed processing time on the response. Two output formats: de-facto `X-Response-Time: 42.50ms` (default) or W3C `Server-Timing: total;dur=42.50` (opt-in via `ResponseTimeOption::USE_SERVER_TIMING`).

### Content negotiation

- **`negotiateMimeType()`** — thin PSR-7 wrapper over `oihana\http\helpers\negotiation\negotiate()` to select the best server-side MIME type from the client `Accept` header. Honours RFC 7231 quality values, standard wildcards (universal and `type/*`) and `q=0` explicit refusals.

### Under the hood

- Pure PSR-7 — no framework lock-in. Works with Slim, Laravel, Symfony HTTP Foundation (via PSR-7 bridge), Hyperf, RoadRunner, etc.
- Built on `oihana/php-http` primitives (IP detection, content negotiation, etc.) and `oihana/php-enums` typed HTTP constants (`HttpHeader`, `HttpMethod`, `HttpStatusCode`, `AuthScheme`, …).

## ✅ Running tests

Run all tests:

```bash
composer test
```

## 🛠️ Generate the documentation

```bash
composer doc
```

## 🧾 License

Licensed under the [Mozilla Public License 2.0 (MPL‑2.0)](https://www.mozilla.org/en-US/MPL/2.0/).

## 👤 About the author

- Author: Marc ALCARAZ (aka eKameleon)
- Email: `marc@ooop.fr`
- Website: `https://www.ooop.fr`

## 🔗 Related packages

| Package | Description |
| :--- | :--- |
| [`oihana/php-http`](https://github.com/BcommeBois/oihana-php-http) | Composable PHP HTTP primitives (client IP detection, signed URLs, cookies, content negotiation, …) consumed by this library. |
| [`oihana/php-enums`](https://github.com/BcommeBois/oihana-php-enums) | Typed HTTP constants (`HttpHeader`, `HttpMethod`, `HttpStatusCode`, `AuthScheme`, …). |
| [`oihana/php-memcached`](https://github.com/BcommeBois/oihana-php-memcached) | Production-grade `MemcachedRateLimitStore` implementing this package's `RateLimitStore` interface. |
