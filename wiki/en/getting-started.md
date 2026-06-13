# Getting started

## Install

```bash
composer require oihana/php-middleware
```

Requires PHP 8.4+. The package depends on `psr/http-message` (2.x) at runtime — pick any PSR-7 implementation (Slim PSR-7, Nyholm/psr7, Laminas Diactoros, Guzzle PSR-7…). It also consumes `oihana/php-http` (HTTP primitives) and `oihana/php-enums` (typed constants).

## Two-minute tour

The package ships a family of **procedural helpers** organised in thematic folders under `oihana\middleware\helpers\`, plus a handful of value objects and typed enums:

```
src/oihana/middleware/
├── helpers/
│   ├── body/          — enforceMaxBodySize
│   ├── cache/         — buildCacheControl, isNotModified, respondNotModified
│   ├── cors/          — applyCorsHeaders, isCorsRequest, isCorsPreflight
│   ├── csrf/          — generateCsrfToken, verifyCsrfToken
│   ├── host/          — enforceTrustedHosts
│   ├── maintenance/   — respondMaintenanceMode
│   ├── negotiation/   — negotiateMimeType, negotiateCharset, negotiateEncoding, negotiateLanguage
│   ├── observability/ — withResponseTime
│   ├── pagination/    — withPaginationHeaders
│   ├── problem/       — respondProblemDetails
│   ├── rateLimit/     — enforceRateLimit, withRateLimitHeaders
│   ├── requestId/     — requestIdFromRequest, withRequestIdHeader
│   ├── security/      — withSecurityHeaders, withDefaultSecurityBaseline, buildCspHeader, buildPermissionsPolicyHeader
│   ├── tracing/       — traceContextFromRequest, withTracingAttribute, withTraceparentResponseHeader
│   └── webhook/       — verifyWebhookSignature
├── pagination/        — PaginationLinks (value object)
├── problem/           — Problem (value object)
├── rateLimit/         — InMemoryRateLimitStore, RateLimitDecision, RateLimitStore
├── tracing/           — TraceContext, parseTraceparent
└── enums/             — typed option keys & constant sets (CacheDirective, CorsOption, CspDirective, …)
```

See the [table of contents](README.md) for a guided tour of each family. Most helpers take a PSR-7 message (request or response) and return a new instance — no mutation, true to the PSR-7 contract.

```php
use Psr\Http\Message\ResponseInterface ;

use function oihana\middleware\helpers\security\withSecurityHeaders ;
use function oihana\middleware\helpers\cors\applyCorsHeaders ;

use oihana\middleware\enums\SecurityHeadersOption ;
use oihana\middleware\enums\FrameOptions ;
use oihana\middleware\enums\ReferrerPolicy ;
use oihana\middleware\enums\CorsOption ;

function applyHttpDefenses( ServerRequestInterface $request , ResponseInterface $response ) : ResponseInterface
{
    // 1. Security headers (sensible baseline, tune to your app)
    $response = withSecurityHeaders( $response ,
    [
        SecurityHeadersOption::HSTS                 => 31536000 ,                                  // 1 year
        SecurityHeadersOption::FRAME_OPTIONS        => FrameOptions::DENY ,
        SecurityHeadersOption::CONTENT_TYPE_NOSNIFF => true ,
        SecurityHeadersOption::REFERRER_POLICY      => ReferrerPolicy::STRICT_ORIGIN_WHEN_CROSS_ORIGIN ,
    ]) ;

    // 2. CORS for the front-end on app.example.com
    $response = applyCorsHeaders( $request , $response ,
    [
        CorsOption::ALLOWED_ORIGINS   => [ 'https://app.example.com' ] ,
        CorsOption::ALLOWED_METHODS   => [ 'GET' , 'POST' , 'DELETE' ] ,
        CorsOption::ALLOW_CREDENTIALS => true ,
    ]) ;

    return $response ;
}
```

## Wiring in a PSR-15 middleware

The package does not ship ready-to-use middleware classes — it ships **procedural helpers** that you call from your own middlewares. Slim example:

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use function oihana\middleware\helpers\security\withSecurityHeaders ;
use oihana\middleware\enums\SecurityHeadersOption ;
use oihana\middleware\enums\FrameOptions ;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct( private readonly array $options = [] ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        $response = $handler->handle( $request ) ;
        return withSecurityHeaders( $response , $this->options ) ;
    }
}

// Wire-up
$app->add( new SecurityHeadersMiddleware
([
    SecurityHeadersOption::HSTS          => 31536000 ,
    SecurityHeadersOption::FRAME_OPTIONS => FrameOptions::DENY ,
])) ;
```

## Mocking PSR-7 requests in your tests

The helpers accept any PSR-7 implementation. The package's test suite uses `Slim\Psr7\Factory\ServerRequestFactory` (declared in `require-dev`).

```php
use Slim\Psr7\Factory\ResponseFactory ;
use Slim\Psr7\Factory\ServerRequestFactory ;

$request  = ( new ServerRequestFactory() )
    ->createServerRequest( 'OPTIONS' , '/api/users' )
    ->withHeader( 'Origin' , 'https://app.example.com' )
    ->withHeader( 'Access-Control-Request-Method' , 'POST' ) ;

$response = ( new ResponseFactory() )->createResponse() ;
```

## Next steps

- [Security headers](security-headers.md) — full detail of the 5 `withSecurityHeaders` options + CSP composition via `buildCspHeader`.
- [CORS](cors.md) — full algorithm of `applyCorsHeaders`, preflight handling, allowlist, credentials.
