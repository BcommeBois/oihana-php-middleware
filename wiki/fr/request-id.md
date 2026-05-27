# Request ID — corrélation de traces

`oihana/php-middleware` fournit deux helpers procéduraux pour mettre en place une propagation propre du **request ID** sur ton API :

- [`requestIdFromRequest()`](#requestidfromrequest) — lit (ou génère) l'ID porté par un `X-Request-Id` entrant.
- [`withRequestIdHeader()`](#withrequestidheader) — pose l'ID sur la réponse.

Plus un enum [`RequestIdField`](#requestidfield) pour les noms conventionnels.

Le but : assigner à chaque requête un identifiant unique court (~128 bits), le propager dans tous les logs côté serveur, et le renvoyer au client via un en-tête `X-Request-Id`. Quand un utilisateur signale un bug, il peut donner cet ID au support, qui retrouve toute la trace côté serveur instantanément.

## `requestIdFromRequest()`

```php
namespace oihana\middleware\helpers\requestId ;

function requestIdFromRequest( ServerRequestInterface $request , string $headerName = 'X-Request-Id' ) : string ;
```

Stratégie en deux temps :

1. **Si la requête porte déjà un `X-Request-Id`** (typiquement forwardé par un load balancer, une API gateway, ou un service appelant), le helper le réutilise — **à condition** qu'il passe une validation de forme conservative : 1 à 128 caractères, restreint à l'alphabet URL-safe `[A-Za-z0-9_-]`.
2. **Sinon** (en-tête absent, vide, ou hors-alphabet), le helper génère un nouvel ID via `oihana\core\encoding\randomBase64Url()` (128 bits de CSPRNG, 22 caractères base64url).

### Pourquoi valider l'en-tête entrant

Un client peut envoyer n'importe quoi dans un `X-Request-Id` (les en-têtes HTTP sont contrôlés côté client). Sans validation, un attaquant pourrait :

- **Polluer les logs** en envoyant un ID excessivement long ou contenant des caractères qui cassent le format de log structuré.
- **Injecter des en-têtes** via CRLF si la lib PSR-7 utilisée est laxiste (Slim PSR-7 catche déjà cette attaque upstream, mais d'autres implémentations sont moins strictes — la défense en profondeur reste de mise).
- **Confondre la corrélation** en réutilisant un ID légitime existant (pour brouiller les pistes).

La validation `[A-Za-z0-9_-]{1,128}` couvre 100% des IDs légitimes (UUIDs, base64url, hex, slugs simples) tout en rejetant les payloads forgés.

### Usage

```php
use function oihana\middleware\helpers\requestId\requestIdFromRequest ;
use oihana\middleware\enums\RequestIdField ;

// Nom d'en-tête par défaut (X-Request-Id)
$id = requestIdFromRequest( $request ) ;

// Nom personnalisé (par exemple pour aligner avec un autre service)
$id = requestIdFromRequest( $request , 'X-Trace-Id' ) ;

// Avec la constante enum
$id = requestIdFromRequest( $request , RequestIdField::HEADER_NAME ) ;
```

## `withRequestIdHeader()`

```php
namespace oihana\middleware\helpers\requestId ;

function withRequestIdHeader( ResponseInterface $response , string $id , string $headerName = 'X-Request-Id' ) : ResponseInterface ;
```

Pose le request ID sur la réponse pour que les consommateurs downstream (devtools navigateur, agrégateur de logs, tickets de support, pipelines de tracing) puissent corréler la réponse avec la trace serveur.

Retourne une **nouvelle** `ResponseInterface` (PSR-7 immutable). N'importe quelle valeur précédente pour le même nom d'en-tête est remplacée.

### Usage

```php
use function oihana\middleware\helpers\requestId\withRequestIdHeader ;

$response = withRequestIdHeader( $response , $id ) ;
// => Response avec `X-Request-Id: <id>`
```

## `RequestIdField`

```php
namespace oihana\middleware\enums ;

class RequestIdField
{
    public const string HEADER_NAME    = 'X-Request-Id' ;
    public const string ATTRIBUTE_NAME = 'requestId' ;
}
```

- **`HEADER_NAME`** — nom d'en-tête conventionnel à utiliser des deux côtés (request entrant + response sortante).
- **`ATTRIBUTE_NAME`** — nom d'attribut PSR-7 conventionnel pour propager l'ID dans la chaîne de middlewares via `$request->withAttribute(...)`.

Conventions, pas obligations — tu peux utiliser tes propres noms si tu préfères.

## Recipe complète : middleware Slim

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use function oihana\middleware\helpers\requestId\requestIdFromRequest ;
use function oihana\middleware\helpers\requestId\withRequestIdHeader ;

use oihana\middleware\enums\RequestIdField ;

class RequestIdMiddleware implements MiddlewareInterface
{
    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        // 1. Lit ou génère
        $id = requestIdFromRequest( $request , RequestIdField::HEADER_NAME ) ;

        // 2. Propage dans la chaîne via un attribut PSR-7
        $request = $request->withAttribute( RequestIdField::ATTRIBUTE_NAME , $id ) ;

        // 3. Handle
        $response = $handler->handle( $request ) ;

        // 4. Stampe la réponse
        return withRequestIdHeader( $response , $id , RequestIdField::HEADER_NAME ) ;
    }
}
```

À placer **en tête de pile** des middlewares, pour que tous les autres (auth, audit, logging, error handler, etc.) puissent récupérer l'ID via `$request->getAttribute(RequestIdField::ATTRIBUTE_NAME)`.

### Côté logger

```php
$id = $request->getAttribute( RequestIdField::ATTRIBUTE_NAME ) ;
$logger->info( 'Customer fetched' , [ 'requestId' => $id , 'customerId' => $customerId ] ) ;
```

Tous les logs émis pour une requête donnée partagent ce `requestId`, ce qui rend la corrélation triviale dans n'importe quel agrégateur (ELK, Loki, Datadog, Splunk…).

## Pour aller plus loin : tracing distribué

Le request ID est la brique de base. Pour un système réparti, voir le **tracing W3C** (`traceparent` / `tracestate`) qui pousse plus loin la corrélation entre services. Helper prévu dans une release ultérieure de `oihana/php-middleware`.

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [En-têtes de sécurité](security-headers.md) — pour combiner avec HSTS / CSP.
- [CSRF](csrf.md) — pour la protection cross-site.
