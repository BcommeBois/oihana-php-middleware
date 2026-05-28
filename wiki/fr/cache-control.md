# Cache-Control

## Pourquoi tu en aurais besoin — un scénario concret

Ton endpoint `/api/products` sert une liste de produits qui se met à jour deux fois par jour. Sans en-tête `Cache-Control`, voilà ce qui se passe :

- Le navigateur de l'utilisateur appelle ton endpoint à chaque navigation. 500 ms de query base, 500 ms de JSON encoding, 500 ms de réseau — à chaque fois.
- Ton CDN (Cloudflare, Fastly, CloudFront) ne cache pas la réponse, parce qu'il n'a aucune idée du temps pendant lequel elle est fraîche. Chaque visiteur va jusqu'à l'origine.
- Ton monitoring montre l'endpoint en tête des « plus lents » et « plus appelés » — pour des données qui ne changent pas pendant des heures.

Tu ajoutes un en-tête `Cache-Control`. Mais tu le tapes à la main :

```php
$response->withHeader( 'Cache-Control' , 'public, max_age=43200' ) ;
```

Tu vois le bug ? `max_age` au lieu de `max-age`. Les underscores n'existent pas en HTTP — la directive est **silencieusement ignorée** par tous les caches. Ta réponse dit maintenant « public » (cacheable par tous les caches) mais sans freshness lifetime, donc les CDN utilisent leur heuristique par défaut (souvent quelques secondes). Tu croyais cacher 12 heures ; tu caches 5 secondes.

**Avec `buildCacheControl()`**, chaque nom de directive est une constante typée et la composition s'occupe de la syntaxe pour toi :

```php
use oihana\middleware\enums\CacheDirective ;
use function oihana\middleware\helpers\cache\buildCacheControl ;

$response->withHeader( 'Cache-Control' , buildCacheControl(
[
    CacheDirective::PUBLIC                 => true ,    // cacheable par navigateurs + CDN
    CacheDirective::MAX_AGE                => 43200 ,   // 12 heures pour les navigateurs
    CacheDirective::S_MAXAGE               => 86400 ,   // 24 heures pour les CDN
    CacheDirective::STALE_WHILE_REVALIDATE => 3600 ,    // servir du stale pendant le rafraîchissement en arrière-plan
] ) ) ;
// → "public, max-age=43200, s-maxage=86400, stale-while-revalidate=3600"
```

Même risque éliminé pour chaque directive : `s-maxage`, `must-revalidate`, `stale-while-revalidate`, `immutable` — toutes typées, toutes correctement orthographiées.

Quand ce n'est **pas** utile : pour un `Cache-Control: no-store` d'une seule ligne sur un seul endpoint. Le helper prend tout son sens quand tu as plusieurs directives ou plusieurs endpoints avec des politiques différentes, et que tu veux la cohérence des clés typées.

---

`oihana/php-middleware` fournit un helper procédural pour composer la valeur de l'en-tête `Cache-Control` :

```php
namespace oihana\middleware\helpers\cache ;

function buildCacheControl( array $directives ) : string ;
```

Plus l'enum [`CacheDirective`](../../src/oihana/middleware/enums/CacheDirective.php) avec 13 noms de directives standards (RFC 9111 + RFC 5861 + RFC 8246).

## Formes de valeur acceptées

| Valeur | Comportement | Exemple |
| :--- | :--- | :--- |
| `true` | Directive flag émise telle quelle | `[ PUBLIC => true ]` → `public` |
| `false` | **Omise silencieusement** (sémantique canonical « off ») | `[ NO_CACHE => false ]` → (rien) |
| `int` non négatif | Émise comme `directive=N` | `[ MAX_AGE => 3600 ]` → `max-age=3600` |
| `int` négatif | Omise silencieusement | `[ MAX_AGE => -1 ]` → (rien) |
| `string` | Émise verbatim comme `directive=value` (rare — forme quoted-string) | `[ NO_CACHE => '"Set-Cookie"' ]` → `no-cache="Set-Cookie"` |

Le comportement `false` ⇒ omettre diffère intentionnellement de [`buildCspHeader()`](security-headers.md#buildcspheader) qui throw sur `false`. Les directives `Cache-Control` ont un état « off » sémantique (ne pas émettre la directive la désactive) ; les directives CSP non.

## Directives standards

Toutes exposées comme constantes sur [`CacheDirective`](../../src/oihana/middleware/enums/CacheDirective.php).

### Fraîcheur (delta-seconds, exigent un `int`)

| Constante | Token | Effet |
| :--- | :--- | :--- |
| `MAX_AGE` | `max-age` | Durée de fraîcheur en secondes, s'applique à tous les caches. |
| `S_MAXAGE` | `s-maxage` | Durée de fraîcheur pour les caches PARTAGÉS uniquement (CDN, reverse proxies). Surcharge `max-age` pour eux. |
| `STALE_WHILE_REVALIDATE` | `stale-while-revalidate` | Secondes après expiration pendant lesquelles un cache PEUT servir du stale en rafraîchissant en arrière-plan (RFC 5861). |
| `STALE_IF_ERROR` | `stale-if-error` | Secondes après expiration pendant lesquelles un cache PEUT servir du stale si l'origine échoue (RFC 5861). |

### Portée (flags)

| Constante | Token | Effet |
| :--- | :--- | :--- |
| `PUBLIC` | `public` | Explicitement cacheable par n'importe quel cache. |
| `PRIVATE` | `private` | Seul le cache privé de l'utilisateur (le navigateur) peut stocker. |

### Contrôles de cache (flags)

| Constante | Token | Effet |
| :--- | :--- | :--- |
| `NO_CACHE` | `no-cache` | Les caches DOIVENT revalider avant de servir. PAS la même chose que `no-store`. |
| `NO_STORE` | `no-store` | Les caches NE DOIVENT PAS stocker. La directive privacy la plus stricte. |
| `MUST_REVALIDATE` | `must-revalidate` | Une réponse stale DOIT être revalidée, pas de service stale même si l'origine échoue. |
| `PROXY_REVALIDATE` | `proxy-revalidate` | Idem `must-revalidate` mais pour les caches PARTAGÉS uniquement. |
| `MUST_UNDERSTAND` | `must-understand` | Le cache DOIT comprendre la sémantique du code de statut ou refuser de stocker. |
| `NO_TRANSFORM` | `no-transform` | Les intermédiaires NE DOIVENT PAS modifier le payload (pas de recompression lossy d'images, etc.). |
| `IMMUTABLE` | `immutable` | Le body ne changera pas pendant la fraîcheur, les caches NE DEVRAIENT PAS revalider même sur un reload utilisateur (RFC 8246). |

## Utilisation

```php
use function oihana\middleware\helpers\cache\buildCacheControl ;
use oihana\middleware\enums\CacheDirective ;

// 1 — Endpoint API public, cacheable 1 heure
$response = $response->withHeader( 'Cache-Control' , buildCacheControl(
[
    CacheDirective::PUBLIC  => true ,
    CacheDirective::MAX_AGE => 3600 ,
] ) ) ;

// 2 — Endpoint sensible, jamais cacher, jamais stocker
$response = $response->withHeader( 'Cache-Control' , buildCacheControl(
[
    CacheDirective::NO_STORE => true ,
    CacheDirective::PRIVATE  => true ,
] ) ) ;

// 3 — Asset statique versionné (nom de fichier hashé) — cache pour toujours
$response = $response->withHeader( 'Cache-Control' , buildCacheControl(
[
    CacheDirective::PUBLIC    => true ,
    CacheDirective::MAX_AGE   => 31536000 , // 1 an
    CacheDirective::IMMUTABLE => true ,
] ) ) ;

// 4 — Caching CDN agressif avec stale-while-revalidate
$response = $response->withHeader( 'Cache-Control' , buildCacheControl(
[
    CacheDirective::PUBLIC                 => true ,
    CacheDirective::MAX_AGE                => 60 ,
    CacheDirective::S_MAXAGE               => 86400 ,  // le CDN le garde 24h
    CacheDirective::STALE_WHILE_REVALIDATE => 3600 ,   // sert du stale jusqu'à 1h pendant le rafraîchissement
] ) ) ;
```

## Pièges évités par le helper

- **Fautes de frappe dans les noms de directives** — `max_age` au lieu de `max-age` désactive silencieusement le cache. Les constantes typées rendent ça impossible.
- **Delta-seconds négatives** — `max-age=-1` est interprété par certains caches comme « toujours stale ». Le helper l'omet.
- **Caractère de jointure incohérent** — les directives doivent être séparées par `, `, PAS par `;` (qui serait une syntaxe de paramètre). Le helper impose la virgule.

## Hors scope

Ce helper construit la **valeur** de l'en-tête `Cache-Control`. Il ne fait PAS :

- **Appliquer l'en-tête à une réponse** — l'appelant fait `$response->withHeader('Cache-Control', buildCacheControl(...))` lui-même. Garder le helper pur le rend réutilisable pour un `Cache-Control` de requête (rare mais légal) ou pour du logging / des tests.
- **Évaluer le `Cache-Control` de la requête** — c'est le travail du cache (`max-age=0`, `no-cache` sur une requête surchargent la fraîcheur de la réponse cachée). Hors scope ici.
- **Gérer `Expires`, `Pragma`, `Vary`** — ce sont des en-têtes séparés avec leur propre grammaire. Utilise `withHeader()` directement.

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [Requêtes conditionnelles](conditional-requests.md) — famille de helpers sœur pour `ETag` / `If-None-Match` / `Last-Modified` / `If-Modified-Since` (réponses 304).
- [RFC 9111 — HTTP Caching](https://www.rfc-editor.org/rfc/rfc9111.html) — la spec officielle.
- [RFC 5861 — `stale-while-revalidate` / `stale-if-error`](https://www.rfc-editor.org/rfc/rfc5861.html).
- [RFC 8246 — `immutable`](https://www.rfc-editor.org/rfc/rfc8246.html).
