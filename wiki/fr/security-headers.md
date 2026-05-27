# En-têtes de sécurité

`oihana/php-middleware` fournit trois helpers procéduraux pour appliquer les en-têtes de sécurité HTTP les plus courants sur une réponse PSR-7 :

- [`withSecurityHeaders()`](#withsecurityheaders) — le point d'entrée unique qui applique HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Content-Security-Policy, les trois en-têtes Cross-Origin (COOP / COEP / CORP) et Permissions-Policy en un seul appel.
- [`buildCspHeader()`](#buildcspheader) — sous-helper pour composer la valeur d'un en-tête `Content-Security-Policy` depuis un tableau de directives.
- [`buildPermissionsPolicyHeader()`](#buildpermissionspolicyheader) — sous-helper pour composer la valeur d'un en-tête `Permissions-Policy` depuis un tableau de fonctionnalités.

Tous trois sont compatibles PSR-7 immutable : ils retournent une **nouvelle** `ResponseInterface`, l'instance fournie n'est jamais mutée.

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
| `CSP_REPORT_ONLY` | `bool` (default `false`) | Quand `true`, émet `Content-Security-Policy-Report-Only` au lieu de `Content-Security-Policy`. Utile pour tester une politique en production sans l'appliquer. |
| `COOP` | `string\|null` | Valeur de `Cross-Origin-Opener-Policy`. Utiliser les constantes `CrossOriginOpenerPolicy::*`. |
| `COEP` | `string\|null` | Valeur de `Cross-Origin-Embedder-Policy`. Utiliser les constantes `CrossOriginEmbedderPolicy::*`. |
| `CORP` | `string\|null` | Valeur de `Cross-Origin-Resource-Policy`. Utiliser les constantes `CrossOriginResourcePolicy::*`. |
| `PERMISSIONS_POLICY` | `string\|array\|null` | Valeur de `Permissions-Policy`. Si `array`, transmis à `buildPermissionsPolicyHeader()`. |

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

Permet de déployer une politique stricte en production en observant les violations remontées via `report-uri` / `report-to`, sans casser l'application. Une fois zéro violation observée, basculer en mode appliqué (`CSP_REPORT_ONLY: false`).

### En-têtes Cross-Origin (COOP / COEP / CORP)

Trois en-têtes sœurs qui contrôlent les interactions entre origines :

| En-tête | Constante | Ce qu'il contrôle |
| :--- | :--- | :--- |
| `Cross-Origin-Opener-Policy` | `CrossOriginOpenerPolicy` | Si un document de premier niveau peut partager son groupe de contextes de navigation avec des documents d'origines différentes (limite les attaques XS-Leaks, Spectre). |
| `Cross-Origin-Embedder-Policy` | `CrossOriginEmbedderPolicy` | Si le document peut intégrer des ressources venant d'autres origines sans accord explicite. |
| `Cross-Origin-Resource-Policy` | `CrossOriginResourcePolicy` | Quelles origines ont le droit d'intégrer *cette ressource-ci* comme sous-ressource. |

Le trio classique d'« isolement entre origines » débloque `SharedArrayBuffer` et les chronomètres haute résolution :

```php
$response = withSecurityHeaders( $response ,
[
    SecurityHeadersOption::COOP => CrossOriginOpenerPolicy::SAME_ORIGIN ,
    SecurityHeadersOption::COEP => CrossOriginEmbedderPolicy::REQUIRE_CORP ,
    SecurityHeadersOption::CORP => CrossOriginResourcePolicy::SAME_ORIGIN ,
]) ;
```

Pour des configurations moins strictes, `CrossOriginOpenerPolicy::SAME_ORIGIN_ALLOW_POPUPS` garde l'isolement tout en laissant les popups OAuth / paiement dans le groupe, et `CrossOriginEmbedderPolicy::CREDENTIALLESS` active l'isolement entre origines sans obliger les serveurs tiers à ajouter un en-tête CORP.

### Permissions-Policy

Désactive (ou restreint) les fonctionnalités du navigateur soumises à autorisation : caméra, micro, géolocalisation, APIs de paiement, USB, capteurs, presse-papier, etc. Deux formes acceptées :

- une **chaîne brute** si tu veux gérer la valeur de l'en-tête toi-même : `'geolocation=(), camera=*'` ;
- un **tableau** indexé par les constantes [`PermissionsPolicyFeature`](../../src/oihana/middleware/enums/PermissionsPolicyFeature.php) (ou des noms de fonctionnalités bruts), transmis à `buildPermissionsPolicyHeader()`.

```php
use oihana\middleware\enums\PermissionsPolicyFeature ;

$response = withSecurityHeaders( $response ,
[
    SecurityHeadersOption::PERMISSIONS_POLICY =>
    [
        PermissionsPolicyFeature::GEOLOCATION => false ,                            // refuser
        PermissionsPolicyFeature::CAMERA      => 'self' ,                           // même origine
        PermissionsPolicyFeature::PAYMENT     => [ 'self' , 'https://stripe.com' ], // soi-même + un partenaire
        PermissionsPolicyFeature::FULLSCREEN  => '*' ,                              // autoriser partout
    ] ,
]) ;
// => Permissions-Policy: geolocation=(), camera=(self), payment=(self "https://stripe.com"), fullscreen=*
```

Une base de départ « tout refuser ce qui touche à la vie privée » :

```php
PermissionsPolicyFeature::CAMERA         => false ,
PermissionsPolicyFeature::MICROPHONE     => false ,
PermissionsPolicyFeature::GEOLOCATION    => false ,
PermissionsPolicyFeature::PAYMENT        => false ,
PermissionsPolicyFeature::USB            => false ,
PermissionsPolicyFeature::MIDI           => false ,
PermissionsPolicyFeature::BLUETOOTH      => false ,
PermissionsPolicyFeature::HID            => false ,
PermissionsPolicyFeature::SERIAL         => false ,
PermissionsPolicyFeature::IDLE_DETECTION => false ,
PermissionsPolicyFeature::LOCAL_FONTS    => false ,
```

N'autorise que ce dont ton application a vraiment besoin et refuse explicitement tout le reste.

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

Ces vérifications sont là pour attraper tôt les erreurs de composition côté appelant, plutôt que d'émettre un CSP silencieusement malformé.

## `buildPermissionsPolicyHeader()`

```php
namespace oihana\middleware\helpers\security ;

function buildPermissionsPolicyHeader( array $directives ) : string ;
```

Compose une valeur d'en-tête `Permissions-Policy` depuis un tableau associatif `fonctionnalité => liste d'origines autorisées`.

### Formes acceptées pour chaque liste d'autorisation

| Forme | Exemple | Résultat |
| :--- | :--- | :--- |
| `false` | `false` | `()` — refus explicite |
| `true` ou `'*'` | `true` | `*` — autorise toutes les origines (seule forme sans parenthèses) |
| `'self'` | `'self'` | `(self)` — même origine uniquement |
| `'https://x.com'` | chaîne avec une seule origine | `("https://x.com")` — origine auto-mise entre guillemets |
| `'(self "https://x.com")'` | chaîne brute commençant par `(` | Passée telle quelle |
| `['self', 'https://x.com']` | tableau | `(self "https://x.com")` — `self` reste un mot-clé, les autres entrées sont auto-mises entre guillemets |
| `[]` | tableau vide | `()` — équivalent à `false` |

Les fonctionnalités sont jointes par `', '`. Une entrée vide retourne une chaîne vide — l'appelant peut alors choisir de ne pas émettre l'en-tête du tout.

### Usage

```php
use function oihana\middleware\helpers\security\buildPermissionsPolicyHeader ;
use oihana\middleware\enums\PermissionsPolicyFeature ;

$value = buildPermissionsPolicyHeader(
[
    PermissionsPolicyFeature::GEOLOCATION => false ,
    PermissionsPolicyFeature::CAMERA      => 'self' ,
    PermissionsPolicyFeature::PAYMENT     => [ 'self' , 'https://stripe.com' ] ,
    PermissionsPolicyFeature::FULLSCREEN  => '*' ,
]) ;
// => 'geolocation=(), camera=(self), payment=(self "https://stripe.com"), fullscreen=*'
```

L'enum `PermissionsPolicyFeature` expose ~40 fonctionnalités groupées par catégorie (vie privée, intégration & média, capteurs, identité & stockage, presse-papier & partage, attribution & tracking, deprecated). Pour une fonctionnalité non couverte par l'enum, passer la chaîne brute comme clé — le helper l'accepte.

### Défense contre les valeurs invalides

`buildPermissionsPolicyHeader` lève `InvalidArgumentException` pour :

- un nom de fonctionnalité vide ;
- une chaîne d'allowlist vide ;
- un élément non-string ou vide dans un tableau d'allowlist.

Ces vérifications sont là pour attraper tôt les erreurs de composition côté appelant, plutôt que d'émettre une `Permissions-Policy` silencieusement malformée.

## Voir aussi

- [Démarrage](getting-started.md) — câblage du helper dans un middleware PSR-15.
- [CORS](cors.md) — l'autre famille de helpers du paquet.
- [Spec CSP Level 3](https://www.w3.org/TR/CSP3/) — référence officielle des directives.
- [Spec Referrer Policy](https://www.w3.org/TR/referrer-policy/) — sémantique des valeurs.
- [Spec Permissions Policy](https://www.w3.org/TR/permissions-policy/) — liste des fonctionnalités et grammaire des allowlists.
- [HTML — Cross-Origin-Opener-Policy](https://html.spec.whatwg.org/multipage/browsers.html#cross-origin-opener-policies) et [Cross-Origin-Embedder-Policy](https://html.spec.whatwg.org/multipage/browsers.html#coep) — sémantique officielle des deux en-têtes d'isolement.
