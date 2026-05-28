# Pagination headers

## Why you would want this — a concrete scenario

Your `/api/users` endpoint returns 482 users across 10 pages of 50. The frontend pulls page 3. The response body is the list of 50 users — but how does the frontend know there are 10 pages total ? How does it build the "next" / "previous" buttons without re-implementing the URL scheme on the client ?

**Without pagination headers**, you typically end up shoehorning metadata into the JSON body :

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

Now every consumer has to know your envelope shape. Generic HTTP clients (Postman, `curl | jq`, hand-written shell scripts) can't paginate without parsing your custom JSON wrapper. CDNs can't follow links automatically. Open API generators produce SDK code that wraps every response in your envelope, polluting the type system.

**With pagination headers** (RFC 5988 / RFC 8288 standard `Link` header + de-facto `X-Total-Count`), the metadata sits in HTTP headers, the body stays pure data :

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

GitHub's API uses this pattern. Hypermedia clients (`curl` + `jq`, the official GitHub CLI, dozens of SDKs) follow `rel="next"` automatically. Your body is the data, full stop.

When this is **not** useful : single-page resources (no pagination state to expose). Or when your clients are exclusively JS frontends that already parse a custom envelope — the headers are extra noise they ignore.

---

`oihana/php-middleware` ships a procedural helper to stamp pagination headers :

```php
namespace oihana\middleware\helpers\pagination ;

function withPaginationHeaders( ResponseInterface $response , PaginationLinks $links ) : ResponseInterface ;
```

Plus a [`PaginationLinks`](../../src/oihana/middleware/pagination/PaginationLinks.php) value object carrying the four standard URIs and an optional total count.

## Headers emitted

| Header | Source | Format |
| :--- | :--- | :--- |
| **`Link`** | RFC 5988 / RFC 8288 | `<uri>; rel="first", <uri>; rel="prev", <uri>; rel="next", <uri>; rel="last"`. Emitted in this fixed order. Entries with `null` URIs are omitted. Header omitted entirely when ALL four URIs are `null`. |
| **`X-Total-Count`** | De-facto (popularised by GitHub) | Plain integer count. Emitted when `$totalCount !== null`. `0` is emitted (meaningful — empty result set). |

The `X-Total-Count` header is NOT in any RFC. The standard reserves no name for the total. `X-Total-Count` is the most common de-facto choice. If your clients expect a different name (`Total-Count`, `Total`), stamp it yourself with `withHeader()` after the helper call.

## `PaginationLinks` value object

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

Every field optional. Typical patterns :

| Page state | Fields populated |
| :--- | :--- |
| First page of N | `next`, `last`, `totalCount` |
| Middle page | `first`, `prev`, `next`, `last`, `totalCount` |
| Last page | `first`, `prev`, `totalCount` |
| Single page | (none or just `totalCount`) |
| Cursor-based / infinite scroll | `next` only |

## Usage

```php
use oihana\middleware\pagination\PaginationLinks ;

use function oihana\middleware\helpers\pagination\withPaginationHeaders ;

// Middle page of a paginated user list
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

## Full recipe : pagination service + middleware

The helper expects URIs you already built. You typically have a tiny pagination service that takes the current request + total count and produces the `PaginationLinks` :

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
// In your handler
$page  = max( 1 , (int) $request->getQueryParams()[ 'page' ] ?? 1 ) ;
$users = $this->users->paginate( $page , 50 ) ;
$links = $this->linkBuilder->build( $request , $page , 50 , $users->totalCount ) ;

$response->getBody()->write( json_encode( $users->items ) ) ;

return withPaginationHeaders( $response , $links )
    ->withHeader( 'Content-Type' , 'application/json' ) ;
```

## Out of scope

This helper covers **stamping the headers**. It does NOT :

- **Build the URIs for you** — the caller knows the URL scheme (`?page=N`, `?offset=N`, `?cursor=...`) and the base URL. Build them yourself, the helper stamps.
- **Compute the total count** — that's your database / repository's job.
- **Read pagination state from the request** — extract `?page=` / `?cursor=` yourself ; the helper deals only with the response side.
- **Emit non-standard rel values** (`rel="search"`, `rel="canonical"`, etc.) — use `withHeader('Link', ...)` directly for those. The helper covers the four pagination-specific rels only.
- **Body envelope wrapping** (`{ "data": [...], "meta": {...} }`) — that's a separate API design choice. With Link headers your body can stay pure data, but you're free to wrap if your clients expect it.

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [RFC 8288 — Web Linking](https://www.rfc-editor.org/rfc/rfc8288.html) — the `Link` header standard (updates RFC 5988).
- [GitHub API pagination docs](https://docs.github.com/en/rest/guides/using-pagination-in-the-rest-api) — the de-facto pattern this helper implements.
