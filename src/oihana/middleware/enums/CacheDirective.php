<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Standard `Cache-Control` directive names defined by
 * [RFC 9111](https://www.rfc-editor.org/rfc/rfc9111.html) (the
 * primary HTTP caching specification) and
 * [RFC 5861](https://www.rfc-editor.org/rfc/rfc5861.html)
 * (`stale-while-revalidate` / `stale-if-error` extensions).
 *
 * Used as keys when composing a `Cache-Control` header value via
 * {@see \oihana\middleware\helpers\cache\buildCacheControl()}.
 *
 * Two value shapes are accepted by the helper, depending on the
 * directive type :
 *
 * - **Flag directives** (no argument) — `bool`. `true` ⇒ directive
 *   emitted bare ; `false` ⇒ directive silently omitted. Examples :
 *   `PUBLIC`, `PRIVATE`, `NO_CACHE`, `NO_STORE`, `MUST_REVALIDATE`,
 *   `IMMUTABLE`, etc.
 * - **Delta-seconds directives** (require an integer argument) —
 *   non-negative `int`. Negative values are silently omitted.
 *   Examples : `MAX_AGE`, `S_MAXAGE`, `STALE_WHILE_REVALIDATE`,
 *   `STALE_IF_ERROR`.
 *
 * The vocabulary here is **open** — `buildCacheControl()` accepts raw
 * directive name strings too, so callers can target directives not
 * yet exposed by this enum (e.g. emerging extensions or
 * vendor-specific tokens).
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class CacheDirective
{
    use ConstantsTrait ;

    // -------------------------------------------------------------------------
    // Response freshness (delta-seconds — require an int argument)
    // -------------------------------------------------------------------------

    /**
     * `max-age` — maximum freshness lifetime, in seconds, applied by every cache (RFC 9111 §5.2.2.1).
     */
    public const string MAX_AGE = 'max-age' ;

    /**
     * `s-maxage` — maximum freshness lifetime for SHARED caches (CDN, reverse proxies) only. Overrides `max-age` for them (RFC 9111 §5.2.2.10).
     */
    public const string S_MAXAGE = 's-maxage' ;

    /**
     * `stale-while-revalidate` — RFC 5861 §3. Number of seconds after expiry during which a stale response MAY be served while the cache asynchronously revalidates.
     */
    public const string STALE_WHILE_REVALIDATE = 'stale-while-revalidate' ;

    /**
     * `stale-if-error` — RFC 5861 §4. Number of seconds after expiry during which a stale response MAY be served if the origin fails to revalidate.
     */
    public const string STALE_IF_ERROR = 'stale-if-error' ;

    // -------------------------------------------------------------------------
    // Caching scope (flag directives)
    // -------------------------------------------------------------------------

    /**
     * `public` — explicitly mark the response as cacheable by any cache, even when default rules would not allow it (RFC 9111 §5.2.2.9).
     */
    public const string PUBLIC = 'public' ;

    /**
     * `private` — response is for a single user ; only the user's private cache (typically the browser) may store it (RFC 9111 §5.2.2.7).
     */
    public const string PRIVATE = 'private' ;

    // -------------------------------------------------------------------------
    // Cache controls (flag directives)
    // -------------------------------------------------------------------------

    /**
     * `no-cache` — caches MUST revalidate with the origin before serving the response (RFC 9111 §5.2.2.4). NOT the same as `no-store`.
     */
    public const string NO_CACHE = 'no-cache' ;

    /**
     * `no-store` — caches MUST NOT store any part of the request or response (RFC 9111 §5.2.2.5). The strictest privacy directive.
     */
    public const string NO_STORE = 'no-store' ;

    /**
     * `must-revalidate` — once stale, the response MUST be revalidated with the origin (no serving stale content even on origin failure) (RFC 9111 §5.2.2.2).
     */
    public const string MUST_REVALIDATE = 'must-revalidate' ;

    /**
     * `proxy-revalidate` — same as `must-revalidate` but for SHARED caches only (RFC 9111 §5.2.2.8).
     */
    public const string PROXY_REVALIDATE = 'proxy-revalidate' ;

    /**
     * `must-understand` — RFC 9111 §5.2.2.3. Cache MUST understand the semantics of the status code or refuse to store the response.
     */
    public const string MUST_UNDERSTAND = 'must-understand' ;

    /**
     * `no-transform` — intermediaries (proxies, CDNs) MUST NOT modify the payload (RFC 9111 §5.2.2.6). Prevents lossy recompression of images, etc.
     */
    public const string NO_TRANSFORM = 'no-transform' ;

    /**
     * `immutable` — RFC 8246. The response body will not change for the freshness lifetime, so caches SHOULD NOT revalidate even on a user-triggered reload.
     */
    public const string IMMUTABLE = 'immutable' ;
}
