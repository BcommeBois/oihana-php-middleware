# Maintenance mode

## Why you would want this — a concrete scenario

You ship a database schema migration that takes 90 seconds (rebuilding a large index, splitting a table, anything heavier than a one-row ALTER). During that window your app code is still up and listening, but every query that touches the migrating table throws a deadlock or a "relation does not exist". The user experience cascade :

- **The user** sees a generic `500 Internal Server Error`. They don't know if it's a glitch or if your site is broken. They refresh. They refresh again. They open a second tab. They refresh that too.
- **Their browser** (and your front-end retry logic) treats `500` as a transient error worth retrying — so every refresh fires N more failing requests.
- **Your mobile app** has retry-with-exponential-backoff logic, but it parses `500` differently than `503` — some clients hammer the server, others enter a broken state requiring an app restart.
- **Your error tracker** ingests thousands of identical 500s in 90 seconds. Your on-call gets paged. You waste 10 minutes confirming "yes this is the migration, not a real outage".

**With maintenance mode**, you flip a flag (env var, sentinel file, feature flag) at the top of your stack. Every request gets a clean :

```
HTTP/1.1 503 Service Unavailable
Retry-After: 120

Service under scheduled maintenance, back in 2 minutes.
```

Browsers and well-behaved HTTP clients **understand `503 + Retry-After`** — they back off and retry after the announced delay instead of treating it as a permanent error. Mobile apps that respect HTTP semantics queue the request and try again automatically. Your error tracker sees a single "maintenance announced" event instead of a flood. And the user sees the actual reason instead of guessing.

The helper is purely about **producing** the 503 response. **Detecting** the maintenance state (env var, sentinel file, deploy hook, feature flag) and **bypassing** it for an admin endpoint that needs to remain reachable — both stay your middleware's job.

---

`oihana/php-middleware` ships a procedural helper to respond cleanly when your application is under maintenance:

```php
namespace oihana\middleware\helpers\maintenance ;

function respondMaintenanceMode( ResponseInterface $response , array $options = [] ) : ResponseInterface ;
```

Turns a PSR-7 response into a clean `503 Service Unavailable`, with an optional `Retry-After` header and optional body. The helper has no opinion on **how** your app detects it is under maintenance — it only takes care of **responding** correctly when it is.

## Supported options

The `$options` array is keyed by [`MaintenanceOption`](../../src/oihana/middleware/enums/MaintenanceOption.php) constants.

| Option | Type | Effect |
| :--- | :--- | :--- |
| `RETRY_AFTER` | `int\|DateTimeInterface\|string\|null` | Value of `Retry-After`. Three accepted forms (see below). Omitted / `null` / invalid ⇒ no header. |
| `MESSAGE` | `string\|null` | Response body. Omitted, `null` or empty string ⇒ no body. |
| `CONTENT_TYPE` | `string` (default `'text/plain; charset=utf-8'`) | `Content-Type` of the body. Only emitted when `MESSAGE` is supplied. |

### `Retry-After` forms

| Type | Example | Effect |
| :--- | :--- | :--- |
| `int` | `120` | `Retry-After: 120` (delta-seconds, RFC 7231 §7.1.3 form 1). Must be > 0. |
| `DateTimeInterface` | `new DateTimeImmutable( '+30 minutes' )` | Formatted as IMF-fixdate via `oihana\http\helpers\dates\formatHttpDate()` (RFC 7231 form 2). |
| `string` | `'Wed, 21 Oct 2026 07:28:00 GMT'` | Passed through verbatim — caller manages the format. |

## Usage

```php
use function oihana\middleware\helpers\maintenance\respondMaintenanceMode ;
use oihana\middleware\enums\MaintenanceOption ;

// Case 1: simple delta-seconds with a text body
$response = respondMaintenanceMode( $response ,
[
    MaintenanceOption::RETRY_AFTER => 120 ,
    MaintenanceOption::MESSAGE     => 'Service under scheduled maintenance, back in 2 minutes.' ,
]) ;

// Case 2: JSON body
$response = respondMaintenanceMode( $response ,
[
    MaintenanceOption::RETRY_AFTER  => 600 ,
    MaintenanceOption::MESSAGE      => json_encode( [ 'status' => 'maintenance' , 'eta' => 600 ] ) ,
    MaintenanceOption::CONTENT_TYPE => 'application/json' ,
]) ;

// Case 3: absolute Retry-After (HTTP-date), no body
$response = respondMaintenanceMode( $response ,
[
    MaintenanceOption::RETRY_AFTER => new DateTimeImmutable( '+30 minutes' ) ,
]) ;
```

## Full recipe: Slim middleware with env toggle

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

// Wire-up
$app->add( new MaintenanceMiddleware
(
    enabled    : (bool) ( $_ENV[ 'APP_MAINTENANCE' ] ?? false ) ,
    retryAfter : 600 ,
    message    : 'Back at 14:00 UTC.' ,
)) ;
```

**Wiring key points**:

- **Place at the top of the stack** — to short-circuit the entire chain (auth, business logic, etc.) when maintenance mode is active.
- **Externalise the toggle** (env var, feature flag, sentinel file, etc.) — the helper does not decide the toggle, that is the middleware's job.
- **Possible bypass** — if you have an admin API that must remain reachable to end the maintenance, add an allowlist condition on path or IP before calling `respondMaintenanceMode`.

## Out of scope

This helper is limited to **building the 503 response**. It does NOT:

- **Detect maintenance mode** — that is the middleware's job (env var, feature flag, sentinel file, SQL table, …).
- **Admin bypass** — if some routes must remain reachable, the middleware filters them before the call.
- **Custom HTML page** — the helper produces text / JSON by default. For a styled HTML page, supply the HTML via `MESSAGE` and `CONTENT_TYPE: 'text/html; charset=utf-8'`.

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [Request ID](request-id.md) — to propagate a trace ID even during maintenance.
- [RFC 7231 §7.1.3 spec](https://datatracker.ietf.org/doc/html/rfc7231#section-7.1.3) — `Retry-After` semantics.
