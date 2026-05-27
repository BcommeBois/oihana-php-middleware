# Mode maintenance

`oihana/php-middleware` fournit un helper procédural pour répondre proprement quand ton application est en maintenance :

```php
namespace oihana\middleware\helpers\maintenance ;

function respondMaintenanceMode( ResponseInterface $response , array $options = [] ) : ResponseInterface ;
```

Transforme une réponse PSR-7 en `503 Service Unavailable` propre, avec un en-tête `Retry-After` optionnel et un body optionnel. Le helper n'a aucune opinion sur **comment** ton app détecte qu'elle est en maintenance — il s'occupe uniquement de **répondre** correctement quand c'est le cas.

## Options supportées

Le `$options` est un tableau associatif keyed par les constantes de l'enum [`MaintenanceOption`](../../src/oihana/middleware/enums/MaintenanceOption.php).

| Option | Type | Effet |
| :--- | :--- | :--- |
| `RETRY_AFTER` | `int\|DateTimeInterface\|string\|null` | Valeur de `Retry-After`. Trois formes acceptées (cf. ci-dessous). Omis / `null` / invalide ⇒ pas d'en-tête. |
| `MESSAGE` | `string\|null` | Body de la réponse. Omis, `null` ou chaîne vide ⇒ pas de body. |
| `CONTENT_TYPE` | `string` (default `'text/plain; charset=utf-8'`) | `Content-Type` du body. Émis uniquement quand `MESSAGE` est fourni. |

### Formes de `Retry-After`

| Type | Exemple | Effet |
| :--- | :--- | :--- |
| `int` | `120` | `Retry-After: 120` (delta-seconds, RFC 7231 §7.1.3 forme 1). Doit être > 0. |
| `DateTimeInterface` | `new DateTimeImmutable( '+30 minutes' )` | Formaté en IMF-fixdate via `oihana\http\helpers\dates\formatHttpDate()` (forme 2 RFC 7231). |
| `string` | `'Wed, 21 Oct 2026 07:28:00 GMT'` | Passé tel quel — le caller gère le format. |

## Usage

```php
use function oihana\middleware\helpers\maintenance\respondMaintenanceMode ;
use oihana\middleware\enums\MaintenanceOption ;

// Cas 1 : delta-seconds simple avec body texte
$response = respondMaintenanceMode( $response ,
[
    MaintenanceOption::RETRY_AFTER => 120 ,
    MaintenanceOption::MESSAGE     => 'Service en maintenance planifiée, retour dans 2 minutes.' ,
]) ;

// Cas 2 : body JSON
$response = respondMaintenanceMode( $response ,
[
    MaintenanceOption::RETRY_AFTER  => 600 ,
    MaintenanceOption::MESSAGE      => json_encode( [ 'status' => 'maintenance' , 'eta' => 600 ] ) ,
    MaintenanceOption::CONTENT_TYPE => 'application/json' ,
]) ;

// Cas 3 : Retry-After absolu (HTTP-date), sans body
$response = respondMaintenanceMode( $response ,
[
    MaintenanceOption::RETRY_AFTER => new DateTimeImmutable( '+30 minutes' ) ,
]) ;
```

## Recipe complète : middleware Slim avec toggle env

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;
use Slim\Psr7\Response ;

use function oihana\middleware\helpers\maintenance\respondMaintenanceMode ;
use oihana\middleware\enums\MaintenanceOption ;

class MaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct
    (
        private readonly bool   $enabled    ,
        private readonly int    $retryAfter = 300 ,
        private readonly string $message    = 'Service is undergoing scheduled maintenance.' ,
    ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        if ( !$this->enabled )
        {
            return $handler->handle( $request ) ;
        }

        return respondMaintenanceMode( new Response() ,
        [
            MaintenanceOption::RETRY_AFTER => $this->retryAfter ,
            MaintenanceOption::MESSAGE     => $this->message ,
        ]) ;
    }
}

// Câblage
$app->add( new MaintenanceMiddleware
(
    enabled    : (bool) ( $_ENV[ 'APP_MAINTENANCE' ] ?? false ) ,
    retryAfter : 600 ,
    message    : 'Back at 14:00 UTC.' ,
)) ;
```

**Points-clé du wiring** :

- **Place en tête de pile** — pour court-circuiter toute la chaîne (auth, business logic, etc.) quand le mode maintenance est actif.
- **Toggle externalisé** (env var, feature flag, fichier sentinelle, etc.) — le helper ne décide pas du toggle, c'est le rôle du middleware.
- **Bypass possible** — si tu as une API d'admin qui doit rester accessible pour terminer la maintenance, ajouter une condition d'allowlist sur le path ou l'IP avant l'appel à `respondMaintenanceMode`.

## Hors scope

Ce helper se limite à **construire la réponse 503**. Il ne s'occupe PAS de :

- **Détecter le mode maintenance** — c'est le rôle du middleware (env var, feature flag, fichier sentinelle, table SQL, …).
- **Bypass admin** — si certaines routes doivent rester accessibles, c'est au middleware de filtrer avant l'appel.
- **Page HTML personnalisée** — le helper produit du texte / JSON par défaut. Pour une page HTML stylée, fournir le HTML via `MESSAGE` et `CONTENT_TYPE: 'text/html; charset=utf-8'`.

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [Request ID](request-id.md) — pour propager un ID de trace même pendant la maintenance.
- [Spec RFC 7231 §7.1.3](https://datatracker.ietf.org/doc/html/rfc7231#section-7.1.3) — sémantique de `Retry-After`.
