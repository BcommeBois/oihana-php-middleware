# Request defense

## Why you would want this — a concrete scenario

An attacker discovers your `/api/upload` endpoint. They send :

```
POST /api/upload HTTP/1.1
Content-Length: 2147483648
Content-Type: application/octet-stream

<2 GB of garbage>
```

Your PHP-FPM worker starts receiving the body. **Before your application code even runs**, the body parser (Slim's `BodyParsingMiddleware`, or PHP's `php://input` buffering, or your own `json_decode( $request->getBody() )`) tries to materialise the 2 GB payload into memory. By the time your validation kicks in to reject the upload, your worker has consumed 2 GB of RAM and triggered the OOM killer. Your process dies. Your supervisor restarts it. The attacker fires another request. Repeat until your server is down.

**With `enforceMaxBodySize()` called BEFORE any body parsing**, the request is rejected based on the `Content-Length` header alone — no memory allocation, no parsing, no streaming. Cost : one header read + one integer compare.

```
HTTP/1.1 413 Payload Too Large
```

The attacker gets a clean rejection, your worker stays healthy. PHP-level defense complements the upstream guards (nginx `client_max_body_size`, PHP `post_max_size` / `upload_max_filesize`) — having all three layers prevents one misconfiguration from being load-bearing.

This page also covers a sibling defense, [`enforceTrustedHosts()`](#enforcetrustedhosts) (shipped later in v0.7), against Host Header attacks.

---

`oihana/php-middleware` ships pre-parsing defense helpers that reject obviously-bad requests before the application has to handle them.

## `enforceMaxBodySize()`

```php
namespace oihana\middleware\helpers\body ;

function enforceMaxBodySize( ServerRequestInterface $request , int $maxBytes ) : bool ;
```

Returns `true` when the body fits within the limit (or its length is unknown), `false` when it exceeds the limit or carries a malformed `Content-Length`.

### Behaviour

| `Content-Length` header | Returns |
| :--- | :--- |
| Absent (streaming / chunked) | `true` — can't verify, defer to upstream guards |
| `0` to `$maxBytes` | `true` |
| `> $maxBytes` | `false` |
| Negative (`-1`) | `false` (strict — `ctype_digit` rejects the sign) |
| Non-numeric (`abc`) | `false` |
| With leading `+`, decimals, or other non-digit characters | `false` |

**Defensive default for malformed input.** If the helper can't trust the declared length, the request is rejected. Better to bounce one weird legitimate request than to let a payload bomb through under an unverifiable header.

### Usage

```php
use oihana\enums\http\HttpStatusCode ;
use function oihana\middleware\helpers\body\enforceMaxBodySize ;

// Reject any body larger than 10 MiB before parsing.
if ( !enforceMaxBodySize( $request , 10 * 1024 * 1024 ) )
{
    return $responseFactory->createResponse( HttpStatusCode::PAYLOAD_TOO_LARGE ) ;
}

// Safe to parse — body is at most 10 MiB.
$parsed = json_decode( (string) $request->getBody() , true ) ;
```

### Where this fits in the defense stack

PHP-level body size enforcement is **one layer in a defense-in-depth setup** — not a replacement for the upstream limits :

| Layer | Configures | Purpose |
| :--- | :--- | :--- |
| **nginx / Apache** | `client_max_body_size`, `LimitRequestBody` | Reject at the edge, before the request reaches PHP-FPM. |
| **PHP** | `post_max_size`, `upload_max_filesize` | Bound the PHP runtime's body handling. |
| **`enforceMaxBodySize()`** | Per-endpoint limit | Tighter, route-specific limit set by application code. |

A login endpoint might cap at 4 KB ; an avatar upload at 5 MB ; a video upload at 200 MB. The upstream guards set a global ceiling ; the helper sets the per-route reality.

## `enforceTrustedHosts()` — shipped later in v0.7

Sibling helper against Host Header attacks (cache poisoning, password-reset poisoning). Documentation will land in this same page when the helper ships (Lot C).

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [RFC 9110 §15.5.14](https://www.rfc-editor.org/rfc/rfc9110#status.413) — `413 Payload Too Large` semantics.
- nginx [`client_max_body_size`](https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size) — upstream sibling configuration.
