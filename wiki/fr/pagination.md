# En-têtes de pagination

## Pourquoi tu en aurais besoin — un scénario concret

Ton endpoint `/api/users` retourne 482 utilisateurs répartis sur 10 pages de 50. Le frontend récupère la page 3. Le body de la réponse est la liste de 50 utilisateurs — mais comment le frontend sait qu'il y a 10 pages au total ? Comment il construit les boutons « suivant » / « précédent » sans réimplémenter le schéma d'URL côté client ?

**Sans en-têtes de pagination**, tu finis typiquement par caser les métadonnées dans le body JSON :

```json
{
  "data": [ { ... }, { ... }, ... ],
  "meta": {
    "page": 3,
    "per_page": 50,
    "total": 482,
    "total_pages": 10,
    "next_url": "/api/users?page=4",
    "prev_url": "/api/users?page=2"
  }
}
```

Maintenant chaque consommateur doit connaître la forme de ton enveloppe. Les clients HTTP génériques (Postman, `curl | jq`, scripts shell écrits à la main) ne peuvent pas paginer sans parser ton wrapper JSON custom. Les CDN ne peuvent pas suivre les liens automatiquement. Les générateurs Open API produisent du code SDK qui wrappe chaque réponse dans ton enveloppe, polluant le système de types.

**Avec les en-têtes de pagination** (en-tête standard `Link` RFC 5988 / RFC 8288 + `X-Total-Count` de-facto), les métadonnées vivent dans les en-têtes HTTP, le body reste de la donnée pure :

```
HTTP/1.1 200 OK
Link: <https://api.example.com/users?page=1>; rel="first",
      <https://api.example.com/users?page=2>; rel="prev",
      <https://api.example.com/users?page=4>; rel="next",
      <https://api.example.com/users?page=10>; rel="last"
X-Total-Count: 482
Content-Type: application/json

[{ ... }, { ... }, ... ]
```

L'API de GitHub utilise ce pattern. Les clients hypermedia (`curl` + `jq`, le CLI GitHub officiel, des dizaines de SDK) suivent `rel="next"` automatiquement. Ton body est la donnée, point.

Quand ce n'est **pas** utile : pour des ressources single-page (pas d'état de pagination à exposer). Ou quand tes clients sont exclusivement des frontends JS qui parsent déjà une enveloppe custom — les en-têtes sont du bruit qu'ils ignorent.

---

`oihana/php-middleware` fournit un helper procédural pour poser les en-têtes de pagination :

```php
namespace oihana\middleware\helpers\pagination ;

function withPaginationHeaders( ResponseInterface $response , PaginationLinks $links ) : ResponseInterface ;
```

Plus un value object [`PaginationLinks`](../../src/oihana/middleware/pagination/PaginationLinks.php) qui porte les quatre URIs standards et un total count optionnel.

## En-têtes émis

| En-tête | Source | Format |
| :--- | :--- | :--- |
| **`Link`** | RFC 5988 / RFC 8288 | `<uri>; rel="first", <uri>; rel="prev", <uri>; rel="next", <uri>; rel="last"`. Émis dans cet ordre fixe. Les entrées avec URI `null` sont omises. En-tête entièrement omis quand les QUATRE URIs sont `null`. |
| **`X-Total-Count`** | De-facto (popularisé par GitHub) | Entier brut. Émis quand `$totalCount !== null`. `0` est émis (significatif — résultat vide). |

L'en-tête `X-Total-Count` n'est dans AUCUNE RFC. Le standard ne réserve aucun nom pour le total. `X-Total-Count` est le choix de-facto le plus courant. Si tes clients attendent un autre nom (`Total-Count`, `Total`), pose-le toi-même avec `withHeader()` après l'appel au helper.

## Value object `PaginationLinks`

```php
final readonly class PaginationLinks
{
    public function __construct(
        public ?string $first      = null ,
        public ?string $prev       = null ,
        public ?string $next       = null ,
        public ?string $last       = null ,
        public ?int    $totalCount = null ,
    ) {}
}
```

Chaque champ optionnel. Patterns typiques :

| État de page | Champs renseignés |
| :--- | :--- |
| Première page de N | `next`, `last`, `totalCount` |
| Page du milieu | `first`, `prev`, `next`, `last`, `totalCount` |
| Dernière page | `first`, `prev`, `totalCount` |
| Page unique | (aucun ou juste `totalCount`) |
| Cursor-based / infinite scroll | `next` seul |

## Utilisation

```php
use oihana\middleware\pagination\PaginationLinks ;

use function oihana\middleware\helpers\pagination\withPaginationHeaders ;

// Page du milieu d'une liste paginée d'utilisateurs
$links = new PaginationLinks
(
    first      : 'https://api.example.com/users?page=1' ,
    prev       : 'https://api.example.com/users?page=2' ,
    next       : 'https://api.example.com/users?page=4' ,
    last       : 'https://api.example.com/users?page=10' ,
    totalCount : 482 ,
) ;

return withPaginationHeaders( $response , $links ) ;
```

## Recette complète : service de pagination + middleware

Le helper attend des URIs que tu as déjà construites. Tu as typiquement un petit service de pagination qui prend la requête courante + le total count et produit le `PaginationLinks` :

```php
use Psr\Http\Message\ServerRequestInterface ;
use oihana\middleware\pagination\PaginationLinks ;

class PageLinkBuilder
{
    public function build( ServerRequestInterface $request , int $page , int $perPage , int $totalCount ) : PaginationLinks
    {
        $totalPages = (int) max( 1 , ceil( $totalCount / $perPage ) ) ;
        $base       = (string) $request->getUri()->withQuery( '' ) ;
        $queryRest  = $this->queryWithoutPage( $request->getUri()->getQuery() ) ;

        $link = fn ( int $p ) :string
            => $base . '?page=' . $p . ( $queryRest === '' ? '' : '&' . $queryRest ) ;

        return new PaginationLinks
        (
            first      : $page > 1            ? $link( 1 )           : null ,
            prev       : $page > 1            ? $link( $page - 1 )   : null ,
            next       : $page < $totalPages  ? $link( $page + 1 )   : null ,
            last       : $page < $totalPages  ? $link( $totalPages ) : null ,
            totalCount : $totalCount ,
        ) ;
    }

    private function queryWithoutPage( string $query ) : string
    {
        parse_str( $query , $params ) ;
        unset( $params[ 'page' ] ) ;
        return http_build_query( $params ) ;
    }
}
```

```php
// Dans ton handler
$page  = max( 1 , (int) $request->getQueryParams()[ 'page' ] ?? 1 ) ;
$users = $this->users->paginate( $page , 50 ) ;
$links = $this->linkBuilder->build( $request , $page , 50 , $users->totalCount ) ;

$response->getBody()->write( json_encode( $users->items ) ) ;

return withPaginationHeaders( $response , $links )
    ->withHeader( 'Content-Type' , 'application/json' ) ;
```

## Hors scope

Ce helper couvre **le stamping des en-têtes**. Il ne fait PAS :

- **La construction des URIs pour toi** — l'appelant connaît le schéma d'URL (`?page=N`, `?offset=N`, `?cursor=...`) et l'URL de base. Tu les construis toi-même, le helper les stamp.
- **Le calcul du total count** — c'est le travail de ta base / ton repository.
- **La lecture de l'état de pagination depuis la requête** — extrais `?page=` / `?cursor=` toi-même ; le helper s'occupe uniquement du côté réponse.
- **L'émission de valeurs `rel` non standards** (`rel="search"`, `rel="canonical"`, etc.) — utilise `withHeader('Link', ...)` directement pour ces cas. Le helper couvre uniquement les quatre rels spécifiques à la pagination.
- **L'enveloppage de body** (`{ "data": [...], "meta": {...} }`) — c'est un choix d'API design séparé. Avec les en-têtes Link ton body peut rester de la donnée pure, mais tu es libre de wrapper si tes clients l'attendent.

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [RFC 8288 — Web Linking](https://www.rfc-editor.org/rfc/rfc8288.html) — le standard de l'en-tête `Link` (met à jour RFC 5988).
- [Doc GitHub API pagination](https://docs.github.com/en/rest/guides/using-pagination-in-the-rest-api) — le pattern de-facto que ce helper implémente.
