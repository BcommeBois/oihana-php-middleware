<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\security ;

use Psr\Http\Message\ResponseInterface ;

use oihana\middleware\enums\CrossOriginOpenerPolicy ;
use oihana\middleware\enums\CrossOriginResourcePolicy ;
use oihana\middleware\enums\FrameOptions ;
use oihana\middleware\enums\ReferrerPolicy ;
use oihana\middleware\enums\SecurityHeadersOption ;

/**
 * Applies an opinionated, secure-by-default set of HTTP security
 * response headers to a PSR-7 response.
 *
 * Thin wrapper over {@see withSecurityHeaders()} that pre-fills a
 * baseline considered "safe for most modern web applications" in
 * 2026 :
 *
 * - `Strict-Transport-Security: max-age=31536000; includeSubDomains`
 *   (1 year, subdomains included, no preload by default).
 * - `X-Frame-Options: DENY` (no embedding in `<iframe>`).
 * - `X-Content-Type-Options: nosniff` (no MIME sniffing).
 * - `Referrer-Policy: strict-origin-when-cross-origin` (modern default).
 * - `Cross-Origin-Opener-Policy: same-origin` (isolates the browsing
 *   context group, mitigates XS-Leaks / Spectre).
 * - `Cross-Origin-Resource-Policy: same-origin` (private API endpoints
 *   are not embeddable by third-party documents).
 *
 * Deliberately NOT included in the baseline (each is application-
 * specific and would otherwise break legitimate setups) :
 *
 * - `Cross-Origin-Embedder-Policy` — `REQUIRE_CORP` would break every
 *   third-party subresource that doesn't ship its own `CORP` header.
 *   Enable it explicitly once you've audited your subresources.
 * - `Content-Security-Policy` — requires per-app inventory of allowed
 *   script / style / image sources.
 * - `Permissions-Policy` — depends on which browser features the app
 *   actually uses.
 * - `Strict-Transport-Security; preload` — requires submission to the
 *   browser preload list (https://hstspreload.org).
 *
 * The `$overrides` array is merged on top of the baseline before being
 * forwarded to {@see withSecurityHeaders()}, so callers can tune any
 * default — or add CSP / Permissions-Policy / COEP — without losing
 * the rest of the baseline.
 *
 * PSR-7 immutable : a new response instance is returned, the input
 * response is never mutated.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\security\withDefaultSecurityBaseline ;
 * use oihana\middleware\enums\SecurityHeadersOption ;
 * use oihana\middleware\enums\CrossOriginEmbedderPolicy ;
 *
 * // Baseline as-is
 * $response = withDefaultSecurityBaseline( $response ) ;
 *
 * // Baseline + cross-origin isolation triad
 * $response = withDefaultSecurityBaseline( $response ,
 * [
 *     SecurityHeadersOption::COEP => CrossOriginEmbedderPolicy::REQUIRE_CORP ,
 *     SecurityHeadersOption::CSP  => [ 'default-src' => "'self'" ] ,
 * ]) ;
 *
 * // Loosen the HSTS max-age for staging
 * $response = withDefaultSecurityBaseline( $response ,
 * [
 *     SecurityHeadersOption::HSTS => 300 , // 5 minutes
 * ]) ;
 * ```
 *
 * @param ResponseInterface    $response  The PSR-7 response to augment.
 * @param array<string, mixed> $overrides Map of options keyed by {@see SecurityHeadersOption} constants — merged on top of the baseline.
 *
 * @return ResponseInterface A new response carrying the baseline (with overrides) security headers.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\security
 */
function withDefaultSecurityBaseline( ResponseInterface $response , array $overrides = [] ) : ResponseInterface
{
    $baseline =
    [
        SecurityHeadersOption::HSTS                 => 31536000 ,
        SecurityHeadersOption::FRAME_OPTIONS        => FrameOptions::DENY ,
        SecurityHeadersOption::CONTENT_TYPE_NOSNIFF => true ,
        SecurityHeadersOption::REFERRER_POLICY      => ReferrerPolicy::STRICT_ORIGIN_WHEN_CROSS_ORIGIN ,
        SecurityHeadersOption::COOP                 => CrossOriginOpenerPolicy::SAME_ORIGIN ,
        SecurityHeadersOption::CORP                 => CrossOriginResourcePolicy::SAME_ORIGIN ,
    ] ;

    return withSecurityHeaders( $response , array_replace( $baseline , $overrides ) ) ;
}
