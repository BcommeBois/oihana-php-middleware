# Requêtes conditionnelles (304 Not Modified)

## Pourquoi tu en aurais besoin — un scénario concret

La page d'accueil de ton blog est à `/`. 1000 visiteurs y passent chaque heure. Sans requêtes conditionnelles :

- Chaque requête fait tourner ta query CMS, ton moteur de templates, ton pipeline d'assets.
- 1000 × 80 ms de temps serveur = 80 secondes de CPU par heure, pour du contenu qui n'a pas changé.
- Les 250 Ko complets du body HTML sont envoyés 1000 fois sur le réseau.

Un utilisateur revient sur la page 3 minutes après sa première visite. La page n'a pas changé. Son navigateur a encore la réponse précédente en cache disque, mais sans metadata de fraîcheur il ne sait pas si la copie cachée est toujours valide. Il re-demande toute la page. 250 Ko de bande passante grillés. L'utilisateur attend encore 400 ms.

**Avec ETag + GET conditionnel**, la première réponse porte :

```
HTTP/1.1 200 OK
ETag: "v42-2026052810"
Content-Length: 254312

<HTML complet>
```

Le navigateur stocke à la fois le body ET l'`ETag`. Quand l'utilisateur revient, le navigateur redemande — mais cette fois avec une précondition :

```
GET / HTTP/1.1
If-None-Match: "v42-2026052810"
```

Ton serveur vérifie l'ETag courant de la ressource. Même valeur → le body caché est toujours frais. Réponse :

```
HTTP/1.1 304 Not Modified
ETag: "v42-2026052810"
```

**Pas de body. 50 octets sur le réseau au lieu de 254 Ko.** Le navigateur sort sa copie du disque, rend instantanément.

Multiplie ça par tous tes visiteurs, tous tes endpoints. Les économies de CPU et de bande passante sur du trafic lecture-lourd sont énormes — c'est pour ça que les CDN construisent tout leur business sur ce mécanisme.

Le piège : tu dois quand même calculer l'ETag (ou la date `Last-Modified`) pour comparer. C'est moins cher que reconstruire le body — typiquement un hash de la dernière mise à jour DB, une cache key, un compteur de version. Mais ce n'est pas gratuit.

Quand ce n'est **pas** utile : pour les endpoints où calculer l'ETag coûte presque autant que servir le body (par ex. ETag = hash du body lui-même pour un petit objet JSON). Tout l'intérêt est de court-circuiter AVANT le travail coûteux.

---

`oihana/php-middleware` fournit deux helpers procéduraux couplés pour le pattern GET conditionnel :

```php
namespace oihana\middleware\helpers\cache ;

function isNotModified(
    ServerRequestInterface $request ,
    string                 $etag ,
    ?DateTimeInterface     $lastModified = null ,
) : bool ;

function respondNotModified( ResponseInterface $response , string $etag ) : ResponseInterface ;
```

`isNotModified()` évalue les préconditions de la requête ; `respondNotModified()` construit la réponse 304 canonique.

## Évaluation des préconditions

Per [RFC 9110 §13.1.3](https://www.rfc-editor.org/rfc/rfc9110#section-13.1.3) :

1. Si la requête porte un en-tête `If-None-Match`, **il prend la précédence** — `If-Modified-Since` est ignoré.
2. Sinon, si la requête porte un `If-Modified-Since` ET qu'un `$lastModified` est fourni, les dates sont comparées.

### Sémantique `If-None-Match`

| `If-None-Match` entrant | Condition de match |
| :--- | :--- |
| `*` | Toujours `true` — la ressource existe, le wildcard est satisfait. |
| `"v42"` | `true` quand la valeur entrante matche `$etag` (comparaison faible — préfixe `W/` strippé des deux côtés). |
| `"v40", "v41", "v42"` | `true` quand UNE entrée matche `$etag` (comparaison faible). |
| `W/"v42"` (faible) | Matche `"v42"` (fort) sous comparaison faible — le préfixe est strippé avant comparaison. |

La comparaison faible est la règle pour `If-None-Match` per RFC 9110 §8.8.3.2 ; la comparaison forte est pour `If-Match` / `If-Range` (hors scope pour ce helper).

### Sémantique `If-Modified-Since`

| Condition | Résultat |
| :--- | :--- |
| `$lastModified` est `null` | `false` (pas de date de référence pour comparer) |
| En-tête `If-Modified-Since` malformé (pas une HTTP-date valide) | `false` (défensif — on ne peut pas faire confiance à la date) |
| `$lastModified->getTimestamp() <= timestamp If-Modified-Since` | `true` (la ressource n'a pas été modifiée depuis le dernier fetch du client) |
| `$lastModified` plus récent que `If-Modified-Since` | `false` |

Le parsing de la HTTP-date est délégué à [`oihana\http\helpers\dates\parseHttpDate()`](https://github.com/BcommeBois/oihana-php-http) qui gère les trois formats RFC 9110 §5.6.7 (IMF-fixdate, RFC 850, asctime).

## Forme de la réponse

`respondNotModified()` produit une réponse qui suit [RFC 9110 §15.4.5](https://www.rfc-editor.org/rfc/rfc9110#status.304) :

- Statut `304 Not Modified`.
- En-tête `ETag` stampé (même valeur que celle que l'appelant mettrait sur un `200`).
- Body vide.
- Les autres en-têtes mises à jour-pertinentes (`Cache-Control`, `Vary`, `Date`) NE sont PAS stampées par le helper — l'appelant les ajoute via la chaîne `withHeader()` standard avant ou après l'appel au helper.

## Utilisation

```php
use DateTimeImmutable ;

use function oihana\middleware\helpers\cache\isNotModified ;
use function oihana\middleware\helpers\cache\respondNotModified ;

$etag         = '"v42-' . $resource->updatedAt->format( 'YmdHis' ) . '"' ;
$lastModified = $resource->updatedAt ;

if ( isNotModified( $request , $etag , $lastModified ) )
{
    return respondNotModified( $responseFactory->createResponse() , $etag ) ;
}

// Construis la réponse complète ; stamp ETag + Last-Modified pour le prochain GET conditionnel.
$body = $template->render( $resource ) ;

return $response
    ->withHeader( 'ETag'          , $etag )
    ->withHeader( 'Last-Modified' , $lastModified->format( DATE_RFC7231 ) )
    ->withHeader( 'Cache-Control' , 'public, max-age=300' )
    ->withBody  ( $bodyFactory->createStream( $body ) ) ;
```

## Recette complète : middleware ETag qui enveloppe un handler coûteux

```php
use DateTimeImmutable ;
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Message\ResponseFactoryInterface ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use function oihana\middleware\helpers\cache\isNotModified ;
use function oihana\middleware\helpers\cache\respondNotModified ;

class EtagMiddleware implements MiddlewareInterface
{
    public function __construct
    (
        private readonly EtagResolver             $resolver ,        // ton app — retourne ['etag' => ..., 'lastModified' => DateTime] pour une URL
        private readonly ResponseFactoryInterface $responseFactory ,
    ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        $resolved = $this->resolver->resolve( (string) $request->getUri() ) ;

        if ( $resolved === null )
        {
            // Pas d'etag calculable — passe à travers.
            return $handler->handle( $request ) ;
        }

        [ 'etag' => $etag , 'lastModified' => $lastModified ] = $resolved ;

        if ( isNotModified( $request , $etag , $lastModified ) )
        {
            return respondNotModified( $this->responseFactory->createResponse() , $etag ) ;
        }

        $response = $handler->handle( $request ) ;

        return $response
            ->withHeader( 'ETag'          , $etag )
            ->withHeader( 'Last-Modified' , $lastModified->format( DATE_RFC7231 ) ) ;
    }
}
```

**Points clés de câblage :**

- **Calcule l'ETag depuis des signaux pas chers** : l'`updated_at` d'une ligne, un compteur de version, une clé de cache. PAS un hash du body complet de la réponse — ça annulerait l'intérêt.
- **Apparier avec `Cache-Control: max-age=...`** : `max-age` contrôle la fraîcheur ; `ETag` est le token de revalidation quand la fraîcheur expire. Ils sont complémentaires, pas alternatifs.
- **Utiliser des etags faibles (`W/"..."`)** quand le body peut avoir des variations sémantiquement équivalentes (whitespace, ordre d'en-têtes). Utiliser des etags forts uniquement quand un contenu byte-identique importe (range requests).

## Hors scope

Ce helper couvre le **GET conditionnel côté lecture**. Il ne fait PAS :

- **Évaluer `If-Match` ou `If-Unmodified-Since`** — ce sont pour le contrôle de concurrence optimiste côté écriture (`PUT`/`PATCH` avec « ne pas écraser si changé »). La sémantique est similaire mais inversée ; si tu en as besoin, demande et on pourra ajouter `respondPreconditionFailed()` dans un lot futur.
- **Calculer l'ETag pour toi** — tu le fournis ; le helper compare. Le calcul de l'ETag est applicatif.
- **Gérer `If-Range`** — les range requests avec préconditions conditionnelles sont une niche. Hors scope.
- **Envoyer 304 pour des méthodes non-GET** — la RFC 9110 réserve `304` pour GET/HEAD. Pour PUT/PATCH/DELETE, une précondition échouée est `412 Precondition Failed`.

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [Cache-Control](cache-control.md) — helper sœur pour le côté fraîcheur du caching HTTP.
- [RFC 9110 §13.1](https://www.rfc-editor.org/rfc/rfc9110#section-13.1) — sémantique des requêtes conditionnelles.
- [RFC 9110 §8.8.3](https://www.rfc-editor.org/rfc/rfc9110#section-8.8.3) — comparaison faible vs forte des etags.
