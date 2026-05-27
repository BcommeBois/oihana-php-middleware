# Limitation de débit (rate limiting)

`oihana/php-middleware` fournit un helper procédural pour appliquer une politique de rate-limit à fenêtre fixe sur les requêtes PSR-7 :

```php
namespace oihana\middleware\helpers\rateLimit ;

function enforceRateLimit
(
    ServerRequestInterface $request ,
    RateLimitStore         $store   ,
    array                  $config  = []
) : RateLimitDecision ;

function withRateLimitHeaders
(
    ResponseInterface $response ,
    RateLimitDecision $decision ,
    bool              $rfc9421 = false ,
) : ResponseInterface ;
```

Le helper prend la décision (autoriser ou bloquer) et te laisse construire la réponse `429` elle-même — pas de body imposé, pas de couplage framework, pas de hook JWT / cookie / DB. Le back-end de stockage est branchable via l'interface `RateLimitStore` ; une implémentation in-memory est shippée, une version Memcached vit dans [`oihana/php-memcached`](https://github.com/BcommeBois/oihana-php-memcached).

## Algorithme

**Fenêtre fixe** : l'horloge est découpée en fenêtres de `WINDOW` secondes ancrées sur `floor(now / window) * window`. Chaque requête incrémente un compteur atomique pour le triplet `(scope, identity, windowStart)`. Quand le compteur dépasse `LIMIT`, la décision bascule sur `!allowed` jusqu'au reset.

Pourquoi la fenêtre fixe :

- mappe trivialement sur une simple opération atomique d'incrément — supportée nativement par tous les back-ends de production (Memcached, Redis, APCu) ;
- 1 clé de stockage par fenêtre active, empreinte mémoire minimale ;
- les en-têtes `RateLimit-*` / `X-RateLimit-*` sont conçus autour (une limite, un reset).

Token bucket et sliding-window-counter ne sont pas fournis. Ils ne sont pas nécessaires pour du rate-limit d'API typique, et imposeraient un contrat de store plus riche (CAS / locks). Ils pourront être ajoutés plus tard comme helpers séparés sans casser celui-ci.

## Options supportées

Le tableau `$config` est indexé par les constantes de [`RateLimitOption`](../../src/oihana/middleware/enums/RateLimitOption.php).

| Option | Type | Défaut | Effet |
| :--- | :--- | :--- | :--- |
| `LIMIT` | `int` | `100` | Nombre maximum de requêtes par fenêtre. Valeur non positive ⇒ retombe sur le défaut. |
| `WINDOW` | `int` (secondes) | `60` | Largeur de la fenêtre de rate-limit. Valeur non positive ⇒ retombe sur le défaut. |
| `KEY` | `string\|callable\|null` | `null` | Identifiant sur lequel le compteur est indexé (cf. ci-dessous). |
| `KEY_PREFIX` | `string` | `'ratelimit'` | Préfixe ajouté à chaque clé de stockage. Utile pour isoler plusieurs limiteurs partageant le même backend. |
| `SCOPE` | `string\|null` | `null` | Segment optionnel inséré entre le préfixe et la clé (ex. `'auth'`, `'write'`, `'read'`). |
| `NOW` | `int\|null` | `time()` | Injection d'horloge pour tests déterministes. |

### Résolution de `KEY`

| Forme | Effet |
| :--- | :--- |
| `string` (non vide) | Utilisée telle quelle. |
| `callable` `fn(ServerRequestInterface): string` | Invoquée à chaque appel — permet de hasher un email, retourner un service `_key`, dériver d'un claim JWT, etc. Un retour vide retombe sur le sentinel `'unknown'`. |
| `null` / omise | Retombe sur l'IP client résolue via [`oihana\http\helpers\ips\getClientIp()`](../../../oihana-php-http/src/oihana/http/helpers/ips/getClientIp.php). Si aucune adresse exploitable n'est trouvée, le sentinel `'unknown'` est utilisé — le helper ne dégrade jamais silencieusement vers "pas de clé, pas de quota". |

La clé de stockage finale est `"{KEY_PREFIX}:{SCOPE?}:{identity}:{windowStart}"`.

## `RateLimitDecision`

Retourné par `enforceRateLimit()`. Value object readonly — cf. [`RateLimitDecision`](../../src/oihana/middleware/rateLimit/RateLimitDecision.php).

| Propriété | Type | Signification |
| :--- | :--- | :--- |
| `$allowed` | `bool` | `true` quand la requête tient dans le quota, `false` quand le compteur a débordé. |
| `$limit` | `int` | Quota en vigueur pour la fenêtre (copie verbatim de `LIMIT`). |
| `$remaining` | `int` | Requêtes encore autorisées avant le reset. Clampé à `0` une fois le quota épuisé. |
| `$reset` | `int` | Timestamp Unix absolu de fin de fenêtre. |
| `$retryAfter` | `int` | Secondes jusqu'au `$reset`, ou `0` si `$allowed`. Adapté comme valeur de `Retry-After` sur un `429`. |

## `withRateLimitHeaders()` — familles d'en-têtes

Deux familles d'en-têtes sont supportées. Bascule via le flag `$rfc9421` :

| Flag | Famille | En-têtes émis |
| :--- | :--- | :--- |
| `false` (défaut) | Legacy de-facto | `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` |
| `true` | Draft IETF (RFC 9421) | `RateLimit-Limit`, `RateLimit-Remaining`, `RateLimit-Reset` |

`Retry-After` est émis sur chaque réponse `!$decision->allowed` dans les deux familles.

## Utilisation

```php
use function oihana\middleware\helpers\rateLimit\enforceRateLimit ;
use function oihana\middleware\helpers\rateLimit\withRateLimitHeaders ;

use oihana\middleware\enums\RateLimitOption ;
use oihana\middleware\rateLimit\InMemoryRateLimitStore ;

$store = new InMemoryRateLimitStore() ;

$decision = enforceRateLimit( $request , $store ,
[
    RateLimitOption::LIMIT  => 10 ,
    RateLimitOption::WINDOW => 60 ,
    RateLimitOption::SCOPE  => 'auth' ,
]) ;

if ( !$decision->allowed )
{
    $response = $responseFactory->createResponse( 429 ) ;
    $response->getBody()->write( '{"error":"too many requests"}' ) ;
    return withRateLimitHeaders( $response , $decision )
        ->withHeader( 'Content-Type' , 'application/json' ) ;
}

$response = $handler->handle( $request ) ;

return withRateLimitHeaders( $response , $decision ) ;
```

## Recette complète : middleware Slim avec résolution explicite de clé

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Message\ResponseFactoryInterface ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use oihana\middleware\enums\RateLimitOption ;
use oihana\middleware\rateLimit\RateLimitStore ;

use function oihana\middleware\helpers\rateLimit\enforceRateLimit ;
use function oihana\middleware\helpers\rateLimit\withRateLimitHeaders ;

class AuthRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct
    (
        private readonly RateLimitStore           $store           ,
        private readonly ResponseFactoryInterface $responseFactory ,
        private readonly int                      $limit  = 10 ,
        private readonly int                      $window = 60 ,
    ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        $decision = enforceRateLimit( $request , $this->store ,
        [
            RateLimitOption::LIMIT  => $this->limit  ,
            RateLimitOption::WINDOW => $this->window ,
            RateLimitOption::SCOPE  => 'auth' ,
        ]) ;

        if ( !$decision->allowed )
        {
            $response = $this->responseFactory->createResponse( 429 ) ;
            $response->getBody()->write( '{"error":"too many requests"}' ) ;
            return withRateLimitHeaders( $response , $decision )
                ->withHeader( 'Content-Type' , 'application/json' ) ;
        }

        return withRateLimitHeaders( $handler->handle( $request ) , $decision ) ;
    }
}
```

**Points clés de câblage :**

- **Placer avant la logique métier et après request-id / tracing** — pour que les réponses bloquées portent quand même leur ID de corrélation.
- **Externaliser le store** — partager une instance unique dans toute l'app pour que les compteurs par clé s'accumulent correctement.
- **Un middleware par scope** — auth, write, read, etc. Chaque instance porte son triplet `LIMIT` / `WINDOW` / `SCOPE`. Le store partagé garde les compteurs ségrégés par `SCOPE`.

## Choisir un store

| Store | Où il vit | À utiliser quand… |
| :--- | :--- | :--- |
| [`InMemoryRateLimitStore`](../../src/oihana/middleware/rateLimit/InMemoryRateLimitStore.php) | Ce paquet | Tests unitaires, scripts CLI, outils mono-process, démos. **Pas pour du trafic HTTP multi-worker** — chaque worker garderait ses propres compteurs. |
| `MemcachedRateLimitStore` | [`oihana/php-memcached`](https://github.com/BcommeBois/oihana-php-memcached) (paquet séparé) | Trafic HTTP de production. Partagé entre tous les workers / nœuds via Memcached. |
| Personnalisé (Redis, APCu, …) | Ton projet | Tout backend qui expose une primitive atomique d'incrément avec TTL. Implémenter l'interface [`RateLimitStore`](../../src/oihana/middleware/rateLimit/RateLimitStore.php). |

## Hors scope

Ce helper se limite à **l'application d'un quota à fenêtre fixe sur un seul compteur par requête**. Il ne fait PAS :

- **La résolution de règle depuis la requête** — choisir `auth` vs `write` vs `read` selon path/méthode est la responsabilité de ton middleware.
- **La combinaison de plusieurs compteurs en un appel** — si tu as besoin de quotas par-IP ET par-email sur le même endpoint, appelle `enforceRateLimit()` deux fois avec deux scopes / deux clés et court-circuite sur l'un ou l'autre échec.
- **Le décodage de JWT ou les requêtes en base** — le hook `KEY` callable te laisse le faire toi-même.
- **La construction du body 429** — c'est une préoccupation applicative (négociation de contenu, problem-details JSON, etc.).
- **Token bucket / sliding window** — hors scope pour la v0.3, cf. section "Algorithme".

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [Request ID](request-id.md) — propager un ID de trace même sur les réponses 429 pour la traçabilité support.
- [Mode maintenance](maintenance.md) — helper voisin pour les réponses 503 propres.
- [Draft IETF RFC 9421](https://datatracker.ietf.org/doc/html/draft-ietf-httpapi-ratelimit-headers) — "RateLimit Header Fields for HTTP".
- [`oihana/php-memcached`](https://github.com/BcommeBois/oihana-php-memcached) — `RateLimitStore` production-grade backé par Memcached.
