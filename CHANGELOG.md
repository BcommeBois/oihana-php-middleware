# Changelog

All notable changes to **oihana/php-middleware** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `withSecurityHeaders()` helper under `oihana\middleware\helpers\security` — applies the most common HTTP security response headers (HSTS, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Content-Security-Policy`) to a PSR-7 `Response` in a single call. Returns a new immutable response — never mutates the input. Each header is opt-in via its option key; omitting the option leaves the response untouched on that front. CSP accepts a string or a directive array (forwarded to `buildCspHeader()`). HSTS supports `includeSubDomains` (default `true`) and `preload` (default `false`) toggles. CSP can be emitted as `Content-Security-Policy-Report-Only` via `CSP_REPORT_ONLY: true`. Uses `oihana\enums\http\HttpHeader` constants under the hood — zero magic strings.
- `SecurityHeadersOption` enum class under `oihana\middleware\enums` — 8 typed constants (`HSTS`, `HSTS_INCLUDE_SUBDOMAINS`, `HSTS_PRELOAD`, `FRAME_OPTIONS`, `CONTENT_TYPE_NOSNIFF`, `REFERRER_POLICY`, `CSP`, `CSP_REPORT_ONLY`) for the option keys accepted by `withSecurityHeaders()`. Lets callers configure security headers without spelling the option strings by hand, matching the "zero magic strings" convention of the `oihana/php-*` ecosystem.
- `buildCspHeader()` helper under `oihana\middleware\helpers\security` — composes a `Content-Security-Policy` header value from an associative array of directives. Accepts three forms for sources: `string` (passed through), `list<string>` (joined with spaces), or `true` / `''` (flag directive like `upgrade-insecure-requests`). Directives joined with `'; '`. Empty input returns `''` so the caller can skip emitting the header. Composes cleanly with the `CspDirective` enum but raw directive strings are accepted too. Throws `InvalidArgumentException` on `false` value, empty directive name, empty source, or unsupported value type.
- `ReferrerPolicy` enum class under `oihana\middleware\enums` — 8 constants covering the full W3C Referrer Policy vocabulary (from `no-referrer` to `unsafe-url`).
- `FrameOptions` enum class under `oihana\middleware\enums` — `DENY` and `SAME_ORIGIN` constants (canonical values `'DENY'` and `'SAMEORIGIN'`). `ALLOW-FROM` intentionally omitted; the docblock points at `CspDirective::FRAME_ANCESTORS` for that use case (modern-browser guidance).
- `CspDirective` enum class under `oihana\middleware\enums` — 17 constants covering the most commonly used CSP Level 3 directive names. The enum is a typed convenience, not a closed vocabulary — `buildCspHeader()` will still accept raw strings for less common directives.
- Initial scaffold: Composer manifest (PHP 8.4+, `psr/http-message` 2.x, `oihana/php-enums`, `oihana/php-http` ^1.0), PHPUnit 12 + phpDocumentor 3 configuration, MPL-2.0 license, README, CHANGELOG.
