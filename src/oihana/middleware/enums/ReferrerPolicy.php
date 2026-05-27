<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Valid values for the `Referrer-Policy` HTTP response header.
 *
 * Controls how much information about the originating page is sent in
 * the `Referer` request header when a user navigates to another origin.
 *
 * See the W3C Referrer Policy specification:
 * https://www.w3.org/TR/referrer-policy/
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class ReferrerPolicy
{
    use ConstantsTrait ;

    /**
     * `no-referrer` — no `Referer` header is ever sent.
     */
    public const string NO_REFERRER = 'no-referrer' ;

    /**
     * `no-referrer-when-downgrade` — full URL on same-protocol navigations, nothing on HTTPS → HTTP.
     */
    public const string NO_REFERRER_WHEN_DOWNGRADE = 'no-referrer-when-downgrade' ;

    /**
     * `origin` — only the origin (scheme + host + port) is sent.
     */
    public const string ORIGIN = 'origin' ;

    /**
     * `origin-when-cross-origin` — full URL on same-origin navigations, only the origin on cross-origin.
     */
    public const string ORIGIN_WHEN_CROSS_ORIGIN = 'origin-when-cross-origin' ;

    /**
     * `same-origin` — full URL on same-origin navigations, nothing on cross-origin.
     */
    public const string SAME_ORIGIN = 'same-origin' ;

    /**
     * `strict-origin` — origin only, and nothing on HTTPS → HTTP downgrades.
     */
    public const string STRICT_ORIGIN = 'strict-origin' ;

    /**
     * `strict-origin-when-cross-origin` — full URL on same-origin, origin on cross-origin (same protocol), nothing on HTTPS → HTTP. Default in modern browsers.
     */
    public const string STRICT_ORIGIN_WHEN_CROSS_ORIGIN = 'strict-origin-when-cross-origin' ;

    /**
     * `unsafe-url` — full URL is always sent, including credentials and path. Strongly discouraged.
     */
    public const string UNSAFE_URL = 'unsafe-url' ;
}
