# Distributed tracing (W3C Trace Context)

## Why you would want this — a concrete scenario

A user calls your support : *"I got a 500 error this morning at 10:32 when paying my order."*

Your request crossed `api-gateway` → `auth-service` → `order-service` → `payment-service` → Stripe. Each service logs to its own pipeline (Loki, Elasticsearch, Datadog Logs, …).

**Without tracing**, you grep your logs around 10:32 with "500" or "error" and get :

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

Two users, two errors, no obvious causal chain. You spend 20 minutes cross-referencing timestamps and IP addresses to figure out which 500 belongs to *your* user.

**With tracing**, your app stamps the trace id on the error response :

```
Reference for support: 4bf92f35-77b3-4da6-a3ce-929d0e0e4736
```

The user gives you that code. You paste it into your log aggregator's search bar :

```
trace_id:4bf92f35-77b3-4da6-a3ce-929d0e0e4736
```

You get **only** the events of this one user's request, in causal order, across every service :

```
[4bf92f35…] api-gateway     10:32:14.103 POST /orders user=user_4521
[4bf92f35…] auth-service    10:32:14.118 validated user user_4521
[4bf92f35…] order-service   10:32:14.142 creating order for user_4521
[4bf92f35…] order-service   10:32:14.421 order_91823 created
[4bf92f35…] payment-service 10:32:14.512 charging $99.50
[4bf92f35…] payment-service 10:32:14.987 ERROR Stripe returned 502
[4bf92f35…] api-gateway     10:32:15.002 → 500
```

5 seconds : `user_4521`, `order_91823`, Stripe returned 502 on the $99.50 charge. Retry the payment manually, point the user to the answer. Move on.

## How it works in 30 seconds

The [W3C Trace Context](https://www.w3.org/TR/trace-context/) standard defines two HTTP headers that propagate a trace across services :

```
traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01
              │  │                                │                │
              │  │                                │                └─ flags (sampled = 01)
              │  │                                └─ parent span id (8 bytes hex)
              │  └─ trace id (16 bytes hex) — SAME end-to-end across every service
              └─ version (always 00)

tracestate: vendor=key:value,other=42  ← vendor-specific, propagated verbatim
```

At every hop, a middleware :

1. Reads the incoming `traceparent` and inherits the **trace id** (the thing that ties everything together) and the **parent span id** (so we know who called us).
2. Generates a fresh **span id** for this hop.
3. Propagates the new `traceparent` (same trace id, new span id) to its own downstream calls (HTTP, DB, message queue).

Result : every log line, every metric, every error in every service of a single user request shares the same trace id. Your log aggregator does the rest.

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
        public string  $traceId ,       // 32 hex chars (16 bytes), shared end-to-end
        public string  $spanId ,        // 16 hex chars (8 bytes), unique to this hop
        public ?string $parentSpanId ,  // 16 hex chars, or null if root
        public bool    $sampled ,
        public ?string $tracestate = null ,
    ) {}

    public function toTraceparent() : string ;  // for stamping downstream calls
}
```

### Behaviour

| Incoming `traceparent` | Returned `TraceContext` |
| :--- | :--- |
| Valid W3C format | `traceId` and `parentSpanId` inherited, fresh `spanId` generated, `sampled` flag inherited |
| Missing or malformed | Entirely fresh : new `traceId`, fresh `spanId`, `parentSpanId = null`, `sampled = true` (default) |

Malformed input is **silently regenerated** — the W3C recommendation says "treat as if no traceparent received" so a misconfigured upstream proxy never breaks tracing.

| `TracingField` constant | Value |
| :--- | :--- |
| `HEADER_TRACEPARENT` | `'traceparent'` |
| `HEADER_TRACESTATE` | `'tracestate'` |
| `ATTRIBUTE_NAME` | `'traceContext'` (PSR-7 request attribute) |

## Full recipe : Slim middleware + downstream propagation

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
// Downstream call inside any handler
$context = $request->getAttribute( TracingField::ATTRIBUTE_NAME ) ;

$guzzle->get( 'https://api.partner.com/charge' ,
[
    'headers' =>
    [
        'traceparent' => $context->toTraceparent() ,
        // Optional: forward vendor state too
        'tracestate'  => $context->tracestate ?? '' ,
    ] ,
]) ;
```

```php
// Log correlation — every log line carries the trace id
$context = $request->getAttribute( TracingField::ATTRIBUTE_NAME ) ;

$logger->info( 'order created' ,
[
    'trace_id' => $context->traceId ,
    'span_id'  => $context->spanId ,
    'user_id'  => $userId ,
]) ;
```

## When this is useful — and when it is not

**Useful** :

- Distributed architecture (≥ 2 services that call each other).
- Support-driven debugging (give the user a reference code from an error response).
- Latency investigations across service boundaries ("where do the 800 ms come from ?").
- Plugging into existing observability backends (OpenTelemetry, Datadog, Honeycomb, Jaeger, Tempo, New Relic, Sentry) — all of them parse W3C Trace Context natively.

**Marginal** :

- Pure monolith with no service-to-service calls : `X-Request-Id` (see [request-id.md](request-id.md)) already gives you log correlation. Add tracing later if you split the monolith.
- No log aggregator yet : without Loki / Elastic / Datadog / etc., the trace id is just a string nobody can search. Stand up your aggregator first.

## Out of scope

This helper provides **only** the propagation and value-object surface. It does NOT :

- **Manage sub-spans inside a single request** — for DB queries, outbound HTTP calls, internal sub-operations, use the [OpenTelemetry PHP SDK](https://opentelemetry.io/docs/instrumentation/php/) which propagates correctly to Tempo / Jaeger / Datadog.
- **Implement a sampling policy** — incoming sampling flag is passed through verbatim ; freshly generated contexts default to `sampled = true`. Wrap the helper if you need a ratio sampler.
- **Format the trace id for humans** — `$context->traceId` is 32 lowercase hex chars. The error page can render it however it wants (e.g. `wordwrap($id, 8, '-', true)` for UUID-style chunks).
- **Stamp the response by default** — `withTraceparentResponseHeader()` is explicit opt-in. The W3C standard talks about forward propagation only ; exposing the trace id to clients is a pragmatic choice the application takes.

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [Request ID](request-id.md) — sibling helper for the same-service log-correlation case.
- [W3C Trace Context recommendation](https://www.w3.org/TR/trace-context/) — official spec.
- [OpenTelemetry](https://opentelemetry.io/) — the full instrumentation framework that builds on this standard.
