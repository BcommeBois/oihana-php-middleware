# Changelog

All notable changes to **oihana/php-middleware** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.7.1] - 2026-06-13

Maintenance release. **No functional change** on the v0.7.0 public surface — this release hardens the project's quality tooling and brings line coverage to 100%.

### Testing

- Line coverage raised to **100% (534/534 lines)** by exercising the last three reachable defensive guards that lacked a test: `isNotModified()` with a known `Last-Modified` reference but no precondition header at all, `enforceTrustedHosts()` with a present-but-malformed `Host` that strips to empty, and `respondMaintenanceMode()` with a message and a blank `Content-Type` override falling back to the `text/plain` default. 320 tests / 562 assertions (was 317 / 558). No production code changed.

### Tooling

- **Coverage report generator** `tools/clover-to-markdown.php` plus the `composer coverage` and `composer coverage:md` scripts (PHPUnit Clover → readable Markdown summary with a local trend log under `build/coverage/`, gitignored).
- **GitHub Actions CI** (`.github/workflows/ci.yml`) — runs the PHPUnit suite on PHP 8.4 with `composer validate --strict` and a Composer dependency cache.
- **`CONTRIBUTING.md`** — setup, the `test` / `coverage` / `coverage:md` commands, and the project's testing philosophy.

### Documentation

- `wiki/{fr,en}/getting-started.md` — the "two-minute tour" still advertised "3 procedural helpers in two thematic folders" with a security/cors-only tree ; replaced with the real folder map (all helper families plus the value objects and enums).
- `wiki/{fr,en}/security-headers.md` — the family intro listed three helpers and omitted `withDefaultSecurityBaseline()` (already documented further down) ; bumped to four with the missing bullet.
- `README.md` — "What you can do" was missing every helper family added since 0.5.0 ; added distributed tracing, Problem Details, webhook signatures, request defense, HTTP caching & conditional requests, pagination, and the three extra `Accept-*` negotiation helpers, plus the `composer coverage` / `coverage:md` commands.

## [0.7.0] - 2026-05-28

Seventh release. Three thematic helper families on top of v0.6.0 : **API correctness essentials** (Problem Details RFC 9457, request body size limit, generic webhook signature verification), **HTTP caching & content negotiation** (Cache-Control builder, conditional GET with `ETag` / `If-None-Match` / `If-Modified-Since`, three new `Accept-*` negotiation helpers), **defense & API ergonomics** (Host header allowlist, pagination headers RFC 5988 / RFC 8288). 11 new procedural helpers, 2 new value objects (`Problem`, `PaginationLinks`), 3 new typed enums (`ProblemField`, `WebhookSignatureOption`, `CacheDirective`), 96 new tests (317 total / 558 assertions). 6 new bilingual FR/EN wiki pages following the pedagogical pattern (concrete user-facing scenario before the API reference), 2 existing pages extended. No breaking change on the v0.6.0 surface.

### Trusted hosts defense

- **`enforceTrustedHosts()`** — new procedural helper that checks the incoming `Host` header against an allowlist. Sibling defense to `enforceMaxBodySize()`, targeting Host Header attacks (password-reset poisoning, cache poisoning, virtual-host routing bypass). Lives in the new `oihana\middleware\helpers\host\` namespace. Matching rules : exact match (case-insensitive per RFC 9110 §7.2), wildcard subdomain (`*.example.com` matches `api.example.com` and `staging.api.example.com` but NOT the apex), port stripped from incoming `Host:` before comparison (`example.com:8080` matches `example.com`). Nested or mid-string wildcards rejected as invalid. **Empty allowlist returns `true`** (intentional no-op : guard considered disabled, fails open rather than locking everyone out on a missing config). Missing or malformed `Host` returns `false` (defensive). Two internal helpers (`stripHostPort`, `matchTrustedHost`) exposed for testability.

### Pagination headers

- **`withPaginationHeaders()`** — new procedural helper that stamps RFC 5988 / RFC 8288 `Link` header (with the four standard `rel="first|prev|next|last"` entries) and the de-facto `X-Total-Count` header from a `PaginationLinks` value object. Implements the GitHub-style pagination pattern that keeps the response body pure data while exposing pagination state in headers — readable by generic HTTP clients (curl, Postman), CDNs that follow links, hypermedia SDKs. Link entries emitted in fixed order `first, prev, next, last`. Null URIs omitted ; `Link` header itself omitted when all four are null. `X-Total-Count` emitted when `totalCount` is non-null (including `0`, which is meaningful for empty result sets). The `X-Total-Count` name is the de-facto choice and not in any RFC ; callers wanting another name (`Total-Count`, `Total`) stamp it themselves via `withHeader()`. Lives in the new `oihana\middleware\helpers\pagination\` namespace.
- **`PaginationLinks`** — readonly value object carrying the four standard URIs (`first`, `prev`, `next`, `last`) and an optional `totalCount`. All properties optional and nullable. Lives in the new `oihana\middleware\pagination\` namespace. Helper is **URI-agnostic** : the caller constructs the URIs (knows whether the API uses `?page=`, `?offset=`, `?cursor=`, etc.) ; the helper just stamps.

### Documentation

- New bilingual wiki page `wiki/{fr,en}/pagination.md` following the pedagogical pattern (concrete envelope-in-body vs headers-in-headers scenario, full PageLinkBuilder service recipe).
- `wiki/{fr,en}/request-defense.md` extended with the `enforceTrustedHosts()` section (concrete password-reset poisoning attack scenario, matching rules table, behaviour matrix, defense stack positioning).
- TOC entries in `wiki/{fr,en}/README.md` updated with the new `pagination.md` page and the now-complete `request-defense.md` description.

### Cache-Control

- **`buildCacheControl()`** — new procedural helper that composes a `Cache-Control` header value from an associative array of directive names. Sibling of `buildCspHeader()` and `buildPermissionsPolicyHeader()`. Accepted value shapes : `true` (flag emitted bare), `false` (silently omitted — canonical "off" semantics, differs intentionally from `buildCspHeader()` which throws on `false` because Cache-Control directives have a meaningful "off" state), non-negative `int` (delta-seconds emitted as `directive=N`), negative `int` (silently omitted — prevents the nonsensical `max-age=-1` that some caches treat as "always stale"), `string` (verbatim, for the rare quoted-string form like `no-cache="Set-Cookie"`). Throws `InvalidArgumentException` on empty directive name or unsupported value type. Open vocabulary : raw directive names accepted, the enum isn't a closed list.
- **`CacheDirective`** enum — 13 typed constants covering RFC 9111 (`MAX_AGE`, `S_MAXAGE`, `PUBLIC`, `PRIVATE`, `NO_CACHE`, `NO_STORE`, `MUST_REVALIDATE`, `PROXY_REVALIDATE`, `MUST_UNDERSTAND`, `NO_TRANSFORM`), RFC 5861 (`STALE_WHILE_REVALIDATE`, `STALE_IF_ERROR`) and RFC 8246 (`IMMUTABLE`).

### Conditional requests

- **`isNotModified()`** — new procedural helper that evaluates the request's HTTP preconditions against a known `ETag` (and optional `Last-Modified` reference). Implements RFC 9110 §13.1.3 precedence : `If-None-Match` takes precedence over `If-Modified-Since` when both are present. `If-None-Match` uses weak comparison per RFC 9110 §8.8.3.2 (`W/` prefix stripped on both sides) and supports the wildcard (`*`) and the comma-separated list forms. `If-Modified-Since` is parsed via `oihana\http\helpers\dates\parseHttpDate()` (all three RFC 9110 §5.6.7 HTTP-date formats). **Malformed date ⇒ `false`** (defensive — can't claim the client is up-to-date if the header is unparseable). Two internal helpers (`matchIfNoneMatch`, `stripWeakPrefix`) are exposed for testability and for callers that already have a parsed precondition header.
- **`respondNotModified()`** — new procedural helper that turns a PSR-7 response into a canonical RFC 9110 §15.4.5 `304 Not Modified` response : status `304`, `ETag` header stamped, empty body. Other update-worthy headers (`Cache-Control`, `Vary`, `Date`) stay the caller's responsibility — stamp them via the standard `withHeader()` chain. PSR-7 immutable.

### Content negotiation (extended)

- **`negotiateLanguage()`** — new PSR-7 wrapper around `oihana\http\helpers\negotiation\negotiate()` targeting `Accept-Language`. Same semantics as `negotiateMimeType()`. Matches by exact tag (BCP 47 subtag inheritance is out of scope — `fr-CA` and `fr` are distinct candidates).
- **`negotiateEncoding()`** — new PSR-7 wrapper targeting `Accept-Encoding`. Useful when the server can serve multiple compressed forms (`br`, `gzip`, `identity`) and needs to pick the one the client supports.
- **`negotiateCharset()`** — new PSR-7 wrapper targeting `Accept-Charset`. Provided for completeness ; the header is deprecated by RFC 9110 §12.5.2 (modern browsers no longer send it), useful only for legacy or non-browser HTTP clients.

### Documentation

- Two new bilingual wiki pages following the pedagogical pattern : `wiki/{fr,en}/cache-control.md` (concrete `max_age` typo → silently disabled cache scenario, full directive reference matrix, common pitfalls section) and `wiki/{fr,en}/conditional-requests.md` (concrete blog home page bandwidth savings scenario, `If-None-Match` / `If-Modified-Since` precondition semantics, ETag middleware recipe). The `wiki/{fr,en}/content-negotiation.md` page extended with a "Beyond MIME types" section covering the three new `Accept-*` helpers. TOC entries added to `wiki/{fr,en}/README.md`.

### Problem Details (RFC 9457)

- **`respondProblemDetails()`** — new procedural helper that turns a PSR-7 response into a standardised RFC 9457 (formerly RFC 7807) error response. Sets HTTP status from the `Problem` value object (defaults to `400` when `null`), emits `Content-Type: application/problem+json`, writes the JSON body via `Problem::toArray()` with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` so URI references and non-ASCII titles stay readable. PSR-7 immutable.
- **`Problem`** — readonly value object with the 5 standard fields (`type`, `title`, `status`, `detail`, `instance` — all `?string`/`?int`, all optional and omitted from the JSON when `null`) plus an `extensions` bag for application-specific keys. `toArray()` honours the RFC field order and silently drops extension entries colliding with standard field names (RFC §3.2 — extensions MUST NOT shadow standard fields). Lives in the new `oihana\middleware\problem\` namespace.
- **`ProblemField`** enum — 5 typed constants (`TYPE`, `TITLE`, `STATUS`, `DETAIL`, `INSTANCE`) for the standard field names. Exposed so HTTP clients that parse Problem Details responses can share the same constants.

### Request body limit

- **`enforceMaxBodySize()`** — new pure predicate that checks the request `Content-Length` against a caller-supplied `$maxBytes`. Returns `true` when the body fits (or its length is unknown — streaming / chunked), `false` when it exceeds the limit or carries a malformed `Content-Length`. **Strict defensive default on malformed input** (negative, non-numeric, with leading sign or decimal — `ctype_digit`-based check matching the `1*DIGIT` grammar of RFC 9110 §8.6) so a payload bomb can never sneak through under an unverifiable header. Saturation-safe on 64-bit PHP for declared lengths beyond `PHP_INT_MAX`. Lives in the new `oihana\middleware\helpers\body\` namespace.

### Webhook signature verification

- **`verifyWebhookSignature()`** — new procedural helper for the simple-HMAC webhook authentication pattern : HMAC over the raw request body with a shared secret, compared in constant time via `hash_equals()`. Covers GitHub (`X-Hub-Signature-256: sha256=…`), Slack (`X-Slack-Signature: v0=…`), Shopify (`X-Shopify-Hmac-Sha256:` base64), Twilio (`X-Twilio-Signature:` SHA-1 base64), SendGrid (non-signed-timestamp variant), and any in-house webhook that picks up the same convention. **Stripe explicitly out of scope** (timestamp + version blended scheme requires freshness checks — use `stripe/stripe-php`). Short-circuits to `false` on empty secret or empty signature (misconfiguration guard). Unknown `ALGORITHM` falls back to `sha256` ; unknown `ENCODING` falls back to `hex`. Lives in the new `oihana\middleware\helpers\webhook\` namespace.
- **`WebhookSignatureOption`** enum — 3 typed constants (`ALGORITHM` default `'sha256'`, `PREFIX` default `null`, `ENCODING` default `'hex'`).

### Documentation

- Three new bilingual wiki pages following the pedagogical pattern : `wiki/{fr,en}/problem-details.md` (concrete `"error":"invalid"` vs RFC 9457 scenario), `wiki/{fr,en}/webhooks.md` (forged-GitHub-push scenario + provider compatibility matrix), `wiki/{fr,en}/request-defense.md` (2 GB upload OOM-killer scenario + defense-in-depth stack). TOC entries added to `wiki/{fr,en}/README.md`. The `request-defense.md` page will be extended in Lot C with `enforceTrustedHosts`.

## [0.6.0] - 2026-05-28

Sixth release. Final Tier 3 piece — W3C Trace Context propagation — plus a wiki-wide pedagogical pass. 3 new procedural helpers (`traceContextFromRequest`, `withTracingAttribute`, `withTraceparentResponseHeader` opt-in), 1 new pure function (`parseTraceparent`), 1 new value object (`TraceContext`), 2 new typed enums (`TracingField`, `ParsedTraceparentField`), 25 new tests (221 total / 423 assertions). All 8 pre-existing bilingual wiki pages aligned on the new pedagogical structure (concrete user-facing scenario before the API reference) inaugurated by the new `tracing.md` page. No breaking change on the v0.5.0 surface.

### Distributed tracing (W3C Trace Context)

- **`traceContextFromRequest()`** — new procedural helper that resolves the W3C Trace Context for an incoming PSR-7 request. Reads `traceparent` and `tracestate`, validates per W3C §3.2.2.4 (strict 55-char hex shape, version `00`, all-zero sentinels rejected), generates a fresh span id via `random_bytes(8)`, returns the resolved `TraceContext`. **Silent regen on missing or malformed input** — matches the W3C "treat as if no traceparent received" guidance and prevents misconfigured upstream proxies from breaking tracing. Fresh contexts default to `sampled = true` so first-hop traces are never silently dropped.
- **`withTracingAttribute()`** — stamps the resolved `TraceContext` on the request as a PSR-7 attribute (`traceContext` by default, configurable) so downstream handlers and loggers can read it without re-parsing the header.
- **`withTraceparentResponseHeader()`** — **opt-in** helper that stamps the resolved `traceparent` on the response. The W3C standard defines `traceparent` as forward-propagation only ; exposing it on the response is a pragmatic choice that lets users / support give the trace id back to debug a failed request in seconds. PSR-7 immutable.
- **`parseTraceparent()`** — pure W3C parser exposed in `oihana\middleware\tracing\`. Returns the raw components as an associative array (keys exposed as typed constants in `ParsedTraceparentField`) or `null` on any failure (never throws). Decoupled from request handling so it can be unit-tested in isolation and reused outside the middleware context.
- **`TraceContext`** — readonly value object carrying `traceId` (32 hex chars, inherited end-to-end), `spanId` (16 hex chars, fresh per hop), `parentSpanId`, `sampled`, `tracestate`. Single method `toTraceparent()` that builds the canonical 55-character header value for forwarding to downstream HTTP/DB calls.
- **`TracingField`** enum — 3 typed constants : `HEADER_TRACEPARENT` (`'traceparent'`), `HEADER_TRACESTATE` (`'tracestate'`), `ATTRIBUTE_NAME` (`'traceContext'`). The two header names duplicate `HttpHeader::TRACEPARENT` / `HttpHeader::TRACESTATE` on purpose to give the tracing family a self-contained local field map.
- **`ParsedTraceparentField`** enum — 3 typed constants (`TRACE_ID`, `PARENT_SPAN_ID`, `SAMPLED`) documenting the array shape returned by `parseTraceparent()` and consumed by `traceContextFromRequest()`. Keeps producer and consumer in sync without magic strings on either side.

### Documentation

- New bilingual wiki page `wiki/{fr,en}/tracing.md` opening on a concrete user-support scenario (500 error → trace id → 5-second debug across 5 microservices) before the API reference. Inaugurates the new pedagogical structure for wiki pages : start with the problem the helper solves (non-specialist-friendly), then move to API + recipe + when-it's-useful + out-of-scope sections.
- **All 8 pre-existing bilingual wiki pages aligned on the same pedagogical structure** in the same release : `security-headers.md` (stolen-cookie + uploaded-jpg-as-JS + injected-script scenarios), `cors.md` (DevTools "blocked by CORS policy" debugging scenario), `csrf.md` (cute-cats.example auto-submits to bank.example.com scenario), `request-id.md` (CSV export failed → reference code → 5-second log lookup), `maintenance.md` (90s DB migration cascade : 500 storm vs clean 503 + Retry-After), `rate-limiting.md` (login brute-force + runaway script scenarios), `observability.md` ("the dashboard feels slow today" — 1.2s endpoint split into 187ms server + 1s network), `content-negotiation.md` (one `/api/users` URL serving React/Excel/RSS). Every page now opens with a concrete "Why you would want this — a scenario" section that a non-specialist can read before diving into the API reference.

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

[Unreleased]: https://github.com/BcommeBois/oihana-php-middleware/compare/0.7.1...HEAD
[0.7.1]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.7.1
[0.7.0]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.7.0
[0.6.0]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.6.0
[0.5.0]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.5.0
[0.4.0]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.4.0
[0.3.0]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.3.0
[0.2.0]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.2.0
[0.1.0]: https://github.com/BcommeBois/oihana-php-middleware/releases/tag/0.1.0
