# CORS

## Pourquoi tu en aurais besoin — un scénario concret

Tu construis un frontend React à `https://app.example.com` qui appelle ton API à `https://api.example.com/users`. Ton API marche parfaitement dans Postman, retourne du JSON propre. Tu appelles la même URL depuis ton frontend, la requête part, et la console DevTools du navigateur crie :

```
Access to fetch at 'https://api.example.com/users' from origin
'https://app.example.com' has been blocked by CORS policy:
No 'Access-Control-Allow-Origin' header is present on the requested resource.
```

La réponse est bien arrivée du serveur. C'est le **navigateur** qui refuse de la donner à ton JavaScript, parce que l'API et le frontend vivent sur des origines différentes et que le serveur n'a pas explicitement dit « cette origine est autorisée ». CORS est le système de permission du navigateur pour les lectures cross-origin — sans les bons en-têtes de réponse, ton API est invisible à ta propre SPA.

Ça devient encore plus subtil pour les requêtes « non simples » (tout ce qui n'est pas un `GET` / `POST` basique avec des en-têtes simples — donc en pratique chaque appel d'API moderne avec `Authorization`, body JSON, en-têtes custom). Le navigateur envoie d'abord un `OPTIONS` **preflight** pour demander au serveur quelles méthodes et quels en-têtes sont autorisés. Si le preflight échoue ou retourne les mauvais en-têtes, ta vraie requête ne part même pas.

Et il y a un piège classique : mettre `Access-Control-Allow-Origin: *` avec des credentials (cookies, en-têtes d'auth) — les navigateurs **rejettent ce combo silencieusement**. Ton API a l'air configurée, tes DevTools montrent les en-têtes, et pourtant la requête échoue toujours.

`applyCorsHeaders()` gère la mécanique allowlist + preflight + `Vary: Origin`, et lève une exception au démarrage si tu tombes dans le piège `*` + credentials.

---

`oihana/php-middleware` fournit un helper procédural unique pour gérer le CORS de bout en bout :

```php
namespace oihana\middleware\helpers\cors ;

function applyCorsHeaders( ServerRequestInterface $request , ResponseInterface $response , array $options = [] ) : ResponseInterface ;
```

Compatible PSR-7 immutable : retourne une **nouvelle** `ResponseInterface`, l'instance fournie n'est jamais mutée. **Le status code n'est pas touché** — c'est au middleware appelant de décider s'il faut court-circuiter un preflight avec un 204 ou continuer vers le handler suivant.

## Options supportées

Le `$options` est un tableau associatif keyed par les constantes de l'enum [`CorsOption`](../../src/oihana/middleware/enums/CorsOption.php).

| Option | Type | Effet |
| :--- | :--- | :--- |
| `ALLOWED_ORIGINS` | `list<string>\|'*'\|null` | Liste explicite d'origines autorisées, ou wildcard `'*'`, ou `null` pour ne rien faire. |
| `ALLOWED_METHODS` | `list<string>` | Méthodes émises dans `Access-Control-Allow-Methods` (preflight uniquement). |
| `ALLOWED_HEADERS` | `list<string>` | En-têtes émis dans `Access-Control-Allow-Headers` (preflight). Si omis, le helper echo le contenu de `Access-Control-Request-Headers`. |
| `EXPOSED_HEADERS` | `list<string>` | En-têtes exposés au JS via `Access-Control-Expose-Headers`. Émis sur toute requête CORS allowed-origin. |
| `ALLOW_CREDENTIALS` | `bool` (default `false`) | Émet `Access-Control-Allow-Credentials: true` quand `true`. |
| `MAX_AGE` | `int` | TTL du preflight cache, en secondes. Preflight uniquement. |

## Algorithme

1. **Détection d'une requête CORS.** Si la requête n'a pas d'en-tête `Origin`, ce n'est pas une requête CORS — la réponse est retournée inchangée.

2. **Résolution de `Access-Control-Allow-Origin`** :
   - `ALLOWED_ORIGINS: '*'` ⇒ `Allow-Origin: *`, pas de `Vary`. **Lève `InvalidArgumentException` si combiné avec `ALLOW_CREDENTIALS: true`** — les navigateurs rejettent cette combinaison.
   - `ALLOWED_ORIGINS: list<string>` et `Origin` du request dans la liste ⇒ `Allow-Origin: <origin>` + `Vary: Origin` (ajouté de façon idempotente : pas de duplication si déjà présent).
   - Sinon (allowlist absente, origin pas dans la liste) ⇒ réponse retournée inchangée. Le caller décide du 403 / 200 séparément.

3. **`Access-Control-Allow-Credentials: true`** émis si `ALLOW_CREDENTIALS: true` et l'origin est autorisée.

4. **`Access-Control-Expose-Headers`** émis si `EXPOSED_HEADERS` non vide. Joint par `', '`.

5. **Détection du preflight** : méthode `OPTIONS` ET en-tête `Access-Control-Request-Method` non vide.
   - `Access-Control-Allow-Methods` émis si `ALLOWED_METHODS` non vide.
   - `Access-Control-Allow-Headers` émis : si `ALLOWED_HEADERS` non vide, joint par `', '`. Sinon, echo le contenu de `Access-Control-Request-Headers` (si présent).
   - `Access-Control-Max-Age` émis si `MAX_AGE` est un `int > 0`.

## Exemples

### Allowlist explicite avec credentials

```php
use function oihana\middleware\helpers\cors\applyCorsHeaders ;
use oihana\middleware\enums\CorsOption ;

$response = applyCorsHeaders( $request , $response ,
[
    CorsOption::ALLOWED_ORIGINS   => [ 'https://app.example.com' , 'https://admin.example.com' ] ,
    CorsOption::ALLOWED_METHODS   => [ 'GET' , 'POST' , 'DELETE' ] ,
    CorsOption::ALLOWED_HEADERS   => [ 'Authorization' , 'Content-Type' ] ,
    CorsOption::EXPOSED_HEADERS   => [ 'X-Request-Id' ] ,
    CorsOption::ALLOW_CREDENTIALS => true ,
    CorsOption::MAX_AGE           => 3600 ,
]) ;
```

Sur un preflight `OPTIONS` venant de `https://app.example.com` :

```
Access-Control-Allow-Origin: https://app.example.com
Vary: Origin
Access-Control-Allow-Credentials: true
Access-Control-Expose-Headers: X-Request-Id
Access-Control-Allow-Methods: GET, POST, DELETE
Access-Control-Allow-Headers: Authorization, Content-Type
Access-Control-Max-Age: 3600
```

### API publique sans credentials

```php
$response = applyCorsHeaders( $request , $response ,
[
    CorsOption::ALLOWED_ORIGINS => '*' ,
    CorsOption::ALLOWED_METHODS => [ 'GET' ] ,
]) ;
```

Émet `Access-Control-Allow-Origin: *` sans `Vary`. Configuration recommandée pour une API publique en lecture seule.

### Câblage en middleware Slim

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;
use Slim\Psr7\Response ;

use function oihana\middleware\helpers\cors\applyCorsHeaders ;
use oihana\middleware\enums\CorsOption ;
use oihana\enums\http\HttpMethod ;
use oihana\enums\http\HttpStatusCode ;

class CorsMiddleware implements MiddlewareInterface
{
    public function __construct( private readonly array $options ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        // Short-circuit preflight with 204
        if ( $request->getMethod() === HttpMethod::OPTIONS )
        {
            $response = new Response( HttpStatusCode::NO_CONTENT ) ;
        }
        else
        {
            $response = $handler->handle( $request ) ;
        }

        return applyCorsHeaders( $request , $response , $this->options ) ;
    }
}
```

> ⚠️ Dans un projet Slim PHP par exemple, placez ce middleware **après** `$app->addRoutingMiddleware()` (et avant `ErrorMiddleware`). Sinon Slim répond `405 Method Not Allowed` aux preflights `OPTIONS` avant que le middleware ne puisse renvoyer `204`, et CORS casse silencieusement sur les routes qui ne déclarent pas `OPTIONS`.

## Prédicats CORS

Deux petits prédicats permettent à un middleware de décider si la branche CORS est pertinente pour une requête donnée, sans écrire les noms d'en-têtes à la main.

```php
namespace oihana\middleware\helpers\cors ;

function isCorsRequest  ( ServerRequestInterface $request ) : bool ;
function isCorsPreflight( ServerRequestInterface $request ) : bool ;
```

| Helper | Retourne `true` quand… |
| :--- | :--- |
| `isCorsRequest()` | La requête porte un en-tête `Origin`. |
| `isCorsPreflight()` | La méthode de la requête est `OPTIONS` ET la requête porte un en-tête `Access-Control-Request-Method`. |

Note : un `OPTIONS` seul (sans `Access-Control-Request-Method`) n'est **pas** un preflight — ça peut être une requête de découverte de route ou une sonde d'info serveur. `isCorsPreflight()` retourne `false` dans ce cas pour que le middleware passe la main au handler normal.

```php
use function oihana\middleware\helpers\cors\applyCorsHeaders ;
use function oihana\middleware\helpers\cors\isCorsPreflight ;
use function oihana\middleware\helpers\cors\isCorsRequest ;

if ( isCorsPreflight( $request ) )
{
    return applyCorsHeaders( $request , $responseFactory->createResponse( 204 ) , $options ) ;
}

$response = $handler->handle( $request ) ;

if ( isCorsRequest( $request ) )
{
    $response = applyCorsHeaders( $request , $response , $options ) ;
}

return $response ;
```

## Pièges classiques évités par le helper

- **`*` + credentials** : interdit par la spec, navigateurs rejettent. Le helper lève une exception au lieu de pousser une réponse invalide silencieusement.
- **Wildcard naïf** : avec une allowlist explicite, l'helper echo l'`Origin` du request, ne sert pas un wildcard fragile.
- **`Vary: Origin` oublié** : sans cet en-tête, les CDN / caches partagés peuvent servir une réponse CORS à une mauvaise origine. Le helper l'ajoute automatiquement quand l'allowlist est dynamique.
- **Duplication de `Vary: Origin`** : si la réponse contient déjà `Vary: Origin`, le helper ne l'ajoute pas une seconde fois.

## Voir aussi

- [Démarrage](getting-started.md) — câblage dans un middleware PSR-15.
- [En-têtes de sécurité](security-headers.md) — l'autre famille de helpers du paquet.
- [Spec Fetch — CORS protocol](https://fetch.spec.whatwg.org/#http-cors-protocol) — référence officielle (CORS est défini par le Fetch standard, pas par une RFC dédiée).
