# Changelog

All notable changes to **oihana/php-middleware** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.5.0] - 2026-05-28

Fifth release. Tier 3 observability + bonus utilities on top of v0.4.0: response time stamping, content negotiation, CORS predicates and an opinionated default security baseline. 5 new procedural helpers (`withResponseTime`, `negotiateMimeType`, `isCorsRequest`, `isCorsPreflight`, `withDefaultSecurityBaseline`), 1 new typed enum (`ResponseTimeOption`), 2 new sub-namespaces (`helpers/observability/`, `helpers/negotiation/`), 31 new tests (196 total / 384 assertions). Two new bilingual FR/EN wiki pages (`observability.md`, `content-negotiation.md`) plus extensions of `cors.md` and `security-headers.md`. Main `README.md` "What you can do" section refreshed to reflect every helper family shipped since v0.1.0. No breaking change on the v0.4.0 surface.

### Observability

- **`withResponseTime()`** — new procedural helper that stamps the elapsed processing time on a PSR-7 response. Two output formats : the de-facto `X-Response-Time: 42.50ms` family (default, Express / Koa convention) and the W3C standard `Server-Timing: total;dur=42.50` family (opt-in via `ResponseTimeOption::USE_SERVER_TIMING`, parsed natively by Chromium / Firefox DevTools and most APM ingesters). Configurable decimal precision and metric name. Duration computed from a caller-supplied `microtime(true)` reference. Negative durations (clock skew, future start time) clamped to `0.00`. PSR-7 immutable.
- **`ResponseTimeOption`** enum — 3 typed constants (`PRECISION`, `USE_SERVER_TIMING`, `SERVER_TIMING_METRIC`).

### Content negotiation

- **`negotiateMimeType()`** — new procedural helper that selects the best server-side MIME type for an incoming PSR-7 request. Thin PSR-7 adapter over `oihana\http\helpers\negotiation\negotiate()` (from the `oihana/php-http` dependency) which honours RFC 7231 quality values, the standard `Accept` wildcards (universal and `type/*`) and `q=0` explicit refusals. Returns the matched MIME type or a caller-supplied default (or `null`). Power users targeting `Accept-Language` / `Accept-Encoding` / `Accept-Charset` can keep calling `oihana/php-http`'s `negotiate()` directly — this is just a one-line PSR-7 wrapper for the `Accept` case.

### CORS predicates

- **`isCorsRequest()`** — new pure predicate that returns `true` when the request carries an `Origin` header (the de-facto signal of a cross-origin browser request). Useful to short-circuit the CORS branch when the request is same-origin and therefore needs no CORS treatment.
- **`isCorsPreflight()`** — new pure predicate that returns `true` when the request method is `OPTIONS` AND the request carries an `Access-Control-Request-Method` header. A bare `OPTIONS` (no `Access-Control-Request-Method`) is correctly identified as NOT a preflight, so middlewares don't mistakenly intercept route discovery or server-info probes.

### Security baseline

- **`withDefaultSecurityBaseline()`** — new opinionated alias of `withSecurityHeaders()` shipping a "safe-for-most-apps" baseline (`HSTS: max-age=31536000; includeSubDomains`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Cross-Origin-Opener-Policy: same-origin`, `Cross-Origin-Resource-Policy: same-origin`). Deliberately omits `Cross-Origin-Embedder-Policy`, `Content-Security-Policy`, `Permissions-Policy` and HSTS `preload` — each is application-specific and would break legitimate setups by default. Caller-supplied `$overrides` array is merged on top of the baseline, so callers can tune any default or add additional headers without losing the rest.

### Documentation

- Two new bilingual wiki pages : `wiki/{fr,en}/observability.md` (`withResponseTime` + `ResponseTimeOption`, with options table, header families matrix, Slim middleware recipe and out-of-scope section) and `wiki/{fr,en}/content-negotiation.md` (`negotiateMimeType` with semantics matrix and Slim middleware recipe).
- Wiki `wiki/{fr,en}/cors.md` extended with a `CORS predicates` section documenting `isCorsRequest` and `isCorsPreflight`.
- Wiki `wiki/{fr,en}/security-headers.md` extended with a top-level `withDefaultSecurityBaseline()` section documenting the baseline values, the deliberate omissions, and the overrides mechanism.
- TOC entries added to `wiki/{fr,en}/README.md` for the two new pages.

### Dependencies

- Picks up the new `HttpHeader::X_RESPONSE_TIME` and `HttpHeader::SERVER_TIMING` constants from `oihana/php-enums` (added in commit `91be75e` of that package).

## [0.4.0] - 2026-05-27

Fourth release. One new helper family added on top of v0.3.0: extended Security headers — Cross-Origin policies (COOP / COEP / CORP) and Permissions-Policy. 1 new procedural helper (`buildPermissionsPolicyHeader`), 4 new typed enums (`CrossOriginOpenerPolicy`, `CrossOriginEmbedderPolicy`, `CrossOriginResourcePolicy`, `PermissionsPolicyFeature`), 4 new `SecurityHeadersOption` keys (`COOP`, `COEP`, `CORP`, `PERMISSIONS_POLICY`), 24 new tests (165 total / 336 assertions). Bilingual FR/EN wiki extended with two new sections under `withSecurityHeaders` and one new top-level section. No breaking change on the v0.3.0 surface.

### Cross-Origin policies

- **`withSecurityHeaders()`** extended with three new options — `SecurityHeadersOption::COOP`, `COEP`, `CORP` — that emit `Cross-Origin-Opener-Policy`, `Cross-Origin-Embedder-Policy`, and `Cross-Origin-Resource-Policy` respectively. Same opt-in semantics as the existing `FRAME_OPTIONS` / `REFERRER_POLICY` keys (omitted / `null` / empty string ⇒ no header). The classic "cross-origin isolation" triad (`COOP=same-origin` + `COEP=require-corp` + `CORP=same-origin`) unlocks `SharedArrayBuffer` and high-resolution timers in modern browsers.
- **`CrossOriginOpenerPolicy`** enum — 5 typed constants (`UNSAFE_NONE`, `SAME_ORIGIN_ALLOW_POPUPS`, `SAME_ORIGIN`, `NOOPENER_ALLOW_POPUPS`, `RESTRICT_PROPERTIES`). Includes recent Chromium additions (`NOOPENER_ALLOW_POPUPS`, `RESTRICT_PROPERTIES`).
- **`CrossOriginEmbedderPolicy`** enum — 3 typed constants (`UNSAFE_NONE`, `REQUIRE_CORP`, `CREDENTIALLESS`). `CREDENTIALLESS` enables cross-origin isolation without requiring third-party servers to ship CORP headers.
- **`CrossOriginResourcePolicy`** enum — 3 typed constants (`SAME_SITE`, `SAME_ORIGIN`, `CROSS_ORIGIN`).

### Permissions-Policy

- **`buildPermissionsPolicyHeader()`** — composes a `Permissions-Policy` header value from a `feature => allowlist` array. Smart per-feature allowlist API mirroring the `buildCspHeader()` ergonomics: `false` ⇒ `()` (deny), `true` or `'*'` ⇒ `*` (allow all), `'self'` ⇒ `(self)`, single origin string ⇒ auto-quoted, array ⇒ composed item-by-item with `self` kept as a token and every other entry auto-quoted as an origin. Raw strings starting with `(` are passed through verbatim (escape hatch). Throws `InvalidArgumentException` on malformed input.
- **`withSecurityHeaders()`** extended with `SecurityHeadersOption::PERMISSIONS_POLICY` (`string|array|null`). When an array is supplied it is forwarded to `buildPermissionsPolicyHeader()` to compose the value, mirroring the `CSP` option pattern.
- **`PermissionsPolicyFeature`** enum — ~40 typed constants grouped by category (privacy-sensitive: camera/microphone/geolocation/payment/usb/midi/bluetooth/hid/serial ; embedding & media: fullscreen/picture-in-picture/autoplay/encrypted-media/display-capture/speaker-selection ; sensors: accelerometer/gyroscope/magnetometer/ambient-light-sensor/compute-pressure/gamepad ; identity & storage: publickey-credentials-{get,create}/identity-credentials-get/otp-credentials/storage-access ; clipboard & sharing: clipboard-{read,write}/web-share/screen-wake-lock/idle-detection/local-fonts/window-management ; attribution & tracking: attribution-reporting/browsing-topics/xr-spatial-tracking/cross-origin-isolated/battery/keyboard-map ; deprecated: interest-cohort/document-domain/sync-xhr/execution-while-{not-rendered,out-of-viewport}). Open vocabulary: `buildPermissionsPolicyHeader()` also accepts raw feature names.

### Documentation

- Wiki `wiki/{fr,en}/security-headers.md` extended with two new sections under `withSecurityHeaders` (`Cross-Origin policies (COOP / COEP / CORP)` + `Permissions-Policy`) and one new top-level section (`buildPermissionsPolicyHeader()`). Includes the cross-origin isolation triad recipe and a "deny everything sensitive" Permissions-Policy baseline.

## [0.3.0] - 2026-05-27

Third release. One new helper family added on top of v0.2.0: Rate limiting (fixed-window quota enforcement on PSR-7 requests with a pluggable store backend). 2 new procedural helpers (`enforceRateLimit`, `withRateLimitHeaders`), 1 new interface (`RateLimitStore`), 2 new classes (`InMemoryRateLimitStore`, `RateLimitDecision`), 1 new typed enum (`RateLimitOption`), 26 new tests (141 total / 301 assertions). Bilingual FR/EN wiki extended with a dedicated page. Memcached-backed store added as a `composer.json` `suggest` (shipped separately in `oihana/php-memcached`). No breaking change on the v0.2.0 surface.

### Rate limiting

- **`enforceRateLimit()`** — fixed-window rate-limit enforcement on PSR-7 requests. Atomic counter keyed on `(KEY_PREFIX, SCOPE?, identity, windowStart)` with deterministic `reset = floor(now/window)*window + window`. Identity resolved verbatim from `KEY` (string), via callable `fn(ServerRequestInterface): string`, or by fallback to the client IP from `oihana\http\helpers\ips\getClientIp()` — `'unknown'` sentinel used when no usable address is found so the helper never silently degrades. Returns an immutable `RateLimitDecision` (`allowed`, `limit`, `remaining`, `reset`, `retryAfter`) — caller stays responsible for the 429 body and the `Content-Type`.
- **`withRateLimitHeaders()`** — stamps `Limit / Remaining / Reset` on the response from a `RateLimitDecision`, plus `Retry-After` when the decision is blocked. Defaults to the legacy `X-RateLimit-*` family (aligned with existing oihana/api convention and most client tooling), opt-in to the RFC 9421 draft `RateLimit-*` family via the `rfc9421: true` flag. PSR-7 immutable.
- **`RateLimitStore`** interface — single atomic method `increment(string $key, int $window): int`. On initial creation the counter is seeded at `1` with a TTL of `$window` seconds; the TTL is anchored on the first request, not extended on subsequent increments. Fits every production backend that exposes atomic increment-with-TTL (Memcached, Redis, APCu).
- **`InMemoryRateLimitStore`** — process-local implementation shipped for tests, CLI scripts, single-process tools and demos. Not thread-safe nor shared across workers — explicitly documented as not for production HTTP traffic. Accepts an optional clock callable for deterministic time travel in tests.
- **`RateLimitDecision`** — readonly value object returned by `enforceRateLimit()`. Plain DTO, no methods.
- **`RateLimitOption`** enum — 6 typed constants (`LIMIT`, `WINDOW`, `KEY`, `KEY_PREFIX`, `SCOPE`, `NOW`) for the option keys. Invalid `LIMIT` / `WINDOW` / `KEY_PREFIX` fall back to safe defaults.
- **Out of scope on purpose** — no rule resolution by path / method (caller decides), no multi-counter combination (call `enforceRateLimit()` twice with two scopes), no JWT decode / DB lookup (use the `KEY` callable), no 429 body opinion. Token bucket / sliding window deliberately deferred to a future helper.
- **Memcached adapter** — `oihana/php-memcached` will ship `MemcachedRateLimitStore` consuming this interface. Added as a `suggest` entry in `composer.json` — no hard dependency, no extension required on `oihana/php-middleware` itself.

### Documentation

- Bilingual wiki page `rate-limiting.md` (FR + EN) added with options table, header families table, identity resolution table, store-choice matrix, Slim middleware recipe and out-of-scope section. TOCs in both `wiki/{fr,en}/README.md` updated.

## [0.2.0] - 2026-05-27

Second release. Three new helper families added on top of v0.1.0: CSRF (signed double-submit pattern), Request ID (X-Request-Id propagation with conservative validation), and Maintenance mode (clean 503 response with Retry-After). 6 new procedural helpers, 3 new typed enums, 53 new tests (115 total / 234 assertions). Bilingual FR/EN wiki extended with three dedicated pages. No breaking change on the v0.1.0 surface.

### Maintenance mode

- **`respondMaintenanceMode()`** — turns a PSR-7 response into a clean `503 Service Unavailable` with optional `Retry-After` header and body. `Retry-After` accepts `int` (delta-seconds), `DateTimeInterface` (formatted as IMF-fixdate via `oihana\http\helpers\dates\formatHttpDate()`), or non-empty `string` (passed through). Body emitted only when `MESSAGE` option is supplied, with `Content-Type` defaulting to `text/plain; charset=utf-8`. Pre-existing unrelated response headers are preserved.
- **`MaintenanceOption`** enum — 3 typed constants (`RETRY_AFTER`, `MESSAGE`, `CONTENT_TYPE`) for the option keys.

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

[Unreleased]: https://github.com/BcommeBois/oihana-php-middleware/compare/0.5.0...HEAD
[0.5.0]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.5.0
[0.4.0]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.4.0
[0.3.0]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.3.0
[0.2.0]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.2.0
[0.1.0]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.1.0
