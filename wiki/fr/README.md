# oihana/php-middleware — Guide utilisateur

![Langue](https://img.shields.io/badge/langue-Français-blue)

`oihana/php-middleware` est une bibliothèque PHP composable de helpers procéduraux pour le middleware HTTP : application typée des en-têtes de sécurité (HSTS, CSP, X-Frame-Options, Referrer-Policy, X-Content-Type-Options) et gestion CORS avec preflight. Compatible PSR-7, zéro chaîne magique, conçue pour s'imbriquer dans n'importe quel middleware PSR-15 (Slim, Mezzio, Laminas, etc.) sans imposer de framework.

## À qui s'adresse cette documentation

Aux développeurs PHP qui construisent une API et veulent :

- appliquer une politique de sécurité HTTP cohérente sur chaque réponse (HSTS pour forcer HTTPS, CSP pour borner les sources d'assets, X-Frame-Options pour le clickjacking, Referrer-Policy pour la fuite d'info, X-Content-Type-Options pour le MIME sniffing) ;
- gérer le CORS proprement avec gestion du preflight, allowlist d'origines, credentials, `Vary: Origin` correctement émis, et défense contre la combo `'*'` + credentials que les navigateurs rejettent ;
- éviter les chaînes magiques partout grâce aux enums typés (`ReferrerPolicy`, `FrameOptions`, `CspDirective`, `SecurityHeadersOption`, `CorsOption`).

## Démarrage rapide

```php
use function oihana\middleware\helpers\security\withSecurityHeaders ;
use function oihana\middleware\helpers\cors\applyCorsHeaders ;

use oihana\middleware\enums\SecurityHeadersOption ;
use oihana\middleware\enums\FrameOptions ;
use oihana\middleware\enums\ReferrerPolicy ;
use oihana\middleware\enums\CspDirective ;
use oihana\middleware\enums\CorsOption ;

// 1. En-têtes de sécurité sur la réponse
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

// 2. CORS avec allowlist + preflight
$response = applyCorsHeaders( $request , $response ,
[
    CorsOption::ALLOWED_ORIGINS   => [ 'https://app.example.com' ] ,
    CorsOption::ALLOWED_METHODS   => [ 'GET' , 'POST' , 'DELETE' ] ,
    CorsOption::ALLOWED_HEADERS   => [ 'Authorization' , 'Content-Type' ] ,
    CorsOption::ALLOW_CREDENTIALS => true ,
    CorsOption::MAX_AGE           => 3600 ,
]) ;
```

## Sommaire

- **[Démarrage](getting-started.md)** — installation, mocking PSR-7, premiers exemples.
- **[En-têtes de sécurité](security-headers.md)** — `withSecurityHeaders`, `buildCspHeader`, enums `ReferrerPolicy` / `FrameOptions` / `CspDirective` / `SecurityHeadersOption`.
- **[CORS](cors.md)** — `applyCorsHeaders` avec preflight, allowlist, credentials, exposed-headers, enum `CorsOption`.
- **[CSRF](csrf.md)** — `generateCsrfToken`, `verifyCsrfToken`, enum `CsrfField`. Pattern signed double-submit stateless, HMAC-SHA256, TTL optionnel.
- **[Request ID](request-id.md)** — `requestIdFromRequest`, `withRequestIdHeader`, enum `RequestIdField`. Propagation `X-Request-Id` avec validation conservatrice de l'en-tête entrant.

## Code source

Le code de la bibliothèque vit sous [`src/oihana/middleware/`](../../src/oihana/middleware/).

## Voir aussi

- [Packagist `oihana/php-middleware`](https://packagist.org/packages/oihana/php-middleware) — page du package.
- [`oihana/php-http`](https://github.com/BcommeBois/oihana-php-http) — primitives HTTP composables (IP, cookies, signatures, négociation), consommé en dépendance.
- [`oihana/php-enums`](https://github.com/BcommeBois/oihana-php-enums) — constantes HTTP typées (`HttpHeader`, `HttpMethod`, `HttpStatusCode`).
