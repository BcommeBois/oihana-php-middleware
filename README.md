# Oihana PHP Middleware

Composable PHP HTTP middleware helpers. Part of the **Oihana PHP** ecosystem, this package ships procedural helpers to build typed security-headers responses and apply CORS with preflight handling — PSR-7 compatible, zero magic strings.

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

- **`withSecurityHeaders()`** — single helper to apply HSTS, `Content-Security-Policy`, `X-Frame-Options`, `Referrer-Policy`, `X-Content-Type-Options` to a PSR-7 `Response` in one call. Typed values via `ReferrerPolicy` and `FrameOptions` enums — no magic strings.
- **`buildCspHeader()`** — compose a `Content-Security-Policy` value from an associative array of directives. `CspDirective` enum exposes the canonical directive names.

### CORS

- **`applyCorsHeaders()`** — origin allowlist with configurable methods, headers, exposed headers, credentials and max-age. Handles the preflight `OPTIONS` request automatically. Defensive defaults: no `*` when `credentials = true`, `Vary: Origin` added when the allowlist is dynamic.

### Under the hood

- Pure PSR-7 — no framework lock-in. Works with Slim, Laravel, Symfony HTTP Foundation (via PSR-7 bridge), Hyperf, RoadRunner, etc.
- Built on `oihana/php-http` primitives (`isHttpsRequest`, etc.) and `oihana/php-enums` typed HTTP header constants.

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

- `oihana/php-http` – composable PHP HTTP primitives (client IP detection, signed URLs, cookies, content negotiation, …) consumed by this library: `https://github.com/BcommeBois/oihana-php-http`
- `oihana/php-enums` – typed HTTP constants (`HttpHeader`, …): `https://github.com/BcommeBois/oihana-php-enums`
