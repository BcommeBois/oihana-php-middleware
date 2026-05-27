# Changelog

All notable changes to **oihana/php-middleware** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Request ID

- **`requestIdFromRequest()`** — reads the `X-Request-Id` from the incoming request and returns it when it passes a conservative shape check (1 to 128 chars, URL-safe alphabet `[A-Za-z0-9_-]`); otherwise generates a fresh 128-bit base64url identifier via `oihana\core\encoding\randomBase64Url()`. Defense-in-depth against log-pollution and header-injection attacks via a forged incoming header.
- **`withRequestIdHeader()`** — stamps the request ID on the response. PSR-7 immutable: returns a new response, replaces any pre-existing value.
- **`RequestIdField`** enum — `HEADER_NAME = 'X-Request-Id'` and `ATTRIBUTE_NAME = 'requestId'`. Conventional default names for wiring request-id propagation through the middleware chain.

### CSRF

- **`generateCsrfToken()`** — issues a signed stateless CSRF token suitable for the double-submit pattern. Wire format `<id>.<exp>.<sig>`: a 128-bit base64url random `<id>` (CSPRNG from `oihana\core\encoding\randomBase64Url()`), an absolute Unix expiry timestamp `<exp>` (`'0'` when no TTL), and a base64url HMAC-SHA256 `<sig>` keyed by the supplied secret. Optional `$ttlSeconds` argument; `null` or `0` ⇒ no expiry. Throws `InvalidArgumentException` when the secret is empty.
- **`verifyCsrfToken()`** — verifies a token issued by `generateCsrfToken()` against the cookie / submitted pair. Checks: constant-time byte equality of the two tokens, three-part wire format, constant-time HMAC verification, TTL when present. Returns `bool` — never throws — so it can be plugged as the sole allow/deny gate.
- **`CsrfField`** enum — `COOKIE_NAME = 'csrf'`, `HEADER_NAME = 'X-CSRF-Token'`. Conventional default field names for wiring the double-submit pattern.

## [0.1.0] - 2026-05-27

First public release. Composable PHP middleware helpers for HTTP security: 3 procedural helpers (`withSecurityHeaders`, `buildCspHeader`, `applyCorsHeaders`) and 5 typed enums (`SecurityHeadersOption`, `ReferrerPolicy`, `FrameOptions`, `CspDirective`, `CorsOption`). PSR-7 compatible, zero magic strings, designed to slot into any PSR-15 middleware (Slim, Mezzio, Laminas, etc.) without imposing a framework. 62 PHPUnit tests, 137 assertions. Bilingual FR/EN wiki shipped from day one.

### Security headers

- **`withSecurityHeaders()`** — single-call application of HSTS, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` and `Content-Security-Policy` to a PSR-7 `Response`. Each header is opt-in; the response is never mutated. CSP accepts a raw string or a directive array. HSTS supports `includeSubDomains` (default `true`) and `preload` (default `false`). CSP can be emitted in report-only mode.
- **`buildCspHeader()`** — composes a `Content-Security-Policy` value from a directive array. Three accepted source forms: `string`, `list<string>`, or `true` / `''` for bare flag directives. Throws on invalid input (`false`, empty directive name, empty source, unsupported type).

### Security enums

- **`SecurityHeadersOption`** — 8 typed constants for the `withSecurityHeaders()` option keys (`HSTS`, `HSTS_INCLUDE_SUBDOMAINS`, `HSTS_PRELOAD`, `FRAME_OPTIONS`, `CONTENT_TYPE_NOSNIFF`, `REFERRER_POLICY`, `CSP`, `CSP_REPORT_ONLY`).
- **`ReferrerPolicy`** — full W3C vocabulary, 8 constants from `NO_REFERRER` to `UNSAFE_URL`.
- **`FrameOptions`** — `DENY` and `SAME_ORIGIN`. `ALLOW-FROM` intentionally omitted; the docblock points at `CspDirective::FRAME_ANCESTORS` instead, per modern-browser guidance.
- **`CspDirective`** — 17 constants for the most commonly used CSP Level 3 directive names. Open vocabulary: `buildCspHeader()` also accepts raw directive strings.

### CORS

- **`applyCorsHeaders()`** — applies CORS response headers to a PSR-7 `Response`, with full preflight handling. Echoes the request `Origin` only when it matches the allowlist; adds `Vary: Origin` idempotently; supports the wildcard `'*'` but throws when combined with `allowCredentials: true` (browsers reject this combo). On a preflight (`OPTIONS` + `Access-Control-Request-Method`), emits `Allow-Methods`, `Allow-Headers` (explicit list or echo of `Access-Control-Request-Headers`), and `Max-Age`. Status code is left to the calling middleware.
- **`CorsOption`** — 6 typed constants for the `applyCorsHeaders()` option keys (`ALLOWED_ORIGINS`, `ALLOWED_METHODS`, `ALLOWED_HEADERS`, `EXPOSED_HEADERS`, `ALLOW_CREDENTIALS`, `MAX_AGE`).

### Conventions

- All helpers live under `oihana\middleware\helpers\{security,cors}\` and are registered in `composer.json` `autoload.files` — they are usable as free functions, no class instantiation needed.
- All enums live under `oihana\middleware\enums\` and use `oihana\reflect\traits\ConstantsTrait` so introspection works via `getAll()` / `has()` / `enums()`.
- All helpers return a new `ResponseInterface` (PSR-7 immutable) — the input response is never mutated.
- HTTP header and method names are consumed from `oihana\enums\http\HttpHeader` and `oihana\enums\http\HttpMethod` (`oihana/php-enums`) — zero magic strings on either side.

### Documentation

- Bilingual FR/EN user wiki under `wiki/{fr,en}/` — 4 pages each: index, getting-started (installation + PSR-15 wiring example), security-headers reference, CORS reference.
- README with logo banner and Related packages table.

### Tooling

- Initial scaffold: Composer manifest (PHP 8.4+, `psr/http-message` 2.x, `oihana/php-enums` `dev-main`, `oihana/php-http` `dev-main`), PHPUnit 12 + phpDocumentor 3 configuration, MPL-2.0 license.
- 62 PHPUnit tests, 137 assertions covering the helpers and enums.

[Unreleased]: https://github.com/BcommeBois/oihana-php-middleware/compare/0.1.0...HEAD
[0.1.0]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.1.0
