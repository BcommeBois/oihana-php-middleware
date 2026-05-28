# Problem Details (RFC 9457)

## Pourquoi tu en aurais besoin — un scénario concret

Ton application React envoie un formulaire de création d'utilisateur mal formé à `POST /api/users`. Ton API répond :

```
HTTP/1.1 400 Bad Request
Content-Type: application/json

{"error":"invalid"}
```

Le développeur frontend doit maintenant écrire du code de parsing spécifique pour comprendre ce que `"invalid"` veut dire. Champ manquant ? Règle de validation cassée ? Email dupliqué ? Contrainte d'unicité sur une autre colonne ? Il devine, il parse le message humain, il écrit un `switch` sur des patterns de chaînes qui casse à la prochaine reformulation du message d'erreur.

Multiplie ça par chaque API de ta société. Chaque backend invente sa propre forme d'erreur. Chaque frontend bricole son propre parser. Le debug en production prend des minutes par requête parce que l'erreur est une phrase libre.

**Avec Problem Details** (RFC 9457, anciennement RFC 7807), chaque erreur parle la même langue :

```
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/problem+json

{
  "type": "https://api.example.com/probs/validation-failed",
  "title": "Validation failed",
  "status": 422,
  "detail": "Email must be unique.",
  "instance": "/users",
  "field": "email",
  "value": "jane@example.com"
}
```

Standardisé, lisible par machine, avec des champs d'extension optionnels (`field`, `value`) pour le contexte applicatif. L'URI `type` sert aussi de lien de documentation, donc les backends peuvent publier leur catalogue d'erreurs à une URL stable. Les stacks API modernes (Spring, FastAPI, ASP.NET Core) émettent ce format par défaut ; les clients HTTP (Apollo, générateurs OpenAPI, Insomnia) le parsent nativement.

Quand ce n'est **pas** utile : pour les monolithes full-stack d'une seule équipe où le même monde écrit les erreurs backend et le handler frontend — une phrase dans du JSON suffit. Problem Details prend tout son sens à la frontière entre équipes, services ou organisations.

---

`oihana/php-middleware` fournit un helper procédural pour émettre des réponses Problem Details :

```php
namespace oihana\middleware\helpers\problem ;

function respondProblemDetails( ResponseInterface $response , Problem $problem ) : ResponseInterface ;
```

Plus un value object [`Problem`](../../src/oihana/middleware/problem/Problem.php) et un enum [`ProblemField`](../../src/oihana/middleware/enums/ProblemField.php) pour les noms de champs standards.

## Champs standards

Per [RFC 9457 §3.1](https://www.rfc-editor.org/rfc/rfc9457.html#section-3.1) — tous optionnels, tous omis du JSON quand `null` sur le value object :

| Champ | Type | Signification |
| :--- | :--- | :--- |
| `type` | URI reference | Identifie le type de problème. Devrait résoudre vers une page de documentation lisible. |
| `title` | string | Résumé court du type de problème. Ne devrait PAS changer d'une occurrence à l'autre. |
| `status` | int | Code de statut HTTP (`400`, `403`, `409`, …). |
| `detail` | string | Explication par occurrence : valeurs variables, noms de champs, identifiants. |
| `instance` | URI reference | Identifie l'occurrence spécifique (id de corrélation, référence de ticket, …). |

## Extensions

Toutes les clés spécifiques à ton application vivent dans le bag `$extensions` du value object `Problem`. Elles arrivent au top-level du JSON, à côté des champs standards :

```php
new Problem(
    title      : 'Out of credit' ,
    status     : 403 ,
    extensions :
    [
        'balance'  => 30 ,
        'accounts' => [ '/account/12345' , '/account/67890' ] ,
    ] ,
) ;
```

```json
{
  "title": "Out of credit",
  "status": 403,
  "balance": 30,
  "accounts": ["/account/12345", "/account/67890"]
}
```

**Per RFC §3.2, les clés d'extension ne doivent pas masquer les champs standards.** Le helper drop silencieusement toute clé en collision pendant la sérialisation — le champ standard gagne.

## Forme de la réponse

`respondProblemDetails()` :

- Pose le code HTTP depuis `$problem->status` (défaut **400** si null).
- Pose `Content-Type: application/problem+json` (RFC §3 — distinct de `application/json` pour que les consommateurs sachent qu'il faut le parser comme un Problem).
- Écrit le body JSON via `Problem::toArray()` avec `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` (garde les URIs et le texte non-ASCII lisibles).

PSR-7 immutable : retourne une nouvelle réponse, ne mute jamais l'entrée.

## Utilisation

```php
use oihana\middleware\problem\Problem ;
use function oihana\middleware\helpers\problem\respondProblemDetails ;

// Cas 1 — erreur de validation
$problem = new Problem
(
    type     : 'https://api.example.com/probs/validation-failed' ,
    title    : 'Validation failed' ,
    status   : 422 ,
    detail   : 'Email must be unique.' ,
    instance : '/users' ,
    extensions :
    [
        'field' => 'email' ,
        'value' => 'jane@example.com' ,
    ] ,
) ;

return respondProblemDetails( $response , $problem ) ;

// Cas 2 — "out of credit" minimaliste
return respondProblemDetails( $response , new Problem
(
    title  : 'Out of credit' ,
    status : 403 ,
    detail : 'Your current balance is 30, but that costs 50.' ,
) ) ;
```

## Recette complète : middleware centralisateur d'erreurs

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;
use Throwable ;

use oihana\middleware\problem\Problem ;
use function oihana\middleware\helpers\problem\respondProblemDetails ;

class ProblemDetailsMiddleware implements MiddlewareInterface
{
    public function __construct
    (
        private readonly ResponseFactoryInterface $responseFactory ,
        private readonly bool                     $exposeStackTrace = false ,
    ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        try
        {
            return $handler->handle( $request ) ;
        }
        catch ( ValidationException $e )
        {
            return respondProblemDetails( $this->responseFactory->createResponse() , new Problem
            (
                type       : 'https://api.example.com/probs/validation-failed' ,
                title      : 'Validation failed' ,
                status     : 422 ,
                detail     : $e->getMessage() ,
                extensions : [ 'errors' => $e->errors() ] ,
            ) ) ;
        }
        catch ( Throwable $e )
        {
            return respondProblemDetails( $this->responseFactory->createResponse() , new Problem
            (
                type       : 'https://api.example.com/probs/internal-error' ,
                title      : 'Internal server error' ,
                status     : 500 ,
                detail     : $this->exposeStackTrace ? $e->getMessage() : 'An unexpected error occurred.' ,
                extensions : $this->exposeStackTrace ? [ 'trace' => $e->getTraceAsString() ] : null ,
            ) ) ;
        }
    }
}
```

**Points clés de câblage :**

- **À placer en haut de la pile** pour qu'il rattrape les erreurs de chaque handler en aval.
- **Utiliser des URIs `type` stables** (`https://api.example.com/probs/...`) — elles deviennent des ancres de documentation sur lesquelles les frontends s'appuient. Ne pas les renommer à la légère.
- **Garder `title` stable pour un `type` donné** ; faire varier `detail` selon l'occurrence.
- **Ne pas fuiter de données sensibles dans `detail` / extensions** — les réponses Problem Details sont visibles par le client.

## Hors scope

Ce helper couvre **la construction et l'émission** d'une réponse Problem Details. Il ne fait PAS :

- **Catcher les exceptions** — c'est toi qui câbles le try/catch, le helper produit juste la réponse.
- **Parser les réponses Problem Details entrantes** — si tu appelles des API qui en émettent, écris un petit décodeur côté client par-dessus les constantes `ProblemField`.
- **Définir un catalogue de problem-types** — les URIs `type` sont applicatives. Maintiens-les dans ta documentation d'API, pas dans le helper.

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [RFC 9457 — Problem Details for HTTP APIs](https://www.rfc-editor.org/rfc/rfc9457.html) — la spec officielle.
- [Request ID](request-id.md) — apparier un request id avec `instance` pour la traçabilité support.
