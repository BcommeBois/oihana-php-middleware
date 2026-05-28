# Webhook signature verification

## Why you would want this — a concrete scenario

Your app receives webhooks from GitHub at `POST https://app.example.com/webhooks/github` — every time someone pushes to your repo, GitHub fires a payload describing the commits, the branch, the author. Your handler then triggers your CI, posts to Slack, audits the change, maybe deploys to staging.

**The webhook URL is public.** It has to be — GitHub needs to reach it from its own servers. Anyone who knows the URL (or guesses it) can POST whatever they want :

```bash
curl -X POST https://app.example.com/webhooks/github \
  -H 'Content-Type: application/json' \
  -d '{"ref":"refs/heads/main","head_commit":{"id":"abc123"}}'
```

Without verification, your CI fires on an attacker-crafted "push", your audit log records a phantom commit, your deploy ships nothing or something hostile. Real attacks have hit real companies through unauthenticated webhooks (see : the [Codecov 2021 supply chain attack](https://about.codecov.io/security-update/), where attackers eventually targeted exactly this kind of integration boundary).

**With HMAC signature verification**, every webhook payload carries a header that proves the sender knew your shared secret :

```
POST /webhooks/github
X-Hub-Signature-256: sha256=a8f8...c4d2
Content-Type: application/json

{"ref":"refs/heads/main", ...}
```

The signature is `HMAC-SHA256( your_secret , raw_request_body )`. Your server recomputes it with the same secret and the body it just received ; if both match in **constant time** (`hash_equals()`), the webhook is authentic. An attacker who doesn't know the secret cannot forge a valid signature.

When this is **not** useful : webhooks from providers that use a different scheme (Stripe with timestamp + version, GCP Cloud Tasks with OIDC tokens, etc.). For those, use the provider's official SDK — see "out of scope" below.

---

`oihana/php-middleware` ships a procedural helper for the simple-HMAC pattern that covers most webhook providers :

```php
namespace oihana\middleware\helpers\webhook ;

function verifyWebhookSignature
(
    string $payload ,           // raw request body
    string $signature ,         // value of the provider's signature header
    string $secret ,            // shared secret you configured with the provider
    array  $options = []        // optional: algorithm, prefix, encoding
) : bool ;
```

Returns `bool` — `true` when the signature is valid, `false` otherwise (wrong secret, tampered payload, malformed input, empty inputs). **Never throws** : you can plug it as a single allow/deny gate without try/catch.

## Provider compatibility matrix

| Provider | Header | Algorithm | Prefix | Encoding | Options needed |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **GitHub** | `X-Hub-Signature-256` | SHA-256 | `sha256=` | hex | `PREFIX => 'sha256='` |
| **Slack** | `X-Slack-Signature` | SHA-256 | `v0=` | hex | `PREFIX => 'v0='` |
| **Shopify** | `X-Shopify-Hmac-Sha256` | SHA-256 | (none) | base64 | `ENCODING => 'base64'` |
| **Twilio** | `X-Twilio-Signature` | SHA-1 | (none) | base64 | `ALGORITHM => 'sha1'`, `ENCODING => 'base64'` |
| **SendGrid Event Webhook** (non-signed-timestamp variant) | `X-Twilio-Email-Event-Webhook-Signature` | SHA-256 | (none) | base64 | `ENCODING => 'base64'` |

Use the [`WebhookSignatureOption`](../../src/oihana/middleware/enums/WebhookSignatureOption.php) constants to keep the option keys typed (`WebhookSignatureOption::PREFIX`, etc.).

## Supported options

| Option | Type | Default | Effect |
| :--- | :--- | :--- | :--- |
| `ALGORITHM` | `string` | `'sha256'` | HMAC algorithm. Validated against `hash_hmac_algos()`. Unknown values fall back to the default. |
| `PREFIX` | `string\|null` | `null` | String prefix stripped from the incoming `$signature` before comparison (`'sha256='` for GitHub, `'v0='` for Slack). Non-matching or absent prefix leaves the signature untouched. |
| `ENCODING` | `string` | `'hex'` | Encoding of the signature bytes : `'hex'` (GitHub, Slack) or `'base64'` (Twilio, Shopify). Unknown values fall back to the default. |

## Usage

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

## Full recipe : GitHub webhook middleware

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

        // Body has been consumed — rewind so downstream handlers can re-read it.
        $request->getBody()->rewind() ;

        return $handler->handle( $request ) ;
    }
}
```

**Wiring key points :**

- **Read the body ONCE, before parsing.** The signature is computed on the raw body — if your body parser modifies it (whitespace, JSON canonicalisation, etc.), the signature breaks.
- **Rewind the stream** after reading so the downstream handler can consume the body normally.
- **Store the secret in your secrets manager**, not in code. The secret is the only thing keeping attackers out.
- **Constant-time comparison happens inside the helper** — don't try to short-circuit with `===` even before the helper call ; let it handle the whole compare path.

## Out of scope

This helper covers the **simple-HMAC pattern**. It deliberately does NOT support :

- **Stripe** (`Stripe-Signature: t=...,v1=...`) — Stripe's scheme blends in a timestamp and a version selector that must be parsed AND freshness-checked (replay attack mitigation). Use the official [`stripe/stripe-php`](https://github.com/stripe/stripe-php) library, which implements all this correctly.
- **SendGrid signed-timestamp variant** — same pattern as Stripe (timestamp + signature). Use the official SendGrid PHP SDK.
- **GCP / AWS / Azure** webhooks signed with cloud-provider tokens (OIDC, IAM-signed URLs) — these require validating cryptographic identity, not a shared secret. Use the relevant cloud SDK.
- **Replay-attack mitigation** — the simple-HMAC pattern verifies authenticity but doesn't prevent replay of a captured-and-replayed valid payload. Providers that need replay protection ship a timestamp in their signature scheme (see Stripe above).

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [GitHub webhooks docs](https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries) — official `X-Hub-Signature-256` semantics.
- [Slack docs — Verifying requests](https://api.slack.com/authentication/verifying-requests-from-slack) — official `X-Slack-Signature` semantics.
- [Shopify docs — Verifying webhooks](https://shopify.dev/docs/apps/build/webhooks/subscribe/get-started#verify-the-webhook) — official `X-Shopify-Hmac-Sha256` semantics.
