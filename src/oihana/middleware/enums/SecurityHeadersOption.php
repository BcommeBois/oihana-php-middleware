<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Option keys accepted by {@see \oihana\middleware\helpers\security\withSecurityHeaders()}.
 *
 * Exposed as typed constants so consumers never need to spell the option
 * strings by hand — matching the "zero magic strings" convention of the
 * `oihana/php-*` ecosystem.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class SecurityHeadersOption
{
    use ConstantsTrait ;

    /**
     * `hsts` — `Strict-Transport-Security` max-age in seconds (`int|null`). Omitted / `null` / `0` produces no HSTS header.
     */
    public const string HSTS = 'hsts' ;

    /**
     * `hstsIncludeSubdomains` — adds the `; includeSubDomains` token to the HSTS header (`bool`, default `true` when HSTS is emitted).
     */
    public const string HSTS_INCLUDE_SUBDOMAINS = 'hstsIncludeSubdomains' ;

    /**
     * `hstsPreload` — adds the `; preload` token to the HSTS header (`bool`, default `false`).
     */
    public const string HSTS_PRELOAD = 'hstsPreload' ;

    /**
     * `frameOptions` — value of `X-Frame-Options` (`string|null`). Use {@see FrameOptions} constants.
     */
    public const string FRAME_OPTIONS = 'frameOptions' ;

    /**
     * `contentTypeNosniff` — when `true`, emits `X-Content-Type-Options: nosniff` (`bool`, default `false`).
     */
    public const string CONTENT_TYPE_NOSNIFF = 'contentTypeNosniff' ;

    /**
     * `referrerPolicy` — value of `Referrer-Policy` (`string|null`). Use {@see ReferrerPolicy} constants.
     */
    public const string REFERRER_POLICY = 'referrerPolicy' ;

    /**
     * `csp` — `Content-Security-Policy` value: `string`, directive `array` (forwarded to `buildCspHeader()`), or `null`.
     */
    public const string CSP = 'csp' ;

    /**
     * `cspReportOnly` — when `true`, emits `Content-Security-Policy-Report-Only` instead of `Content-Security-Policy` (`bool`, default `false`).
     */
    public const string CSP_REPORT_ONLY = 'cspReportOnly' ;

    /**
     * `coop` — value of `Cross-Origin-Opener-Policy` (`string|null`). Use {@see CrossOriginOpenerPolicy} constants.
     */
    public const string COOP = 'coop' ;

    /**
     * `coep` — value of `Cross-Origin-Embedder-Policy` (`string|null`). Use {@see CrossOriginEmbedderPolicy} constants.
     */
    public const string COEP = 'coep' ;

    /**
     * `corp` — value of `Cross-Origin-Resource-Policy` (`string|null`). Use {@see CrossOriginResourcePolicy} constants.
     */
    public const string CORP = 'corp' ;

    /**
     * `permissionsPolicy` — `Permissions-Policy` value: `string`, feature `array` (forwarded to `buildPermissionsPolicyHeader()`), or `null`.
     */
    public const string PERMISSIONS_POLICY = 'permissionsPolicy' ;
}
