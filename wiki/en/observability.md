# Observability

## Why you would want this — a concrete scenario

A user reports : *"the dashboard feels slow today."*

You open DevTools, hit the endpoint they mention, see **1.2 seconds** total in the Network tab. Where do those 1.2 seconds go ? Three possibilities :

- **Network round-trip** — slow CDN, slow last-mile, packet loss between user and server.
- **Server processing** — slow query, slow third-party API, blocked event loop.
- **Render / parse time** — heavy JSON payload, slow client-side hydration.

Without timing data on the response, you guess. You add temporary `error_log()` calls in your controller, redeploy, ask the user to retry, parse the logs by hand. 30 minutes minimum.

**With `withResponseTime()`**, every response carries the server-side processing time as a header :

```
X-Response-Time: 187.42ms
```

DevTools now tells you : 1.2 s total, 187 ms server-side. **1 s is on the wire**, not in your code. Network problem, not application problem. Done in 5 seconds.

For more granular signals, the opt-in `Server-Timing` format is parsed natively by Chromium and Firefox DevTools and surfaces directly in the Network panel — every APM ingester (Datadog, New Relic, Sentry, Honeycomb) reads it too.

When this is **not** useful : pure static-file responses (your server isn't doing work), or when you already have an APM library that injects `Server-Timing` itself.

---

`oihana/php-middleware` ships a procedural helper to stamp the response with the elapsed processing time:

```php
namespace oihana\middleware\helpers\observability ;

function withResponseTime( ResponseInterface $response , float $startMicrotime , array $options = [] ) : ResponseInterface ;
```

Useful to surface server processing time to clients (frontend perf budgets, alerting on slow endpoints, support traceability) without pulling in a full APM library.

## Supported options

The `$options` array is keyed by [`ResponseTimeOption`](../../src/oihana/middleware/enums/ResponseTimeOption.php) constants.

| Option | Type | Default | Effect |
| :--- | :--- | :--- | :--- |
| `PRECISION` | `int` | `2` | Decimal places kept on the duration value in milliseconds. Negative values fall back to the default. |
| `USE_SERVER_TIMING` | `bool` | `false` | When `true`, emits the W3C `Server-Timing: metric;dur=ms` format instead of the de-facto `X-Response-Time: Nms` format. |
| `SERVER_TIMING_METRIC` | `string` | `'total'` | Metric name used on the `Server-Timing` header. Only consumed when `USE_SERVER_TIMING` is `true`. Empty string falls back to the default. |

## Header families

| Mode | Header | Format | When to pick |
| :--- | :--- | :--- | :--- |
| Default | `X-Response-Time` | `42.50ms` | De-facto Express / Koa convention. Picked up by most HTTP clients and dashboards out of the box. |
| `USE_SERVER_TIMING: true` | `Server-Timing` | `total;dur=42.50` | W3C standard. Parsed natively by Chromium / Firefox DevTools "Network" tab and most APM ingesters (Datadog, New Relic, Sentry). |

## Usage

```php
use function oihana\middleware\helpers\observability\withResponseTime ;
use oihana\middleware\enums\ResponseTimeOption ;

// Default — X-Response-Time: 12.34ms
$start    = microtime( true ) ;
$response = $handler->handle( $request ) ;
$response = withResponseTime( $response , $start ) ;

// Server-Timing — total;dur=12.34
$response = withResponseTime( $response , $start ,
[
    ResponseTimeOption::USE_SERVER_TIMING => true ,
]) ;

// Server-Timing with a custom metric name and lower precision
$response = withResponseTime( $response , $start ,
[
    ResponseTimeOption::USE_SERVER_TIMING    => true ,
    ResponseTimeOption::SERVER_TIMING_METRIC => 'app' ,
    ResponseTimeOption::PRECISION            => 1 ,
]) ;
```

## Full recipe: Slim middleware

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

**Wiring key points:**

- **Place at the top of the stack** — to measure the full handler chain including auth, validation, business logic, etc.
- **Use `microtime(true)`** — `float` precision sufficient for ms-resolution stamping. For nanosecond precision use `hrtime(true)` and adjust the unit math yourself.
- **Negative durations are clamped** — a `$startMicrotime` in the future (clock skew, mistaken value) produces `0.00ms` instead of a meaningless negative value.

## Out of scope

This helper is limited to **stamping a single duration measurement**. It does NOT:

- **Compose multiple `Server-Timing` metrics** — call the helper multiple times and re-stamp would replace the header. For multi-metric Server-Timing (e.g. `db;dur=5, cache;dur=2, app;dur=12`), build the value yourself and use `withHeader('Server-Timing', ...)` directly.
- **Measure individual sub-operations** — there is no built-in spanning. Use a real APM library if you need per-operation traces.
- **Emit both X-Response-Time and Server-Timing simultaneously** — pick one family per response.

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [Request ID](request-id.md) — correlate the duration with a trace ID for support.
- [W3C Server-Timing spec](https://www.w3.org/TR/server-timing/) — `Server-Timing` header reference.
