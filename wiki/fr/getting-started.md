# Démarrage

## Installation

```bash
composer require oihana/php-middleware
```

Nécessite PHP 8.4+. Le paquet dépend de `psr/http-message` (2.x) à l'exécution — choisissez n'importe quelle implémentation PSR-7 (Slim PSR-7, Nyholm/psr7, Laminas Diactoros, Guzzle PSR-7…). Il consomme aussi `oihana/php-http` (primitives HTTP) et `oihana/php-enums` (constantes typées).

## Visite éclair

Le paquet expose **3 helpers procéduraux** organisés en deux familles thématiques sous `oihana\middleware\helpers\` :

```
src/oihana/middleware/
├── helpers/
│   ├── security/
│   │   ├── buildCspHeader.php
│   │   └── withSecurityHeaders.php
│   └── cors/
│       └── applyCorsHeaders.php
└── enums/
    ├── ReferrerPolicy.php
    ├── FrameOptions.php
    ├── CspDirective.php
    ├── SecurityHeadersOption.php
    └── CorsOption.php
```

Chaque helper accepte une `ResponseInterface` PSR-7 et retourne une nouvelle instance — pas de mutation, conforme au contrat PSR-7.

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
    // 1. En-têtes de sécurité (baseline raisonnable, à adapter)
    $response = withSecurityHeaders( $response ,
    [
        SecurityHeadersOption::HSTS                 => 31536000 ,                                  // 1 an
        SecurityHeadersOption::FRAME_OPTIONS        => FrameOptions::DENY ,
        SecurityHeadersOption::CONTENT_TYPE_NOSNIFF => true ,
        SecurityHeadersOption::REFERRER_POLICY      => ReferrerPolicy::STRICT_ORIGIN_WHEN_CROSS_ORIGIN ,
    ]) ;

    // 2. CORS pour le front sur app.example.com
    $response = applyCorsHeaders( $request , $response ,
    [
        CorsOption::ALLOWED_ORIGINS   => [ 'https://app.example.com' ] ,
        CorsOption::ALLOWED_METHODS   => [ 'GET' , 'POST' , 'DELETE' ] ,
        CorsOption::ALLOW_CREDENTIALS => true ,
    ]) ;

    return $response ;
}
```

## Câblage en middleware PSR-15

Le paquet ne fournit pas de classes middleware prêtes-à-l'emploi — il fournit les **helpers procéduraux** que vous appelez depuis vos propres middlewares. Exemple Slim :

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

// Câblage
$app->add( new SecurityHeadersMiddleware
([
    SecurityHeadersOption::HSTS          => 31536000 ,
    SecurityHeadersOption::FRAME_OPTIONS => FrameOptions::DENY ,
])) ;
```

## Mocking des requêtes PSR-7 dans vos tests

Les helpers acceptent n'importe quelle implémentation PSR-7. La suite de tests du paquet utilise `Slim\Psr7\Factory\ServerRequestFactory` (déclaré en `require-dev`).

```php
use Slim\Psr7\Factory\ResponseFactory ;
use Slim\Psr7\Factory\ServerRequestFactory ;

$request  = ( new ServerRequestFactory() )
    ->createServerRequest( 'OPTIONS' , '/api/users' )
    ->withHeader( 'Origin' , 'https://app.example.com' )
    ->withHeader( 'Access-Control-Request-Method' , 'POST' ) ;

$response = ( new ResponseFactory() )->createResponse() ;
```

## Étapes suivantes

- [En-têtes de sécurité](security-headers.md) — détail des 5 options de `withSecurityHeaders` + composition de CSP via `buildCspHeader`.
- [CORS](cors.md) — algorithme complet de `applyCorsHeaders`, gestion preflight, allowlist, credentials.
