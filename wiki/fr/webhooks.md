# Vérification de signature de webhook

## Pourquoi tu en aurais besoin — un scénario concret

Ton application reçoit des webhooks de GitHub à `POST https://app.example.com/webhooks/github` — chaque fois que quelqu'un pousse sur ton dépôt, GitHub envoie un payload décrivant les commits, la branche, l'auteur. Ton handler déclenche ensuite ton CI, poste sur Slack, audite le changement, voire déploie sur staging.

**L'URL du webhook est publique.** Elle doit l'être — GitHub doit pouvoir l'atteindre depuis ses propres serveurs. N'importe qui qui connaît l'URL (ou la devine) peut POSTer ce qu'il veut :

```bash
curl -X POST https://app.example.com/webhooks/github \
  -H 'Content-Type: application/json' \
  -d '{"ref":"refs/heads/main","head_commit":{"id":"abc123"}}'
```

Sans vérification, ton CI se déclenche sur un "push" fabriqué par un attaquant, ton journal d'audit enregistre un commit fantôme, ton déploiement livre rien ou pire quelque chose d'hostile. Des vraies attaques ont touché des vraies entreprises via des webhooks non authentifiés (voir : l'[attaque supply chain Codecov 2021](https://about.codecov.io/security-update/), où les attaquants ont fini par cibler exactement ce genre de frontière d'intégration).

**Avec une vérification de signature HMAC**, chaque payload de webhook porte un en-tête qui prouve que l'émetteur connaissait ton secret partagé :

```
POST /webhooks/github
X-Hub-Signature-256: sha256=a8f8...c4d2
Content-Type: application/json

{"ref":"refs/heads/main", ...}
```

La signature est `HMAC-SHA256( ton_secret , corps_brut_de_la_requête )`. Ton serveur la recalcule avec le même secret et le corps qu'il vient de recevoir ; si les deux matchent en **temps constant** (`hash_equals()`), le webhook est authentique. Un attaquant qui ne connaît pas le secret ne peut pas forger une signature valide.

Quand ce n'est **pas** utile : pour des webhooks de fournisseurs qui utilisent un schéma différent (Stripe avec timestamp + version, GCP Cloud Tasks avec tokens OIDC, etc.). Pour ceux-là, utilise le SDK officiel du fournisseur — voir "hors scope" plus bas.

---

`oihana/php-middleware` fournit un helper procédural pour le pattern simple-HMAC qui couvre la majorité des fournisseurs de webhooks :

```php
namespace oihana\middleware\helpers\webhook ;

function verifyWebhookSignature
(
    string $payload ,           // corps brut de la requête
    string $signature ,         // valeur de l'en-tête de signature du fournisseur
    string $secret ,            // secret partagé configuré avec le fournisseur
    array  $options = []        // optionnel : algorithm, prefix, encoding
) : bool ;
```

Retourne `bool` — `true` quand la signature est valide, `false` sinon (mauvais secret, payload trafiqué, entrée malformée, entrées vides). **Ne throw jamais** : tu peux le brancher comme une seule porte allow/deny sans try/catch.

## Matrice de compatibilité des fournisseurs

| Fournisseur | En-tête | Algo | Préfixe | Encodage | Options nécessaires |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **GitHub** | `X-Hub-Signature-256` | SHA-256 | `sha256=` | hex | `PREFIX => 'sha256='` |
| **Slack** | `X-Slack-Signature` | SHA-256 | `v0=` | hex | `PREFIX => 'v0='` |
| **Shopify** | `X-Shopify-Hmac-Sha256` | SHA-256 | (aucun) | base64 | `ENCODING => 'base64'` |
| **Twilio** | `X-Twilio-Signature` | SHA-1 | (aucun) | base64 | `ALGORITHM => 'sha1'`, `ENCODING => 'base64'` |
| **SendGrid Event Webhook** (variante non signée-timestamp) | `X-Twilio-Email-Event-Webhook-Signature` | SHA-256 | (aucun) | base64 | `ENCODING => 'base64'` |

Utilise les constantes de [`WebhookSignatureOption`](../../src/oihana/middleware/enums/WebhookSignatureOption.php) pour garder les clés d'option typées (`WebhookSignatureOption::PREFIX`, etc.).

## Options supportées

| Option | Type | Défaut | Effet |
| :--- | :--- | :--- | :--- |
| `ALGORITHM` | `string` | `'sha256'` | Algorithme HMAC. Validé contre `hash_hmac_algos()`. Valeurs inconnues ⇒ retombe sur le défaut. |
| `PREFIX` | `string\|null` | `null` | Préfixe strippé de la `$signature` entrante avant comparaison (`'sha256='` pour GitHub, `'v0='` pour Slack). Préfixe absent ou non-matchant ⇒ signature laissée intacte. |
| `ENCODING` | `string` | `'hex'` | Encodage des octets de signature : `'hex'` (GitHub, Slack) ou `'base64'` (Twilio, Shopify). Valeurs inconnues ⇒ retombe sur le défaut. |

## Utilisation

```php
use function oihana\middleware\helpers\webhook\verifyWebhookSignature ;
use oihana\middleware\enums\WebhookSignatureOption ;

// 1 — GitHub
$ok = verifyWebhookSignature
(
    (string) $request->getBody() ,
    $request->getHeaderLine( 'X-Hub-Signature-256' ) ,
    $githubWebhookSecret ,
    [ WebhookSignatureOption::PREFIX => 'sha256=' ] ,
) ;

// 2 — Shopify
$ok = verifyWebhookSignature
(
    (string) $request->getBody() ,
    $request->getHeaderLine( 'X-Shopify-Hmac-Sha256' ) ,
    $shopifyWebhookSecret ,
    [ WebhookSignatureOption::ENCODING => 'base64' ] ,
) ;

// 3 — Twilio (SHA-1 base64)
$ok = verifyWebhookSignature
(
    (string) $request->getBody() ,
    $request->getHeaderLine( 'X-Twilio-Signature' ) ,
    $twilioAuthToken ,
    [
        WebhookSignatureOption::ALGORITHM => 'sha1' ,
        WebhookSignatureOption::ENCODING  => 'base64' ,
    ] ,
) ;
```

## Recette complète : middleware GitHub webhook

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Message\ResponseFactoryInterface ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use oihana\middleware\enums\WebhookSignatureOption ;

use function oihana\middleware\helpers\webhook\verifyWebhookSignature ;

class GithubWebhookAuthMiddleware implements MiddlewareInterface
{
    public function __construct
    (
        private readonly string                   $secret ,
        private readonly ResponseFactoryInterface $responseFactory ,
    ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        $body      = (string) $request->getBody() ;
        $signature = $request->getHeaderLine( 'X-Hub-Signature-256' ) ;

        if ( !verifyWebhookSignature( $body , $signature , $this->secret ,
        [
            WebhookSignatureOption::PREFIX => 'sha256=' ,
        ] ) )
        {
            return $this->responseFactory->createResponse( 401 ) ;
        }

        // Le body a été consommé — rewind pour que les handlers en aval puissent le relire.
        $request->getBody()->rewind() ;

        return $handler->handle( $request ) ;
    }
}
```

**Points clés de câblage :**

- **Lire le body UNE SEULE FOIS, avant tout parsing.** La signature est calculée sur le body brut — si ton body parser le modifie (whitespace, canonicalisation JSON, etc.), la signature casse.
- **Rewind le stream** après lecture pour que le handler en aval puisse consommer le body normalement.
- **Stocker le secret dans ton gestionnaire de secrets**, pas dans le code. Le secret est la seule chose qui tient les attaquants à l'extérieur.
- **La comparaison constant-time se fait à l'intérieur du helper** — n'essaie pas de court-circuiter avec `===` avant l'appel ; laisse le helper gérer tout le chemin de comparaison.

## Hors scope

Ce helper couvre le **pattern simple-HMAC**. Il ne supporte délibérément PAS :

- **Stripe** (`Stripe-Signature: t=...,v1=...`) — le schéma Stripe intègre un timestamp et un sélecteur de version qu'il faut parser ET vérifier en fraîcheur (mitigation des replay attacks). Utilise la lib officielle [`stripe/stripe-php`](https://github.com/stripe/stripe-php), qui implémente tout ça correctement.
- **Variante SendGrid signée-timestamp** — même pattern que Stripe (timestamp + signature). Utilise le SDK officiel SendGrid PHP.
- **Webhooks GCP / AWS / Azure** signés avec des tokens cloud-provider (OIDC, URLs IAM-signed) — il faut valider une identité cryptographique, pas un secret partagé. Utilise le SDK cloud concerné.
- **Mitigation des replay attacks** — le pattern simple-HMAC vérifie l'authenticité mais ne protège pas du rejeu d'un payload valide capturé puis re-soumis. Les fournisseurs qui ont besoin de cette protection embarquent un timestamp dans leur schéma de signature (voir Stripe ci-dessus).

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [Doc GitHub webhooks](https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries) — sémantique officielle `X-Hub-Signature-256`.
- [Doc Slack — Verifying requests](https://api.slack.com/authentication/verifying-requests-from-slack) — sémantique officielle `X-Slack-Signature`.
- [Doc Shopify — Verifying webhooks](https://shopify.dev/docs/apps/build/webhooks/subscribe/get-started#verify-the-webhook) — sémantique officielle `X-Shopify-Hmac-Sha256`.
