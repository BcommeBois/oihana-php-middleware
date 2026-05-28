<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\host ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\enums\http\HttpHeader ;

/**
 * Checks the incoming request `Host` header against an allowlist of
 * trusted host names, as a defense against Host Header attacks.
 *
 * Without this check, an attacker who can route an HTTP request to
 * your server (typically possible with virtual-host hosting, shared
 * load balancers, or misconfigured reverse proxies) can :
 *
 * - **Poison password-reset links** by sending `Host: attacker.com`,
 *   so the reset URL your app generates points to their domain.
 * - **Poison shared caches** by storing a response with the wrong
 *   `Host` and serving it back to legitimate users.
 * - **Bypass virtual-host routing** to reach internal endpoints.
 *
 * Matching rules (per RFC 9110 §7.2 — `Host` is case-insensitive) :
 *
 * - **Exact match** : `example.com` in the allowlist matches `Host:
 *   example.com`.
 * - **Wildcard subdomain** : `*.example.com` matches any direct or
 *   nested subdomain (`api.example.com`, `staging.api.example.com`)
 *   but NOT the apex itself — list the apex explicitly if you want
 *   it accepted. Nested wildcards (`*.*.example.com`) are rejected
 *   at match time as invalid patterns.
 * - **Port stripping** : the port portion of the incoming `Host`
 *   header (`example.com:8080`) is stripped before comparison.
 *   Allowlist entries should NOT carry a port — the helper is meant
 *   to gate names, not infrastructure ports.
 *
 * Edge cases :
 *
 * - **Empty allowlist** ⇒ returns `true` (no-op : guard is
 *   considered disabled rather than locking everyone out by
 *   accident).
 * - **Missing `Host` header** ⇒ returns `false` (HTTP/1.1 requires
 *   `Host` ; its absence is suspicious and the strict default
 *   rejects).
 * - **Malformed `Host` value** ⇒ returns `false` (defensive : if we
 *   can't trust the value, we don't trust the request).
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\host\enforceTrustedHosts ;
 *
 * if ( !enforceTrustedHosts( $request , [
 *     'example.com' ,
 *     '*.example.com' ,
 *     'admin.internal' ,
 * ] ) )
 * {
 *     return $responseFactory->createResponse( 400 ) ;
 * }
 * ```
 *
 * @param ServerRequestInterface $request      The incoming PSR-7 request.
 * @param string[]               $trustedHosts Allowlist of trusted host names. Exact names and `*.domain.tld` patterns accepted. Empty array ⇒ guard disabled.
 *
 * @return bool `true` when the request is trusted, `false` otherwise.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\host
 */
function enforceTrustedHosts( ServerRequestInterface $request , array $trustedHosts ) : bool
{
    if ( $trustedHosts === [] )
    {
        // No-op : empty allowlist means "guard disabled". Lock-everyone-out
        // by default would be too dangerous in apps that don't realise the
        // helper is wired without a config.
        return true ;
    }

    $hostHeader = $request->getHeaderLine( HttpHeader::HOST ) ;

    if ( $hostHeader === '' )
    {
        // HTTP/1.1 §3.4.1 requires a Host header — absence is suspicious.
        return false ;
    }

    $host = stripHostPort( $hostHeader ) ;

    if ( $host === '' )
    {
        // Malformed — can't trust.
        return false ;
    }

    foreach ( $trustedHosts as $trusted )
    {
        if ( !is_string( $trusted ) || $trusted === '' )
        {
            continue ;
        }

        if ( matchTrustedHost( $host , $trusted ) )
        {
            return true ;
        }
    }

    return false ;
}

/**
 * Strips the optional port portion of a `Host` header value, leaving
 * only the host name.
 *
 * Lowercases the result for case-insensitive comparison per RFC 9110
 * §7.2. Returns an empty string on malformed input (e.g. multiple
 * colons that don't look like an IPv6 literal).
 *
 * Internal helper. Public so callers that already have the host
 * value can reuse the normalisation.
 *
 * @param string $hostHeader The raw `Host` header value.
 *
 * @return string The lowercased host without port, or `''` on malformed input.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\host
 */
function stripHostPort( string $hostHeader ) : string
{
    $value = strtolower( trim( $hostHeader ) ) ;

    if ( $value === '' )
    {
        return '' ;
    }

    // IPv6 literal : "[::1]:8080" or "[::1]" — keep the bracketed part.
    if ( $value[ 0 ] === '[' )
    {
        $end = strpos( $value , ']' ) ;

        return $end === false ? '' : substr( $value , 0 , $end + 1 ) ;
    }

    // Multiple colons in a non-bracketed value can't be a legal host:port.
    // (A bare IPv6 without brackets would be malformed per RFC 9110.)
    $colons = substr_count( $value , ':' ) ;

    if ( $colons === 0 )
    {
        return $value ;
    }

    if ( $colons === 1 )
    {
        return substr( $value , 0 , strpos( $value , ':' ) ) ;
    }

    return '' ;
}

/**
 * Tests whether a normalised host name matches a single allowlist
 * pattern.
 *
 * Internal helper. Public so callers that already have a parsed host
 * can reuse the match logic.
 *
 * Both arguments are expected to be already lowercased. Wildcard
 * patterns must start with `*.` and contain no further `*` — nested
 * wildcards are rejected as invalid input.
 *
 * @param string $host    The lowercased host to check.
 * @param string $pattern The allowlist pattern (exact host or `*.domain.tld`).
 *
 * @return bool `true` when the host matches the pattern.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\host
 */
function matchTrustedHost( string $host , string $pattern ) : bool
{
    $pattern = strtolower( $pattern ) ;

    if ( !str_contains( $pattern , '*' ) )
    {
        return $host === $pattern ;
    }

    // Wildcard must be at the very start as `*.something`. Nested or
    // mid-string wildcards (`api.*.com`, `*.*.example.com`) are rejected
    // — they have no agreed semantics and would make matching ambiguous.
    if ( !str_starts_with( $pattern , '*.' ) || str_contains( substr( $pattern , 2 ) , '*' ) )
    {
        return false ;
    }

    $suffix = substr( $pattern , 1 ) ; // keep the leading dot, e.g. ".example.com"

    // `*.example.com` matches `api.example.com` but NOT `example.com` itself.
    // Callers wanting the apex must list it explicitly.
    if ( $host === substr( $suffix , 1 ) )
    {
        return false ;
    }

    return str_ends_with( $host , $suffix ) ;
}
