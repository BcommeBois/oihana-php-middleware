# CSRF — stateless double-submit protection

## Why you would want this — a concrete scenario

Your user is logged into `bank.example.com` (your app). Their browser holds a valid session cookie. They open a second tab and visit `cute-cats.example` — an attacker-controlled site that looks innocent. That page silently contains :

```html
<form action="https://bank.example.com/transfer" method="POST" id="f">
  <input name="to"     value="attacker_account"/>
  <input name="amount" value="10000"/>
</form>
<script>document.getElementById( 'f' ).submit() ;</script>
```

When the page loads, the browser submits the form. **The browser automatically attaches the user's `bank.example.com` session cookie**, because that's what cookies do — they're sent on every request to their origin, regardless of which site triggered the request. Your server sees a perfectly authenticated request to `/transfer` from a logged-in user. The transfer succeeds. The user finds out next month from their bank statement.

This is **Cross-Site Request Forgery** (CSRF). Every state-changing `POST` / `PUT` / `DELETE` is exposed by default. Sessions alone don't protect you — they're the very mechanism the attacker exploits.

The fix : require a token on every mutation that **only your own pages can read**. The attacker's page can submit a form to your domain, but it cannot read your domain's cookies (Same-Origin Policy). So if your server requires "the token in the cookie must match the token submitted in a header", and only your own JavaScript can read the cookie to set that header, an attacker's form submission is missing the header → rejected → 403.

That's the **double-submit cookie** pattern. Adding an HMAC signature on the token (so even a leaked cookie value can't be forged into a valid token by an attacker) gives you a **signed double-submit cookie** — what these helpers implement, statelessly (no server-side session needed).

---

`oihana/php-middleware` ships two procedural helpers for stateless, HMAC-signed CSRF protection:

- [`generateCsrfToken()`](#generatecsrftoken) — issues a signed CSRF token, ready to be set as a cookie AND echoed back to the client.
- [`verifyCsrfToken()`](#verifycsrftoken) — verifies that a token from the cookie matches the one submitted by the client (header or form).

Plus an enum [`CsrfField`](#csrffield) for the conventional cookie / header names.

The pattern used is the **signed double-submit cookie**: the server stores the token in a cookie (readable by the page's JS), the JS re-submits the token in a header on every mutation, the server checks that both match **and** that the HMAC signature is valid. No server-side session is needed — the helper is purely stateless.

## Why sign the token

A **non-signed** double-submit cookie is vulnerable when an attacker can write to the cookie (for instance via a partial XSS limited to a subdomain, or a subdomain-takeover attack). With the HMAC signature, even if the attacker can plant a cookie, they cannot make it look like a legitimate token because they do not know the app secret.

## `generateCsrfToken()`

```php
namespace oihana\middleware\helpers\csrf ;

function generateCsrfToken( string $secret , ?int $ttlSeconds = null ) : string ;
```

Emits a token of the form:

```
<id>.<exp>.<sig>
```

- `<id>` — 128-bit base64url-encoded random identifier (CSPRNG via `oihana\core\encoding\randomBase64Url()`).
- `<exp>` — absolute Unix expiry timestamp, or `'0'` when no TTL.
- `<sig>` — base64url-encoded HMAC-SHA256 of `<id>.<exp>` keyed by `$secret`.

All three parts use the URL-safe alphabet `[A-Za-z0-9_-]` — the token can sit in a cookie, header, form field or URL with no additional encoding.

**Throws `InvalidArgumentException`** when `$secret` is empty.

### Usage

```php
use function oihana\middleware\helpers\csrf\generateCsrfToken ;

// 1-hour TTL — recommended for browser forms.
$token = generateCsrfToken( $appSecret , ttlSeconds: 3600 ) ;

// No expiry — reserved for long-lived API clients (manual rotation).
$token = generateCsrfToken( $appSecret ) ;
```

## `verifyCsrfToken()`

```php
namespace oihana\middleware\helpers\csrf ;

function verifyCsrfToken( string $cookieToken , string $submittedToken , string $secret ) : bool ;
```

Verifies a token issued by `generateCsrfToken()`. Returns `true` **only** when **all** of these hold:

1. **Byte equality** between `cookieToken` and `submittedToken`, **constant-time** (`hash_equals`). This is the cornerstone of double-submit: a cross-site attacker can submit a token via JS, but cannot read the victim's cookie — the two cannot match.
2. **Valid `<id>.<exp>.<sig>` shape** with three non-empty parts.
3. **Valid HMAC signature**, constant-time. Catches forged tokens written by an attacker who can plant the cookie but does not know the secret.
4. **TTL** is either `'0'` (no expiry) or a Unix timestamp in the future.

**Never throws** — returns `false` on any invalid input. The helper can be plugged straight in as the **sole allow/deny gate** of a middleware:

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
    public const string COOKIE_NAME = 'csrf' ;          // server → client
    public const string HEADER_NAME = 'X-CSRF-Token' ;  // client → server
}
```

Typed constants for the conventional CSRF cookie and header names. Callers remain free to use their own names — the enum is a convenience, not a requirement.

## Full recipe: Slim middleware

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

        // Mutations → verify the token
        if ( in_array( $method , [ HttpMethod::POST , HttpMethod::PUT , HttpMethod::PATCH , HttpMethod::DELETE ] , true ) )
        {
            $cookieToken = $request->getCookieParams()[ CsrfField::COOKIE_NAME ] ?? '' ;
            $submitted   = $request->getHeaderLine ( CsrfField::HEADER_NAME ) ;

            if ( !verifyCsrfToken( $cookieToken , $submitted , $this->secret ) )
            {
                return new Response( HttpStatusCode::FORBIDDEN ) ;
            }
        }

        // Reads (GET, HEAD, OPTIONS) → issue / refresh a token
        $response = $handler->handle( $request ) ;

        $existingCookie = $request->getCookieParams()[ CsrfField::COOKIE_NAME ] ?? null ;

        if ( $existingCookie === null )
        {
            $newToken = generateCsrfToken( $this->secret , ttlSeconds: 3600 ) ;
            $cookie   = buildSetCookieHeader( CsrfField::COOKIE_NAME , $newToken , 3600 ,
            [
                CookieOption::SECURE    => true            ,
                CookieOption::HTTP_ONLY => false           , // must be JS-readable
                CookieOption::SAME_SITE => SameSite::STRICT ,
                CookieOption::PATH      => '/'             ,
            ]) ;
            $response = $response->withHeader( 'Set-Cookie' , $cookie ) ;
        }

        return $response ;
    }
}
```

**Wiring key points**:

- **`HttpOnly: false`** on the CSRF cookie — the double-submit pattern requires the page's JS to read the cookie and copy it into the header.
- **`Secure: true`** — HTTPS only.
- **`SameSite: Strict`** — defense-in-depth; the cookie is only sent on same-site navigations.
- **Lazy issuance**: a new token is only issued when the client doesn't have one. Avoids cycling the token on every request.

## Security guarantees

- **Time-constant**: both comparisons (token == token, sig == sig) use `hash_equals()`. No timing side-channel.
- **Stateless**: no server-side entry (session, cache) to invalidate. Ideal for horizontally-scalable APIs.
- **Bounded TTL**: a token leaked via copy-paste / logs has an attack window bounded by the supplied TTL.
- **Cross-origin resistant**: a cross-origin attacker can submit a token via JS (classic CSRF attack) but cannot read the victim's cookie — the two cannot match.

## What this helper does NOT do

- **No server-side storage** — no revocation blacklist. If you need immediate revocation, either shorten the TTL drastically, or layer a blacklist store on top.
- **No XSS protection** — if an attacker has XSS on your page, they can read the cookie and copy it into their header. Covers only classic cross-origin attacks.
- **No automatic cookie / header handling** — the helper produces / verifies a token, that's it. Cookie emission and header reading are the responsibility of your middleware.

## See also

- [Security headers](security-headers.md) — to combine CSRF with HSTS / CSP / `SameSite`.
- [`oihana/php-http`](https://github.com/BcommeBois/oihana-php-http) — `buildSetCookieHeader` and the `signatures/` helpers used internally.
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html) — reference for CSRF protection patterns.
