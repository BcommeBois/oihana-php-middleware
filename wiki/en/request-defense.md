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

This page also covers a sibling defense, [`enforceTrustedHosts()`](#enforcetrustedhosts), against Host Header attacks.

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

## `enforceTrustedHosts()`

```php
namespace oihana\middleware\helpers\host ;

function enforceTrustedHosts( ServerRequestInterface $request , array $trustedHosts ) : bool ;
```

Sibling defense against **Host Header attacks** — the class of attacks where an attacker forges the `Host:` header to make your app generate URLs pointing to their domain (poisoned password-reset emails) or to bypass virtual-host routing.

### The concrete attack

Your app sends password-reset emails containing :

```php
$resetLink = $request->getUri()->getScheme()
           . '://' . $request->getHeaderLine( 'Host' )
           . '/reset/' . $token ;
```

An attacker requests a password reset for someone else's account, but with `Host: attacker.com` in their request. Your app generates `https://attacker.com/reset/<real-token>` and emails it to the victim. The victim clicks. The token leaks to the attacker. Account compromised.

With `enforceTrustedHosts()`, requests carrying a Host that's not on your allowlist are rejected before any handler runs :

```php
use function oihana\middleware\helpers\host\enforceTrustedHosts ;

if ( !enforceTrustedHosts( $request , [
    'example.com' ,
    '*.example.com' ,
    'admin.internal' ,
] ) )
{
    return $responseFactory->createResponse( 400 ) ;
}
```

### Matching rules

Per RFC 9110 §7.2 — `Host` is case-insensitive.

| Allowlist entry | Matches |
| :--- | :--- |
| `example.com` | Exact match : `Host: example.com` or `Host: example.com:8080` (port stripped). |
| `*.example.com` | Any subdomain : `api.example.com`, `staging.api.example.com`. **Does NOT match the apex `example.com`** — list it explicitly to accept. |
| `*.*.example.com` | **Rejected as invalid** — nested wildcards have no agreed semantics. |
| `api.*.com` | **Rejected as invalid** — mid-string wildcards have no agreed semantics. |

### Behaviour matrix

| Condition | Returns |
| :--- | :--- |
| Empty allowlist | `true` (no-op : guard disabled, NOT block-everything) |
| Missing `Host` header | `false` (HTTP/1.1 requires Host) |
| Malformed `Host` (multiple unbracketed colons, unclosed IPv6 bracket) | `false` (defensive) |
| Host matches an allowlist entry | `true` |
| Host does not match any entry | `false` |

The **empty-allowlist = no-op** behaviour is intentional safety net. A misconfigured deployment that wires the middleware but forgets to populate the allowlist would otherwise lock every user out — the no-op fails open instead, which is the right trade-off for a missing config (you'll notice ; you wouldn't necessarily notice a working but bypassed defense).

### Where this fits in the defense stack

| Layer | Configures | Purpose |
| :--- | :--- | :--- |
| **nginx / Apache `server_name` blocks** | Per-vhost routing | Reject at the edge, before the request reaches PHP-FPM. |
| **`enforceTrustedHosts()`** | App-side allowlist | Defense-in-depth in case the reverse-proxy is misconfigured or absent. |

If your reverse-proxy already enforces strict `server_name` matching and never forwards unknown hosts, the helper is redundant for the production environment. It still earns its keep in development (where you don't typically run a reverse-proxy) and as a fallback if the proxy config drifts.

## See also

- [Getting started](getting-started.md) — general PSR-15 middleware wiring.
- [RFC 9110 §15.5.14](https://www.rfc-editor.org/rfc/rfc9110#status.413) — `413 Payload Too Large` semantics.
- nginx [`client_max_body_size`](https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size) — upstream sibling configuration.
