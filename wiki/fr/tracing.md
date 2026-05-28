# Traçage distribué (W3C Trace Context)

## Pourquoi tu en aurais besoin — un scénario concret

Un utilisateur appelle ton support : *« j'ai eu une erreur 500 ce matin à 10h32 en payant ma commande. »*

Sa requête a traversé `api-gateway` → `auth-service` → `order-service` → `payment-service` → Stripe. Chaque service log dans son propre flux (Loki, Elasticsearch, Datadog Logs…).

**Sans traçage**, tu cherches dans tes logs autour de 10:32 avec « 500 » ou « erreur » et tu obtiens :

```
api-gateway     | 10:32:14 ERROR POST /orders → 500
auth-service    | 10:32:14 INFO  validated user user_4521
auth-service    | 10:32:14 INFO  validated user user_8923
order-service   | 10:32:14 INFO  creating order for user_4521
order-service   | 10:32:14 INFO  creating order for user_8923
order-service   | 10:32:14 ERROR DB timeout
order-service   | 10:32:14 INFO  order_91823 created
payment-service | 10:32:14 INFO  charging $99.50
payment-service | 10:32:14 ERROR Stripe returned 502
payment-service | 10:32:14 INFO  charging $42.00
```

Deux utilisateurs, deux erreurs, pas de chaîne causale évidente. Tu passes 20 minutes à croiser horodatages et adresses IP pour identifier le 500 de *ton* utilisateur.

**Avec traçage**, ton application affiche l'identifiant de trace sur la page d'erreur :

```
Référence support : 4bf92f35-77b3-4da6-a3ce-929d0e0e4736
```

L'utilisateur te donne ce code. Tu le colles dans la barre de recherche de ton agrégateur de logs :

```
trace_id:4bf92f35-77b3-4da6-a3ce-929d0e0e4736
```

Tu obtiens **uniquement** les events de cette requête utilisateur précise, dans l'ordre causal, à travers tous les services :

```
[4bf92f35…] api-gateway     10:32:14.103 POST /orders user=user_4521
[4bf92f35…] auth-service    10:32:14.118 validated user user_4521
[4bf92f35…] order-service   10:32:14.142 creating order for user_4521
[4bf92f35…] order-service   10:32:14.421 order_91823 created
[4bf92f35…] payment-service 10:32:14.512 charging $99.50
[4bf92f35…] payment-service 10:32:14.987 ERROR Stripe returned 502
[4bf92f35…] api-gateway     10:32:15.002 → 500
```

5 secondes : `user_4521`, `order_91823`, Stripe a renvoyé 502 sur le débit de 99,50 $. Tu réessaies le paiement à la main, tu donnes la réponse à l'utilisateur. Tu passes à autre chose.

## Comment ça marche en 30 secondes

Le standard [W3C Trace Context](https://www.w3.org/TR/trace-context/) définit deux en-têtes HTTP qui propagent une trace à travers les services :

```
traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01
              │  │                                │                │
              │  │                                │                └─ flags (sampled = 01)
              │  │                                └─ id du span parent (8 octets hex)
              │  └─ id de trace (16 octets hex) — IDENTIQUE bout-en-bout dans chaque service
              └─ version (toujours 00)

tracestate: vendor=key:value,other=42  ← données vendor, propagées telles quelles
```

À chaque saut, un middleware :

1. Lit le `traceparent` entrant et hérite de l'**id de trace** (ce qui lie tout ensemble) et du **id du span parent** (le service qui nous a appelé).
2. Génère un **nouvel id de span** pour ce saut.
3. Propage le nouveau `traceparent` (même id de trace, nouvel id de span) sur ses propres appels sortants (HTTP, DB, message queue).

Résultat : chaque ligne de log, chaque métrique, chaque erreur dans chaque service pour une seule requête utilisateur partagent le même id de trace. Ton agrégateur de logs se charge du reste.

## API

```php
namespace oihana\middleware\helpers\tracing ;

function traceContextFromRequest      ( ServerRequestInterface $request ) : TraceContext ;
function withTracingAttribute         ( ServerRequestInterface $request , TraceContext $context , string $attributeName = 'traceContext' ) : ServerRequestInterface ;
function withTraceparentResponseHeader( ResponseInterface      $response , TraceContext $context ) : ResponseInterface ;

namespace oihana\middleware\tracing ;

function parseTraceparent( string $value ) : ?array ;

final readonly class TraceContext
{
    public function __construct(
        public string  $traceId ,       // 32 caractères hex (16 octets), partagé bout-en-bout
        public string  $spanId ,        // 16 caractères hex (8 octets), unique à ce saut
        public ?string $parentSpanId ,  // 16 caractères hex, ou null si racine
        public bool    $sampled ,
        public ?string $tracestate = null ,
    ) {}

    public function toTraceparent() : string ;  // pour stamper les appels sortants
}
```

### Comportement

| `traceparent` entrant | `TraceContext` retourné |
| :--- | :--- |
| Format W3C valide | `traceId` et `parentSpanId` hérités, nouveau `spanId` généré, flag `sampled` hérité |
| Absent ou malformé | Entièrement neuf : nouveau `traceId`, nouveau `spanId`, `parentSpanId = null`, `sampled = true` (défaut) |

Une valeur malformée est **régénérée silencieusement** — la recommandation W3C dit « treat as if no traceparent received » pour qu'un proxy mal configuré en amont ne casse jamais le traçage.

| Constante `TracingField` | Valeur |
| :--- | :--- |
| `HEADER_TRACEPARENT` | `'traceparent'` |
| `HEADER_TRACESTATE` | `'tracestate'` |
| `ATTRIBUTE_NAME` | `'traceContext'` (attribut PSR-7 sur la requête) |

## Recette complète : middleware Slim + propagation downstream

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use oihana\middleware\enums\TracingField ;

use function oihana\middleware\helpers\tracing\traceContextFromRequest ;
use function oihana\middleware\helpers\tracing\withTracingAttribute ;
use function oihana\middleware\helpers\tracing\withTraceparentResponseHeader ;

class TracingMiddleware implements MiddlewareInterface
{
    public function __construct( private readonly bool $exposeTraceparentToClient = true ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        $context = traceContextFromRequest( $request ) ;
        $request = withTracingAttribute  ( $request , $context ) ;

        $response = $handler->handle( $request ) ;

        return $this->exposeTraceparentToClient
             ? withTraceparentResponseHeader( $response , $context )
             : $response ;
    }
}
```

```php
// Appel sortant dans n'importe quel handler
$context = $request->getAttribute( TracingField::ATTRIBUTE_NAME ) ;

$guzzle->get( 'https://api.partner.com/charge' ,
[
    'headers' =>
    [
        'traceparent' => $context->toTraceparent() ,
        // Optionnel : forwarder le state vendor aussi
        'tracestate'  => $context->tracestate ?? '' ,
    ] ,
]) ;
```

```php
// Corrélation des logs — chaque ligne porte l'id de trace
$context = $request->getAttribute( TracingField::ATTRIBUTE_NAME ) ;

$logger->info( 'order created' ,
[
    'trace_id' => $context->traceId ,
    'span_id'  => $context->spanId ,
    'user_id'  => $userId ,
]) ;
```

## Quand c'est utile — et quand ça ne l'est pas

**Utile** :

- Architecture distribuée (≥ 2 services qui s'appellent entre eux).
- Debug piloté par le support (donner à l'utilisateur un code de référence depuis une réponse en erreur).
- Investigations de latence à travers les frontières de service (« d'où viennent les 800 ms ? »).
- Branchement sur des backends d'observabilité existants (OpenTelemetry, Datadog, Honeycomb, Jaeger, Tempo, New Relic, Sentry) — tous parsent W3C Trace Context nativement.

**Marginal** :

- Monolithe pur sans appel de service à service : `X-Request-Id` (cf. [request-id.md](request-id.md)) te donne déjà la corrélation des logs. Ajoute le traçage plus tard si tu splittes le monolithe.
- Pas encore d'agrégateur de logs : sans Loki / Elastic / Datadog / etc., l'id de trace n'est qu'une chaîne que personne ne peut chercher. Monte ton agrégateur d'abord.

## Hors scope

Ce helper fournit **uniquement** la propagation et le value-object. Il ne fait PAS :

- **La gestion de sous-spans dans une même requête** — pour les requêtes DB, les appels HTTP sortants, les sous-opérations internes, utilise le [SDK OpenTelemetry PHP](https://opentelemetry.io/docs/instrumentation/php/) qui propage correctement vers Tempo / Jaeger / Datadog.
- **La politique d'échantillonnage** — le flag sampled entrant est passé tel quel ; les contextes neufs sont sampled = true par défaut. Wrapper le helper si tu as besoin d'un sampler ratio.
- **Le formatage de l'id de trace pour humains** — `$context->traceId` est 32 caractères hex en minuscules. La page d'erreur le rend comme elle veut (ex. `wordwrap($id, 8, '-', true)` pour un découpage UUID).
- **Le stamping sur la réponse par défaut** — `withTraceparentResponseHeader()` est explicitement opt-in. Le standard W3C ne parle que de propagation forward ; exposer l'id de trace aux clients est un choix pragmatique applicatif.

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [Request ID](request-id.md) — helper voisin pour le cas de la corrélation de logs intra-service.
- [Recommandation W3C Trace Context](https://www.w3.org/TR/trace-context/) — spec officielle.
- [OpenTelemetry](https://opentelemetry.io/) — le framework d'instrumentation complet qui s'appuie sur ce standard.
