<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\csrf ;

use function oihana\core\encoding\base64UrlEncode ;

/**
 * Verifies a stateless CSRF token issued by {@see generateCsrfToken()},
 * following the double-submit pattern.
 *
 * Returns `true` only when **all** of these hold:
 *
 * 1. The cookie token and the submitted token are byte-identical
 *    (constant-time comparison via `hash_equals()`). This is the
 *    "double-submit" cornerstone: an attacker on a cross-site origin
 *    can submit a token via JS but cannot read the victim's cookie, so
 *    the two cannot match.
 * 2. The token has the expected `<id>.<exp>.<sig>` shape with three
 *    non-empty parts.
 *
 * 3. The HMAC-SHA256 signature of `<id>.<exp>` keyed by `$secret`
 *    matches `<sig>` (constant-time comparison). This catches forged
 *    tokens written by an attacker who can set the cookie but does not
 *    know the secret.
 * 4. The `<exp>` field is either `'0'` (no expiry) or a Unix timestamp
 *    in the future.
 *
 * Returns `false` on any other input — never throws. This makes the
 * helper safe to plug directly into a middleware as the sole allow/deny
 * gate:
 *
 * ```php
 * if ( !verifyCsrfToken( $cookie , $submitted , $secret ) )
 * {
 *     return new Response( 403 ) ;
 * }
 * ```
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\csrf\verifyCsrfToken ;
 * use oihana\middleware\enums\CsrfField ;
 *
 * $cookie    = $request->getCookieParams()[ CsrfField::COOKIE_NAME ] ?? '' ;
 * $submitted = $request->getHeaderLine( CsrfField::HEADER_NAME ) ;
 *
 * if ( !verifyCsrfToken( $cookie , $submitted , $appSecret ) )
 * {
 *     return $response->withStatus( 403 ) ;
 * }
 * ```
 *
 * @param string $cookieToken The token read from the request cookie.
 * @param string $submittedToken The token submitted by the client in a header / form field.
 * @param string $secret The HMAC key. Must be non-empty.
 *
 * @return bool `true` when the token is valid, `false` on any other input or any check failure.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\csrf
 */
function verifyCsrfToken( string $cookieToken , string $submittedToken , string $secret ) :bool
{
    if ( $secret === '' || $cookieToken === '' || $submittedToken === '' )
    {
        return false ;
    }

    // 1. Double-submit: both tokens must match (constant-time).
    if ( !hash_equals( $cookieToken , $submittedToken ) )
    {
        return false ;
    }

    // 2. Parse the wire format <id>.<exp>.<sig>.
    $parts = explode( '.' , $cookieToken ) ;

    if ( count( $parts ) !== 3 )
    {
        return false ;
    }

    [ $id , $exp , $sig ] = $parts ;

    if ( $id === '' || $exp === '' || $sig === '' )
    {
        return false ;
    }

    // 3. Verify the HMAC signature (constant-time).
    $expectedSig = base64UrlEncode( hash_hmac( 'sha256' , $id . '.' . $exp , $secret , true ) ) ;

    if ( !hash_equals( $expectedSig , $sig ) )
    {
        return false ;
    }

    // 4. Verify the TTL when present.
    if ( $exp !== '0' && (int) $exp < time() )
    {
        return false ;
    }

    return true ;
}
