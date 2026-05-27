# En-têtes de sécurité

`oihana/php-middleware` fournit deux helpers procéduraux pour appliquer les en-têtes de sécurité HTTP les plus courants sur une réponse PSR-7 :

- [`withSecurityHeaders()`](#withsecurityheaders) — le point d'entrée unique qui applique HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy et Content-Security-Policy en un seul appel.
- [`buildCspHeader()`](#buildcspheader) — sous-helper pour composer la valeur d'un en-tête `Content-Security-Policy` depuis un tableau de directives.

Tous deux sont compatibles PSR-7 immutable : ils retournent une **nouvelle** `ResponseInterface`, l'instance fournie n'est jamais mutée.

## `withSecurityHeaders()`

```php
namespace oihana\middleware\helpers\security ;

function withSecurityHeaders( ResponseInterface $response , array $options = [] ) : ResponseInterface ;
```

Le `$options` est un tableau associatif keyed par les constantes de l'enum [`SecurityHeadersOption`](../../src/oihana/middleware/enums/SecurityHeadersOption.php). Chaque option est **opt-in** : omettre la clé laisse la réponse intacte sur ce front.

### Options supportées

| Option | Type | Effet |
| :--- | :--- | :--- |
| `HSTS` | `int\|null` | `Strict-Transport-Security: max-age=N`. `null` ou `0` ⇒ pas d'en-tête. |
| `HSTS_INCLUDE_SUBDOMAINS` | `bool` (default `true`) | Ajoute `; includeSubDomains` quand HSTS est émis. |
| `HSTS_PRELOAD` | `bool` (default `false`) | Ajoute `; preload` (cf. https://hstspreload.org). |
| `FRAME_OPTIONS` | `string\|null` | Valeur de `X-Frame-Options`. Utiliser `FrameOptions::DENY` ou `FrameOptions::SAME_ORIGIN`. |
| `CONTENT_TYPE_NOSNIFF` | `bool` (default `false`) | Émet `X-Content-Type-Options: nosniff` quand `true`. |
| `REFERRER_POLICY` | `string\|null` | Valeur de `Referrer-Policy`. Utiliser les constantes `ReferrerPolicy::*`. |
| `CSP` | `string\|array\|null` | Valeur de `Content-Security-Policy`. Si `array`, transmis à `buildCspHeader()`. |
| `CSP_REPORT_ONLY` | `bool` (default `false`) | Quand `true`, émet `Content-Security-Policy-Report-Only` au lieu de `Content-Security-Policy`. Utile pour tester une politique en production sans l'enforcer. |

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

Résultat sur la réponse :

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; img-src 'self' data:
```

### CSP en mode rapport seul

```php
$response = withSecurityHeaders( $response ,
[
    SecurityHeadersOption::CSP             => $strictPolicy ,
    SecurityHeadersOption::CSP_REPORT_ONLY => true ,
]) ;
// => Content-Security-Policy-Report-Only: <strictPolicy>
```

Permet de déployer une politique stricte en production en observant les violations remontées via `report-uri` / `report-to`, sans casser l'application. Une fois zéro violation observée, basculer en mode enforcement (`CSP_REPORT_ONLY: false`).

## `buildCspHeader()`

```php
namespace oihana\middleware\helpers\security ;

function buildCspHeader( array $directives ) : string ;
```

Compose une valeur d'en-tête `Content-Security-Policy` depuis un tableau associatif `directive => sources`.

### Formes acceptées pour chaque source

| Forme | Exemple | Résultat |
| :--- | :--- | :--- |
| `string` | `'self' https://cdn.example.com` | Passé tel quel |
| `list<string>` | `["'self'", 'https://cdn.example.com']` | Joinées par espace |
| `true` ou `''` | `true` | Directive flag bare (ex. `upgrade-insecure-requests`) |

Les directives sont jointes par `'; '`. Une entrée vide retourne une chaîne vide — le caller peut alors choisir de ne pas émettre l'en-tête du tout.

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

L'enum `CspDirective` expose les directives CSP Level 3 les plus courantes (`default-src`, `script-src`, `style-src`, `img-src`, `font-src`, `connect-src`, `media-src`, `object-src`, `frame-src`, `worker-src`, `manifest-src`, `base-uri`, `form-action`, `frame-ancestors`, `report-uri`, `report-to`, `upgrade-insecure-requests`). Pour une directive moins courante, passer la chaîne brute comme clé — le helper l'accepte.

### Défense contre les valeurs invalides

`buildCspHeader` lève `InvalidArgumentException` pour :

- une valeur `false` (omettre la clé ou utiliser `true` pour un flag) ;
- un nom de directive vide ;
- une source vide dans une liste ;
- une valeur d'un type non supporté.

Ces vérifications sont là pour attraper tôt les erreurs de composition côté caller, plutôt que d'émettre un CSP silencieusement malformé.

## Voir aussi

- [Démarrage](getting-started.md) — câblage du helper dans un middleware PSR-15.
- [CORS](cors.md) — l'autre famille de helpers du paquet.
- [Spec CSP Level 3](https://www.w3.org/TR/CSP3/) — référence officielle des directives.
- [Spec Referrer Policy](https://www.w3.org/TR/referrer-policy/) — sémantique des valeurs.
