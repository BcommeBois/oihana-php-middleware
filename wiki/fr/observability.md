# Observabilité

## Pourquoi tu en aurais besoin — un scénario concret

Un utilisateur signale : *« le dashboard a l'air lent aujourd'hui. »*

Tu ouvres DevTools, tu appelles l'endpoint qu'il mentionne, tu vois **1,2 seconde** au total dans l'onglet Réseau. Où vont ces 1,2 seconde ? Trois possibilités :

- **Aller-retour réseau** — CDN lent, derniers kilomètres lents, perte de paquets entre l'utilisateur et le serveur.
- **Traitement serveur** — query lente, API tierce lente, event loop bloquée.
- **Temps de rendu / parsing** — payload JSON lourd, hydratation côté client lente.

Sans données de timing sur la réponse, tu devines. Tu ajoutes des `error_log()` temporaires dans ton controller, tu redéploies, tu demandes à l'utilisateur de retenter, tu parses les logs à la main. 30 minutes minimum.

**Avec `withResponseTime()`**, chaque réponse porte le temps de traitement serveur dans un en-tête :

```
X-Response-Time: 187.42ms
```

DevTools t'indique maintenant : 1,2 s au total, 187 ms côté serveur. **1 s est sur le réseau**, pas dans ton code. Problème réseau, pas problème applicatif. Réglé en 5 secondes.

Pour des signaux plus fins, le format `Server-Timing` en option est parsé nativement par les DevTools de Chromium et Firefox et s'affiche directement dans le panneau Réseau — chaque ingesteur APM (Datadog, New Relic, Sentry, Honeycomb) le lit aussi.

Quand ce n'est **pas** utile : pour des réponses purement statiques (ton serveur ne travaille pas), ou quand tu as déjà une lib APM qui injecte `Server-Timing` toute seule.

---

`oihana/php-middleware` fournit un helper procédural qui marque la réponse avec le temps de traitement écoulé :

```php
namespace oihana\middleware\helpers\observability ;

function withResponseTime( ResponseInterface $response , float $startMicrotime , array $options = [] ) : ResponseInterface ;
```

Pratique pour exposer le temps de traitement serveur aux clients (budgets perf côté front, alerting sur endpoints lents, traçabilité support) sans embarquer une lib APM complète.

## Options supportées

Le tableau `$options` est indexé par les constantes [`ResponseTimeOption`](../../src/oihana/middleware/enums/ResponseTimeOption.php).

| Option | Type | Défaut | Effet |
| :--- | :--- | :--- | :--- |
| `PRECISION` | `int` | `2` | Nombre de décimales conservées sur la durée en millisecondes. Valeur négative ⇒ retombe sur le défaut. |
| `USE_SERVER_TIMING` | `bool` | `false` | Quand `true`, émet le format W3C `Server-Timing: metric;dur=ms` au lieu du format de-facto `X-Response-Time: Nms`. |
| `SERVER_TIMING_METRIC` | `string` | `'total'` | Nom de la métrique utilisé sur l'en-tête `Server-Timing`. Lu uniquement quand `USE_SERVER_TIMING` est `true`. Chaîne vide ⇒ retombe sur le défaut. |

## Familles d'en-têtes

| Mode | En-tête | Format | Quand le choisir |
| :--- | :--- | :--- | :--- |
| Défaut | `X-Response-Time` | `42.50ms` | Convention de-facto Express / Koa. Reconnu par la plupart des clients HTTP et tableaux de bord d'office. |
| `USE_SERVER_TIMING: true` | `Server-Timing` | `total;dur=42.50` | Standard W3C. Parsé nativement par les DevTools « Réseau » de Chromium / Firefox et la plupart des ingesteurs APM (Datadog, New Relic, Sentry). |

## Utilisation

```php
use function oihana\middleware\helpers\observability\withResponseTime ;
use oihana\middleware\enums\ResponseTimeOption ;

// Défaut — X-Response-Time: 12.34ms
$start    = microtime( true ) ;
$response = $handler->handle( $request ) ;
$response = withResponseTime( $response , $start ) ;

// Server-Timing — total;dur=12.34
$response = withResponseTime( $response , $start ,
[
    ResponseTimeOption::USE_SERVER_TIMING => true ,
]) ;

// Server-Timing avec un nom de métrique personnalisé et moins de décimales
$response = withResponseTime( $response , $start ,
[
    ResponseTimeOption::USE_SERVER_TIMING    => true ,
    ResponseTimeOption::SERVER_TIMING_METRIC => 'app' ,
    ResponseTimeOption::PRECISION            => 1 ,
]) ;
```

## Recette complète : middleware Slim

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use oihana\middleware\enums\ResponseTimeOption ;

use function oihana\middleware\helpers\observability\withResponseTime ;

class ResponseTimeMiddleware implements MiddlewareInterface
{
    public function __construct
    (
        private readonly bool $useServerTiming = false ,
        private readonly int  $precision       = 2 ,
    ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        $start    = microtime( true ) ;
        $response = $handler->handle( $request ) ;

        return withResponseTime( $response , $start ,
        [
            ResponseTimeOption::USE_SERVER_TIMING => $this->useServerTiming ,
            ResponseTimeOption::PRECISION         => $this->precision ,
        ]) ;
    }
}
```

**Points clés de câblage :**

- **Placer en haut de la pile** — pour mesurer toute la chaîne de handlers : auth, validation, logique métier, etc.
- **Utiliser `microtime(true)`** — précision `float` suffisante pour la résolution ms. Pour de la nanoseconde, utilise `hrtime(true)` et ajuste la conversion d'unité toi-même.
- **Les durées négatives sont remises à zéro** — un `$startMicrotime` dans le futur (décalage d'horloge, valeur erronée) produit `0.00ms` au lieu d'une valeur négative sans sens.

## Hors scope

Ce helper se limite à **marquer une seule mesure de durée**. Il ne fait PAS :

- **La composition de plusieurs métriques `Server-Timing`** — appeler le helper plusieurs fois remplace l'en-tête. Pour du Server-Timing multi-métriques (ex. `db;dur=5, cache;dur=2, app;dur=12`), construis la valeur toi-même et utilise `withHeader('Server-Timing', ...)` directement.
- **La mesure d'opérations individuelles** — pas de spanning intégré. Utilise une vraie lib APM pour des traces par opération.
- **L'émission simultanée de X-Response-Time ET Server-Timing** — choisis une famille par réponse.

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [Request ID](request-id.md) — corréler la durée avec un ID de trace pour le support.
- [Spec W3C Server-Timing](https://www.w3.org/TR/server-timing/) — référence officielle de l'en-tête `Server-Timing`.
