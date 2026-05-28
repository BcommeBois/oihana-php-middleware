# Négociation de contenu

`oihana/php-middleware` fournit un wrapper PSR-7 léger pour choisir le meilleur type MIME côté serveur à partir d'un en-tête `Accept` client :

```php
namespace oihana\middleware\helpers\negotiation ;

function negotiateMimeType( ServerRequestInterface $request , array $supported , ?string $default = null ) : ?string ;
```

Délègue le travail réel à [`oihana\http\helpers\negotiation\negotiate()`](https://github.com/BcommeBois/oihana-php-http) (depuis la dépendance `oihana/php-http`), qui honore les quality values RFC 7231 et les wildcards `Accept` standards.

## Sémantique

| En-tête `Accept` | `$supported` | Retourne |
| :--- | :--- | :--- |
| `application/json` | `['application/json', 'text/html']` | `'application/json'` |
| `text/html;q=0.9, application/json` | `['application/json', 'text/html']` | `'application/json'` (q=1.0 l'emporte sur q=0.9) |
| `text/*` | `['application/json', 'text/csv', 'text/html']` | `'text/csv'` (premier match `text/*` selon l'ordre serveur) |
| wildcard universel | `['text/html', 'application/json']` | `'text/html'` (ordre de préférence serveur) |
| `application/json;q=0, text/html` | `['application/json', 'text/html']` | `'text/html'` (q=0 est un refus explicite — ignoré) |
| `application/xml` | `['application/json', 'text/html']` | `$default` (ou `null`) |
| absent | `['application/json', 'text/html']` | `$default` (ou `null`) |

## Utilisation

```php
use function oihana\middleware\helpers\negotiation\negotiateMimeType ;

$mime = negotiateMimeType( $request ,
[
    'application/json' ,
    'text/html' ,
    'text/csv' ,
] ,
'application/json' ) ;

// $mime est l'un des MIME listés, ou 'application/json' si aucun match.
```

## Recette complète : middleware Slim qui pose un attribut `mimeType`

```php
use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface as Request ;
use Psr\Http\Server\MiddlewareInterface ;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler ;

use function oihana\middleware\helpers\negotiation\negotiateMimeType ;

class ContentNegotiationMiddleware implements MiddlewareInterface
{
    public function __construct
    (
        private readonly array  $supported = [ 'application/json' , 'text/html' ] ,
        private readonly string $default   = 'application/json' ,
        private readonly string $attribute = 'mimeType' ,
    ) {}

    public function process( Request $request , RequestHandler $handler ) : ResponseInterface
    {
        $mime    = negotiateMimeType( $request , $this->supported , $this->default ) ;
        $request = $request->withAttribute( $this->attribute , $mime ) ;

        return $handler->handle( $request ) ;
    }
}
```

Les handlers en aval lisent le MIME choisi depuis l'attribut PSR-7 et sélectionnent le bon serializer / moteur de template en conséquence.

## Hors scope

Ce helper couvre **uniquement la négociation des types MIME**. Pour négocier les autres en-têtes `Accept*` (`Accept-Language`, `Accept-Encoding`, `Accept-Charset`), appelle [`oihana\http\helpers\negotiation\negotiate()`](https://github.com/BcommeBois/oihana-php-http) directement — `negotiateMimeType()` n'est qu'un adaptateur PSR-7 d'une ligne pour l'en-tête `Accept`. Il ne fait pas non plus :

- **Le fallback sur `?format=` dans la query string** — c'est une préoccupation applicative (certaines apps le veulent, d'autres non).
- **La pose du `Content-Type` de la réponse** — c'est le travail du handler, une fois qu'il sait ce qu'il a vraiment rendu.
- **Le throw sur type non supporté** — il retourne `$default` (ou `null`) pour que l'appelant décide quoi faire en cas de « pas de type acceptable » (ex. répondre `406 Not Acceptable`).

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [Négociation de contenu `oihana/php-http`](https://github.com/BcommeBois/oihana-php-http) — les primitives `negotiate()` et `parseAcceptHeader()` sous-jacentes, réutilisables pour tout en-tête `Accept*`.
- [RFC 7231 §5.3](https://datatracker.ietf.org/doc/html/rfc7231#section-5.3) — grammaire de la négociation de contenu.
