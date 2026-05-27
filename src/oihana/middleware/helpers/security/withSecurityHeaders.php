<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\security ;

use Psr\Http\Message\ResponseInterface ;

use oihana\enums\http\HttpHeader ;
use oihana\middleware\enums\SecurityHeadersOption ;

/**
 * Applies the most common HTTP security response headers to a PSR-7 `Response`.
 *
 * Returns a new `ResponseInterface` (PSR-7 immutable) — the input
 * response is never mutated. Only the options actually provided produce
 * a header: omitting an option leaves the response untouched on that front.
 *
 * Supported options (keys exposed as typed constants in
 * {@see SecurityHeadersOption}):
 *
 * - `SecurityHeadersOption::HSTS` (int|null) — `Strict-Transport-Security`
 *   `max-age` in seconds. Omitted, `null` or `0` ⇒ no HSTS header.
 * - `SecurityHeadersOption::HSTS_INCLUDE_SUBDOMAINS` (bool) — default `true`.
 *   Adds the `; includeSubDomains` token when HSTS is emitted.
 * - `SecurityHeadersOption::HSTS_PRELOAD` (bool) — default `false`. Adds
 *   the `; preload` token when HSTS is emitted (requires inclusion in
 *   browser preload lists; cf. https://hstspreload.org).
 * - `SecurityHeadersOption::FRAME_OPTIONS` (string|null) — value of
 *   `X-Frame-Options` (use {@see \oihana\middleware\enums\FrameOptions}
 *   constants). Omitted, `null` or empty string ⇒ no header.
 * - `SecurityHeadersOption::CONTENT_TYPE_NOSNIFF` (bool) — default `false`.
 *   When `true`, emits `X-Content-Type-Options: nosniff`.
 * - `SecurityHeadersOption::REFERRER_POLICY` (string|null) — value of
 *   `Referrer-Policy` (use {@see \oihana\middleware\enums\ReferrerPolicy}
 *   constants). Omitted, `null` or empty string ⇒ no header.
 * - `SecurityHeadersOption::CSP` (string|array|null) — value of
 *   `Content-Security-Policy`. When an array is passed it is forwarded to
 *   {@see buildCspHeader()} to compose the value. Omitted, `null`, empty
 *   string or empty array ⇒ no header.
 * - `SecurityHeadersOption::CSP_REPORT_ONLY` (bool) — default `false`.
 *   When `true`, the CSP is emitted as
 *   `Content-Security-Policy-Report-Only` instead of
 *   `Content-Security-Policy` — useful to test a policy in production
 *   without enforcing it.
 *
 * Each emitted header replaces any pre-existing value on the response
 * (PSR-7 `withHeader` semantics). Pre-existing headers not touched by
 * the supplied options are preserved.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\security\withSecurityHeaders;
 * use oihana\middleware\enums\SecurityHeadersOption;
 * use oihana\middleware\enums\FrameOptions;
 * use oihana\middleware\enums\ReferrerPolicy;
 *
 * $response = withSecurityHeaders( $response , [
 *     SecurityHeadersOption::HSTS                 => 31536000,                                          // 1 year
 *     SecurityHeadersOption::FRAME_OPTIONS        => FrameOptions::DENY,
 *     SecurityHeadersOption::CONTENT_TYPE_NOSNIFF => true,
 *     SecurityHeadersOption::REFERRER_POLICY      => ReferrerPolicy::STRICT_ORIGIN_WHEN_CROSS_ORIGIN,
 *     SecurityHeadersOption::CSP                  => [ 'default-src' => "'self'" ],
 * ]) ;
 * ```
 *
 * @param ResponseInterface $response The PSR-7 response to augment.
 * @param array<string, mixed> $options Map of security-header options keyed by {@see SecurityHeadersOption} constants.
 *
 * @return ResponseInterface A new response with the security headers applied.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\security
 */
function withSecurityHeaders( ResponseInterface $response , array $options = [] ) :ResponseInterface
{
    // Strict-Transport-Security
    $hsts = $options[ SecurityHeadersOption::HSTS ] ?? null ;

    if ( is_int( $hsts ) && $hsts > 0 )
    {
        $value = 'max-age=' . $hsts ;

        if ( ( $options[ SecurityHeadersOption::HSTS_INCLUDE_SUBDOMAINS ] ?? true ) === true )
        {
            $value .= '; includeSubDomains' ;
        }

        if ( ( $options[ SecurityHeadersOption::HSTS_PRELOAD ] ?? false ) === true )
        {
            $value .= '; preload' ;
        }

        $response = $response->withHeader( HttpHeader::STRICT_TRANSPORT_SECURITY , $value ) ;
    }

    // X-Frame-Options
    $frameOptions = $options[ SecurityHeadersOption::FRAME_OPTIONS ] ?? null ;

    if ( is_string( $frameOptions ) && $frameOptions !== '' )
    {
        $response = $response->withHeader( HttpHeader::X_FRAME_OPTIONS , $frameOptions ) ;
    }

    // X-Content-Type-Options
    if ( ( $options[ SecurityHeadersOption::CONTENT_TYPE_NOSNIFF ] ?? false ) === true )
    {
        $response = $response->withHeader( HttpHeader::X_CONTENT_TYPE_OPTIONS , 'nosniff' ) ;
    }

    // Referrer-Policy
    $referrerPolicy = $options[ SecurityHeadersOption::REFERRER_POLICY ] ?? null ;

    if ( is_string( $referrerPolicy ) && $referrerPolicy !== '' )
    {
        $response = $response->withHeader( HttpHeader::REFERRER_POLICY , $referrerPolicy ) ;
    }

    // Content-Security-Policy (or Content-Security-Policy-Report-Only)
    $csp = $options[ SecurityHeadersOption::CSP ] ?? null ;

    if ( is_array( $csp ) )
    {
        $cspValue = buildCspHeader( $csp ) ;
    }
    elseif ( is_string( $csp ) )
    {
        $cspValue = $csp ;
    }
    else
    {
        $cspValue = '' ;
    }

    if ( $cspValue !== '' )
    {
        $headerName = ( $options[ SecurityHeadersOption::CSP_REPORT_ONLY ] ?? false ) === true
            ? HttpHeader::CONTENT_SECURITY_POLICY_REPORT_ONLY
            : HttpHeader::CONTENT_SECURITY_POLICY ;

        $response = $response->withHeader( $headerName , $cspValue ) ;
    }

    return $response ;
}
