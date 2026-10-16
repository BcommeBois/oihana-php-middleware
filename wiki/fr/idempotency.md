# Clés d'idempotence (`Idempotency-Key`)

Une requête est **idempotente** quand la recevoir deux fois produit le même effet que la recevoir une fois. `GET` l'est par nature ; un `POST` qui crée une commande ne l'est pas. L'en-tête `Idempotency-Key` le rend idempotent : le client tire un identifiant unique par action (un UUID), l'envoie à **chaque tentative**, et le serveur répond à une tentative répétée **ce qu'il a répondu la première fois**, sans refaire l'action.

## Pourquoi tu en aurais besoin — un scénario concret

**La réponse perdue.** Un vendeur crée une commande depuis une tablette, dans un entrepôt mal couvert. Ton API reçoit la requête, enregistre la commande, prépare le `201`… et la connexion tombe avant que la réponse n'arrive. Pour la tablette, rien ne s'est passé : elle réessaie trente secondes plus tard. Sans idempotence, ton API enregistre **une deuxième commande**, identique. Plus le réseau est mauvais, plus il y a de doublons — et personne n'est averti.

Avec une clé d'idempotence, la deuxième tentative porte la même clé que la première :

```
HTTP/1.1 201 Created
Content-Type: application/json
Location: /orders/42
Idempotent-Replayed: true

{"id":42}
```

La commande existe une fois ; la tablette reçoit sa clé, exactement comme si la première réponse était arrivée. `Idempotent-Replayed: true` lui dit que c'est un rejeu.

---

`oihana/php-middleware` fournit les pièces, sans imposer de cadre :

```php
namespace oihana\middleware\helpers\idempotency ;

function beginIdempotency( ServerRequestInterface $request , IdempotencyStore $store , array $options = [] ) : IdempotencyDecision ;

function completeIdempotency( IdempotencyDecision $decision , ResponseInterface $response , IdempotencyStore $store , array $options = [] ) : ResponseInterface ;

function respondIdempotentReplay( ResponseInterface $response , IdempotencyRecord $record ) : ResponseInterface ;

function idempotencyFingerprint( ServerRequestInterface $request ) : string ;

function isIdempotencyRecordable( int $status ) : bool ;
```

`beginIdempotency()` prend la **décision** ; `completeIdempotency()` garde la réponse une fois l'action faite ; c'est toi qui construis les réponses d'erreur — le corps et son format restent ceux de ton API. Le stockage est branchable via l'interface `IdempotencyStore` ; une implémentation en mémoire est fournie pour les essais.

## Le déroulé d'une requête

`beginIdempotency()` rend une [`IdempotencyDecision`](../../src/oihana/middleware/idempotency/IdempotencyDecision.php) dont `$outcome` vaut l'une des constantes de [`IdempotencyOutcome`](../../src/oihana/middleware/enums/IdempotencyOutcome.php) :

| Issue | Quand | Réponse typique |
| :--- | :--- | :--- |
| `SKIP` | méthode non gardée (`GET`, `HEAD`, `OPTIONS`…), ou pas d'en-tête alors que la clé est facultative | la requête passe ; rien n'est gardé |
| `MISSING` | pas d'en-tête alors que la clé est obligatoire (`REQUIRED`) | `400` |
| `INVALID` | en-tête vide, ou plus long que `MAX_KEY_LENGTH` — refusé plutôt qu'ignoré, sinon la requête passerait sans protection | `400` |
| `PROCEED` | la clé était libre : elle est maintenant **réservée** par cette requête | exécute l'action, puis `completeIdempotency()` |
| `MISMATCH` | la clé est connue, mais pour **un autre corps** — le client réutilise une clé pour une autre action | `422` |
| `REPLAY` | la clé est connue, même corps, réponse gardée | `respondIdempotentReplay()` — l'action ne s'exécute pas |
| `IN_PROGRESS` | la clé est connue, même corps, mais la première requête **tourne encore** (deux onglets, un réessai trop rapide) | `429` + `Retry-After: $decision->retryAfter` |

Puis, sur `PROCEED` :

- `completeIdempotency()` **garde** la réponse quand [`isIdempotencyRecordable()`](../../src/oihana/middleware/helpers/idempotency/isIdempotencyRecordable.php) l'accepte — les `2xx`, `3xx` et les `4xx` qui ne dépendent que de la requête (`400`, `404`, `409`, `412`, `422`…) — et **libère** la clé sinon : les `5xx`, `401`, `403`, `408`, `425`, `429` doivent pouvoir réussir au rejeu ;
- si l'action lève une exception, **libère la clé toi-même** (`$store->release( $decision->key )`) avant de la relancer ; sinon elle reste réservée jusqu'à la fin de son bail.

### Pourquoi `429` et pas `409` pour une clé en cours

Le brouillon IETF (`draft-ietf-httpapi-idempotency-key-header`) propose `409 Conflict` pour une clé en cours. Beaucoup de clients rangent pourtant tout `409` en « conflit à faire trancher par une personne » ; `429` + `Retry-After` est un « réessaie dans un instant » que tous les clients HTTP comprennent déjà. Le helper ne construit pas la réponse : choisis le statut qui convient à tes clients.

## La clé de stockage et l'empreinte

La clé de stockage est `"{KEY_PREFIX}:{sha256(scope, MÉTHODE, chemin, clé)}"` : deux comptes, deux méthodes ou deux chemins ne partagent jamais une clé.

🚨 **Passe le compte authentifié en `SCOPE`.** Sans lui, tous les appelants partagent les mêmes clés : quiconque lit une clé dans un journal pourrait rejouer la réponse d'un autre compte.

L'**empreinte** ([`idempotencyFingerprint()`](../../src/oihana/middleware/helpers/idempotency/idempotencyFingerprint.php)) est un SHA-256 :

- du **corps lu** (`getParsedBody()`), les clés de chaque objet triées récursivement — le même objet donne la même empreinte quel que soit l'ordre de ses membres ; à défaut, du **corps brut** ;
- de chaque **fichier téléversé** : le chemin du champ, le nom, le type et le SHA-256 de son contenu, lu par morceaux — aucun octet n'est gardé.

🚨 Un corps `multipart/form-data` n'est jamais pris brut : le client tire une nouvelle frontière à chaque tentative, les octets de deux rejeux identiques diffèrent.

## Options supportées

Le tableau `$options` est indexé par les constantes de [`IdempotencyOption`](../../src/oihana/middleware/enums/IdempotencyOption.php). Les options sont lues **à chaque appel** : tu peux les choisir par route, ou par application cliente.

| Option | Type | Défaut | Lue par | Effet |
| :--- | :--- | :--- | :--- | :--- |
| `SCOPE` | `string\|callable\|null` | `null` | `begin` | Le compte à qui appartient la clé. Un `callable` `fn(ServerRequestInterface): ?string` est appelé à chaque requête. |
| `REQUIRED` | `bool` | `false` | `begin` | `true` : une requête gardée sans en-tête est `MISSING` plutôt que `SKIP`. |
| `METHODS` | `list<string>` | `POST`, `PUT`, `PATCH`, `DELETE` | `begin` | Les méthodes gardées. |
| `MAX_KEY_LENGTH` | `int` | `255` | `begin` | La plus longue clé acceptée, en octets. |
| `LEASE` | `int` (secondes) | `120` | `begin` | Durée de la réservation d'une requête en cours. Passé ce délai, la réservation d'un processus mort est reprise par le rejeu suivant. Garde-la au-dessus de ta durée d'exécution maximale. |
| `RETRY_AFTER` | `int` (secondes) | `2` | `begin` | Le délai proposé sur `IN_PROGRESS`. |
| `KEY_PREFIX` | `string` | `'idempotency'` | `begin` | Préfixe de chaque clé de stockage. |
| `TTL` | `int` (secondes) | `604800` (7 jours) | `complete` | Durée de conservation d'une réponse. Elle doit couvrir le plus long délai entre une première tentative et son dernier rejeu — un appareil éteint un week-end compris. |
| `HEADERS` | `list<string>` | `Content-Type`, `Content-Location`, `Location`, `ETag`, `Last-Modified` | `complete` | Les en-têtes gardés avec la réponse. |

Une valeur de mauvais type, ou non positive, retombe sur le défaut.

## Recette complète : un middleware PSR-15

```php
use Throwable ;

use Psr\Http\Message\ResponseFactoryInterface ;
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use oihana\middleware\enums\IdempotencyOption ;
use oihana\middleware\enums\IdempotencyOutcome ;
use oihana\middleware\idempotency\IdempotencyStore ;

use function oihana\middleware\helpers\idempotency\beginIdempotency ;
use function oihana\middleware\helpers\idempotency\completeIdempotency ;
use function oihana\middleware\helpers\idempotency\respondIdempotentReplay ;

class IdempotencyMiddleware implements MiddlewareInterface
{
    public function __construct
    (
        private readonly IdempotencyStore         $store           ,
        private readonly ResponseFactoryInterface $responseFactory ,
    ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        $options = [ IdempotencyOption::SCOPE => $request->getAttribute( 'userId' ) ] ;

        $decision = beginIdempotency( $request , $this->store , $options ) ;

        switch ( $decision->outcome )
        {
            case IdempotencyOutcome::SKIP :
                return $handler->handle( $request ) ;

            case IdempotencyOutcome::REPLAY :
                return respondIdempotentReplay( $this->responseFactory->createResponse() , $decision->record ) ;

            case IdempotencyOutcome::IN_PROGRESS :
                return $this->responseFactory->createResponse( 429 )
                            ->withHeader( 'Retry-After' , (string) $decision->retryAfter ) ;

            case IdempotencyOutcome::MISMATCH :
                return $this->responseFactory->createResponse( 422 ) ;

            case IdempotencyOutcome::MISSING :
            case IdempotencyOutcome::INVALID :
                return $this->responseFactory->createResponse( 400 ) ;
        }

        try
        {
            $response = $handler->handle( $request ) ;
        }
        catch ( Throwable $exception )
        {
            $this->store->release( $decision->key ) ;
            throw $exception ;
        }

        return completeIdempotency( $decision , $response , $this->store , $options ) ;
    }
}
```

**Points clés de câblage :**

- **Placer après l'authentification et l'autorisation** — le `SCOPE` lit le compte authentifié, un rejeu repasse par les droits (un droit retiré n'est pas contourné par une réponse gardée), et un refus `401` / `403` ne touche jamais le magasin.
- **Placer après la lecture du corps** — l'empreinte lit `getParsedBody()`.
- **CORS** — un client navigateur doit pouvoir envoyer `Idempotency-Key` (dans `Access-Control-Allow-Headers`) et lire `Idempotent-Replayed` (dans `Access-Control-Expose-Headers`).

## Choisir un store

| Store | Où il vit | À utiliser quand… |
| :--- | :--- | :--- |
| [`InMemoryIdempotencyStore`](../../src/oihana/middleware/idempotency/InMemoryIdempotencyStore.php) | Ce paquet | Essais unitaires, scripts CLI, outils mono-process. **Pas pour du trafic HTTP multi-worker** — un rejeu arrivé sur un autre worker exécuterait l'action une deuxième fois. |
| Personnalisé (base de données, Redis, …) | Ton projet | Implémenter [`IdempotencyStore`](../../src/oihana/middleware/idempotency/IdempotencyStore.php). |

Ce qu'un store de production doit garantir :

- 🚨 **`begin()` atomique** — deux requêtes portant la même clé au même instant ne doivent jamais l'obtenir toutes les deux. La primitive existe partout : une insertion refusée sur une clé existante (clé unique d'une base, `Memcached::add()`, `SET NX` de Redis).
- **Garder pour tout le `TTL`** — une clé effacée trop tôt (un cache vidé ou évincé) laisse le rejeu suivant refaire l'action.
- **Sérialiser avec `IdempotencyRecord::toArray()` / `fromArray()`** — les clés sont les constantes de [`IdempotencyField`](../../src/oihana/middleware/enums/IdempotencyField.php).

## Hors scope

Ces helpers gardent **une réponse par clé**. Ils ne font PAS :

- **La construction des réponses d'erreur** — le corps de tes `400`, `422` et `429` suit le format de ton API.
- **La résolution des routes à clé obligatoire** — calcule `REQUIRED` toi-même, par route ou par client.
- **La garde d'une réponse au corps non relisible** — un corps non « seekable » ne peut pas être lu sans être consommé : `completeIdempotency()` libère alors la clé au lieu de la garder.
- **La détection d'un doublon créé par une personne** — deux saisies distinctes portent deux clés différentes : pour le serveur, ce sont deux actions.

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [CORS](cors.md) — exposer `Idempotent-Replayed` à un client navigateur.
- [Limitation de débit](rate-limiting.md) — le même patron store + décision + helper.
- [Brouillon IETF `Idempotency-Key`](https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/) — la spécification de l'en-tête.
