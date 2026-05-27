# CSRF — protection stateless double-submit

`oihana/php-middleware` fournit deux helpers procéduraux pour mettre en place une protection CSRF stateless, signée HMAC :

- [`generateCsrfToken()`](#generatecsrftoken) — émet un token CSRF signé, à mettre dans un cookie ET à renvoyer au client.
- [`verifyCsrfToken()`](#verifycsrftoken) — vérifie qu'un token issu du cookie matche celui soumis par le client (header ou form).

Plus un enum [`CsrfField`](#csrffield) pour les noms de cookie / header conventionnels.

Le pattern utilisé est le **signed double-submit cookie** : le serveur stocke le token dans un cookie (lisible par le JS de la page), le JS ré-envoie le token dans un en-tête sur chaque mutation, le serveur vérifie que les deux matchent **et** que la signature HMAC est valide. Aucune session côté serveur n'est requise — le helper est purement stateless.

## Pourquoi signer le token

Un double-submit cookie **non signé** est vulnérable si un attaquant peut écrire dans le cookie (par exemple via un XSS partiel limité à un sous-domaine, ou via une attaque sub-domain takeover). Avec la signature HMAC, même si l'attaquant peut poser un cookie, il ne peut pas le faire passer pour un token légitime car il ne connaît pas le secret applicatif.

## `generateCsrfToken()`

```php
namespace oihana\middleware\helpers\csrf ;

function generateCsrfToken( string $secret , ?int $ttlSeconds = null ) : string ;
```

Émet un token de la forme :

```
<id>.<exp>.<sig>
```

- `<id>` — identifiant aléatoire 128 bits encodé en base64url (CSPRNG via `oihana\core\encoding\randomBase64Url()`).
- `<exp>` — timestamp Unix absolu d'expiration, ou `'0'` quand pas de TTL.
- `<sig>` — HMAC-SHA256 de `<id>.<exp>` keyée par `$secret`, encodée en base64url.

Les trois parties utilisent l'alphabet URL-safe `[A-Za-z0-9_-]` — le token peut être posé dans un cookie, un en-tête, un champ de formulaire ou une URL sans encoding additionnel.

**Lève `InvalidArgumentException`** quand `$secret` est vide.

### Usage

```php
use function oihana\middleware\helpers\csrf\generateCsrfToken ;

// TTL d'une heure — recommandé pour les formulaires navigateur.
$token = generateCsrfToken( $appSecret , ttlSeconds: 3600 ) ;

// Pas d'expiration — réservé aux clients API long-lived (rotation manuelle).
$token = generateCsrfToken( $appSecret ) ;
```

## `verifyCsrfToken()`

```php
namespace oihana\middleware\helpers\csrf ;

function verifyCsrfToken( string $cookieToken , string $submittedToken , string $secret ) : bool ;
```

Vérifie un token issu de `generateCsrfToken()`. Retourne `true` **uniquement** quand **toutes** ces conditions sont vraies :

1. **Égalité bit-à-bit** entre `cookieToken` et `submittedToken`, en **temps constant** (`hash_equals`). C'est la pierre angulaire du double-submit : un attaquant cross-site peut soumettre un token via JS, mais ne peut pas lire le cookie de la victime — les deux ne peuvent donc pas matcher.
2. **Format `<id>.<exp>.<sig>`** valide, trois parties non vides.
3. **Signature HMAC** valide en temps constant. Catche les tokens forgés par un attaquant qui peut poser le cookie sans connaître le secret.
4. **TTL** soit `'0'` (pas d'expiration), soit un timestamp dans le futur.

**Ne lève jamais d'exception** — retourne `false` sur n'importe quelle entrée invalide. Le helper peut être branché tel quel comme **seule porte allow/deny** d'un middleware :

```php
if ( !verifyCsrfToken( $cookie , $submitted , $secret ) )
{
    return new Response( 403 ) ;
}
```

## `CsrfField`

```php
namespace oihana\middleware\enums ;

class CsrfField
{
    public const string COOKIE_NAME = 'csrf' ;          // serveur → client
    public const string HEADER_NAME = 'X-CSRF-Token' ;  // client → serveur
}
```

Constantes typées pour les noms conventionnels de cookie et de header CSRF. Le caller reste libre d'utiliser ses propres noms — l'enum est une commodité, pas une obligation.

## Recipe complète : middleware Slim

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;
use Slim\Psr7\Response ;

use function oihana\middleware\helpers\csrf\generateCsrfToken ;
use function oihana\middleware\helpers\csrf\verifyCsrfToken ;
use function oihana\http\helpers\cookies\buildSetCookieHeader ;

use oihana\middleware\enums\CsrfField ;
use oihana\http\enums\CookieOption ;
use oihana\http\enums\SameSite ;
use oihana\enums\http\HttpMethod ;
use oihana\enums\http\HttpStatusCode ;

class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct( private readonly string $secret ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        $method = $request->getMethod() ;

        // Mutations → vérification du token
        if ( in_array( $method , [ HttpMethod::POST , HttpMethod::PUT , HttpMethod::PATCH , HttpMethod::DELETE ] , true ) )
        {
            $cookieToken = $request->getCookieParams()[ CsrfField::COOKIE_NAME ] ?? '' ;
            $submitted   = $request->getHeaderLine ( CsrfField::HEADER_NAME ) ;

            if ( !verifyCsrfToken( $cookieToken , $submitted , $this->secret ) )
            {
                return new Response( HttpStatusCode::FORBIDDEN ) ;
            }
        }

        // Lecture (GET, HEAD, OPTIONS) → on émet / rafraîchit un token
        $response = $handler->handle( $request ) ;

        $existingCookie = $request->getCookieParams()[ CsrfField::COOKIE_NAME ] ?? null ;

        if ( $existingCookie === null )
        {
            $newToken = generateCsrfToken( $this->secret , ttlSeconds: 3600 ) ;
            $cookie   = buildSetCookieHeader( CsrfField::COOKIE_NAME , $newToken , 3600 ,
            [
                CookieOption::SECURE    => true            ,
                CookieOption::HTTP_ONLY => false           , // doit être lisible par JS
                CookieOption::SAME_SITE => SameSite::STRICT ,
                CookieOption::PATH      => '/'             ,
            ]) ;
            $response = $response->withHeader( 'Set-Cookie' , $cookie ) ;
        }

        return $response ;
    }
}
```

**Points-clé du wiring** :

- **`HttpOnly: false`** sur le cookie CSRF — le pattern double-submit nécessite que le JS de la page puisse lire le cookie pour le copier dans l'en-tête.
- **`Secure: true`** — uniquement HTTPS.
- **`SameSite: Strict`** — protection complémentaire ; le cookie n'est envoyé que sur des navigations same-site.
- **Émission paresseuse** : on n'émet un nouveau token que si le client n'en a pas encore. Évite de cycler le token à chaque requête.

## Garanties de sécurité

- **Time-constant** : les deux comparaisons (token == token, sig == sig) utilisent `hash_equals()`. Pas de side-channel timing.
- **Stateless** : aucune entrée serveur (session, cache) à invalider. Idéal pour les API horizontalement scalables.
- **TTL borné** : un token leak via copy-paste / log a une fenêtre d'attaque bornée par le TTL fourni.
- **Cross-origin résistant** : un attaquant sur une autre origine peut soumettre un token via JS (en attaque CSRF classique) mais ne peut pas lire le cookie de la victime — les deux ne peuvent donc pas matcher.

## Ce que ce helper ne fait PAS

- **Pas de stockage côté serveur** — pas de blacklist de tokens révoqués. Si tu as besoin de révocation immédiate, il te faut soit raccourcir le TTL drastiquement, soit ajouter un store de blacklist au-dessus.
- **Pas de protection contre le XSS** — si l'attaquant a un XSS sur ta page, il peut lire le cookie et le copier dans son header. Couvre uniquement les attaques cross-origin classiques.
- **Pas de gestion automatique du cookie / header** — le helper produit / vérifie un token, c'est tout. L'émission du cookie et la lecture du header sont la responsabilité de ton middleware.

## Voir aussi

- [En-têtes de sécurité](security-headers.md) — pour combiner CSRF avec HSTS / CSP / `SameSite`.
- [`oihana/php-http`](https://github.com/BcommeBois/oihana-php-http) — `buildSetCookieHeader` et les helpers `signatures/` utilisés en interne.
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html) — référence des patterns de protection CSRF.
