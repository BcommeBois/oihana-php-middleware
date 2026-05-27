<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\csrf ;

use InvalidArgumentException ;

use function oihana\core\encoding\base64UrlEncode ;
use function oihana\core\encoding\randomBase64Url ;

/**
 * Generates a stateless CSRF token, ready to be sent as a cookie AND
 * echoed back by the client in a header / form field (double-submit
 * pattern).
 *
 * The token is **signed** with HMAC-SHA256 keyed by `$secret`, so even
 * an attacker who can write the cookie via a partial XSS cannot forge a
 * valid token without knowing the secret. The token also carries an
 * optional **absolute expiry**, so a leaked token has a bounded lifetime.
 *
 * Wire format:
 *
 * ```
 * <id>.<exp>.<sig>
 * ```
 *
 * - `<id>` — 128-bit base64url-encoded random identifier from
 *   {@see \oihana\core\encoding\randomBase64Url()} (cryptographic CSPRNG).
 * - `<exp>` — absolute Unix timestamp at which the token expires, or
 *   `'0'` for "no expiry".
 * - `<sig>` — base64url-encoded HMAC-SHA256 of `<id>.<exp>` keyed by
 *   `$secret`.
 *
 * All three parts use the URL-safe alphabet `[A-Za-z0-9_-]`, so the
 * token is safe to put in cookies, headers, hidden form fields and URLs.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\csrf\generateCsrfToken ;
 *
 * // 1-hour TTL — recommended for browser forms.
 * $token = generateCsrfToken( $appSecret , ttlSeconds: 3600 ) ;
 *
 * // No expiry — use sparingly (long-lived API clients, etc.).
 * $token = generateCsrfToken( $appSecret ) ;
 * ```
 *
 * @param string $secret The HMAC key. Must be non-empty.
 * @param int|null $ttlSeconds Token lifetime in seconds. `null` or `0` ⇒ no expiry (token never expires by TTL — caller must rotate by other means).
 *
 * @return string The signed CSRF token.
 *
 * @throws InvalidArgumentException When `$secret` is empty.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\csrf
 */
function generateCsrfToken( string $secret , ?int $ttlSeconds = null ) :string
{
    if ( $secret === '' )
    {
        throw new InvalidArgumentException
        (
            'generateCsrfToken: $secret must be a non-empty string.'
        ) ;
    }

    $id = randomBase64Url( 16 ) ; // 128 bits, 22 base64url chars

    $exp = ( $ttlSeconds !== null && $ttlSeconds > 0 )
        ? (string) ( time() + $ttlSeconds )
        : '0' ;

    $payload = $id . '.' . $exp ;
    $sig     = base64UrlEncode( hash_hmac( 'sha256' , $payload , $secret , true ) ) ;

    return $payload . '.' . $sig ;
}
