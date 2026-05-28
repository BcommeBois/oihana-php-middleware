# Problem Details (RFC 9457)

## Why you would want this — a concrete scenario

Your React app posts a malformed user creation form to `POST /api/users`. Your API responds :

```
HTTP/1.1 400 Bad Request
Content-Type: application/json

{"error":"invalid"}
```

The frontend dev now has to write specific parsing code to figure out what `"invalid"` means. Missing field ? Validation rule failed ? Duplicate email ? Unique constraint on another column ? They guess, they parse the human-readable message, they write a `switch` on string patterns that breaks the next time you reword the error.

Multiply this by every API in your company. Every backend invents its own error shape. Every frontend hand-rolls its own parser. Production debugging takes minutes per request because the error is a free-form sentence.

**With Problem Details** (RFC 9457, formerly RFC 7807), every error speaks the same language :

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

Standardized, machine-readable, with optional extension fields (`field`, `value`) for application-specific context. The `type` URI doubles as a documentation link, so backends can publish their error catalog at a stable URL. Modern API stacks (Spring, FastAPI, ASP.NET Core) emit this format by default ; HTTP clients (Apollo, OpenAPI generators, Insomnia) parse it natively.

When this is **not** useful : single-app full-stack monoliths where the same team writes both the backend errors and the frontend handler — a sentence in JSON is fine. Problem Details earns its keep at the API boundary between teams, services, or organizations.

---

`oihana/php-middleware` ships a procedural helper to emit Problem Details responses :

```php
namespace oihana\middleware\helpers\problem ;

function respondProblemDetails( ResponseInterface $response , Problem $problem ) : ResponseInterface ;
```

Plus a [`Problem`](../../src/oihana/middleware/problem/Problem.php) value object and a [`ProblemField`](../../src/oihana/middleware/enums/ProblemField.php) enum for the standard field names.

## Standard fields

Per [RFC 9457 §3.1](https://www.rfc-editor.org/rfc/rfc9457.html#section-3.1) — all optional, all omitted from the JSON when `null` on the value object :

| Field | Type | Meaning |
| :--- | :--- | :--- |
| `type` | URI reference | Identifies the problem type. Should resolve to a human-readable documentation page. |
| `title` | string | Short summary of the problem type. SHOULD NOT change from occurrence to occurrence. |
| `status` | int | HTTP status code (`400`, `403`, `409`, …). |
| `detail` | string | Per-occurrence explanation : variable values, field names, ids. |
| `instance` | URI reference | Identifies the specific occurrence (correlation id, ticket reference, …). |

## Extensions

Any application-specific keys live in the `$extensions` bag of the `Problem` value object. They land at the top level of the JSON, alongside the standard fields :

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

**Per RFC §3.2, extension keys must not shadow standard field names.** The helper silently drops any colliding key during serialisation — the standard field wins.

## Response shape

`respondProblemDetails()` :

- Sets the HTTP status code from `$problem->status` (defaults to **400** when null).
- Sets `Content-Type: application/problem+json` (RFC §3 — distinct from `application/json` so consumers know to parse it as a Problem).
- Writes the JSON body via `Problem::toArray()` with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` (keeps URIs and non-ASCII text readable).

PSR-7 immutable : returns a new response, never mutates the input.

## Usage

```php
use oihana\middleware\problem\Problem ;
use function oihana\middleware\helpers\problem\respondProblemDetails ;

// Case 1 — Validation error
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

// Case 2 — Minimal "out of credit"
return respondProblemDetails( $response , new Problem
(
    title  : 'Out of credit' ,
    status : 403 ,
    detail : 'Your current balance is 30, but that costs 50.' ,
) ) ;
```

## Full recipe : centralised error handler middleware

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

**Wiring key points :**

- **Place at the top of the stack** so it catches errors from every downstream handler.
- **Use stable `type` URIs** (`https://api.example.com/probs/...`) — they become documentation anchors that frontends rely on. Don't rename them lightly.
- **Keep `title` stable per `type`** ; vary `detail` per occurrence.
- **Don't leak sensitive data in `detail` / extensions** — Problem Details responses are visible to the client.

## Out of scope

This helper covers **building and emitting** a Problem Details response. It does NOT :

- **Catch exceptions** — you wire the try/catch yourself, the helper just produces the response.
- **Parse incoming Problem Details responses** — if you call APIs that emit them, write a small client-side decoder on top of `ProblemField` constants.
- **Define a problem-type catalog** — the `type` URIs are application-specific. Maintain them in your API documentation, not in the helper.

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [RFC 9457 — Problem Details for HTTP APIs](https://www.rfc-editor.org/rfc/rfc9457.html) — the official specification.
- [Request ID](request-id.md) — pair a request id with `instance` for support traceability.
