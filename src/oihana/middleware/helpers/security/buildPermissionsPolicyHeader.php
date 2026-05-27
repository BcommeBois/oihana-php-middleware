<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\security ;

use InvalidArgumentException ;

/**
 * Builds a `Permissions-Policy` header value from an associative array
 * of feature directives.
 *
 * Each entry maps a policy-controlled feature name (e.g. `camera`,
 * `geolocation`, `payment`) to its allowlist. Four accepted forms for
 * the allowlist value, in increasing expressiveness :
 *
 * - `false` — explicit deny, emitted as `()`.
 * - `true` or the string `'*'` — allow all origins, emitted as `*`
 *   (the only allowlist form that does NOT use parentheses).
 * - `'self'` — allow only the same origin, emitted as `(self)`.
 * - `string` starting with `(` — passed through verbatim (escape hatch
 *   for advanced syntax not directly supported by the smart forms).
 * - `string` carrying a single origin (e.g. `'https://stripe.com'`) —
 *   emitted as a quoted single-item list `("https://stripe.com")`.
 * - `array<int, string>` — composed item-by-item : the token `'self'`
 *   stays unquoted, every other entry is auto-quoted as an origin.
 *   `['self', 'https://stripe.com']` ⇒ `(self "https://stripe.com")`.
 *
 * Feature names are joined with `', '` per the structured-headers
 * syntax. Empty input yields an empty string — the caller can then
 * skip emitting the header altogether.
 *
 * Use the {@see \oihana\middleware\enums\PermissionsPolicyFeature}
 * constants for the keys to avoid magic strings — but raw feature
 * names are accepted too, so callers can target features not yet
 * exposed by the enum.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\security\buildPermissionsPolicyHeader;
 * use oihana\middleware\enums\PermissionsPolicyFeature;
 *
 * buildPermissionsPolicyHeader([
 *     PermissionsPolicyFeature::GEOLOCATION => false,                          // deny
 *     PermissionsPolicyFeature::CAMERA      => 'self',                         // same-origin only
 *     PermissionsPolicyFeature::PAYMENT     => [ 'self', 'https://stripe.com' ], // self + a partner
 *     PermissionsPolicyFeature::FULLSCREEN  => '*',                            // allow all
 * ]);
 * // => 'geolocation=(), camera=(self), payment=(self "https://stripe.com"), fullscreen=*'
 * ```
 *
 * @param array<string, bool|string|array<int, string>> $directives Map of feature name => allowlist.
 *
 * @return string The Permissions-Policy header value, or `''` when no directive is supplied.
 *
 * @throws InvalidArgumentException On empty feature name, unsupported value type, or empty source string in an array.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\security
 */
function buildPermissionsPolicyHeader( array $directives ) : string
{
    if ( $directives === [] )
    {
        return '' ;
    }

    $parts = [] ;

    foreach ( $directives as $feature => $allowlist )
    {
        if ( !is_string( $feature ) || $feature === '' )
        {
            throw new InvalidArgumentException
            (
                'Permissions-Policy feature name must be a non-empty string.'
            ) ;
        }

        $parts[] = $feature . '=' . formatPermissionsPolicyAllowlist( $allowlist , $feature ) ;
    }

    return implode( ', ' , $parts ) ;
}

/**
 * Formats a single allowlist value into its structured-headers
 * representation. Internal — only consumed by
 * {@see buildPermissionsPolicyHeader()} but kept package-public for
 * testability and for callers that want to compose headers manually.
 *
 * Quoting rule for array items : the bare token `'self'` is emitted
 * unquoted (per the spec), every other string is auto-quoted as an
 * origin. The caller is responsible for the validity of the origin
 * itself — the helper only formats.
 *
 * @param bool|string|array<int, string> $allowlist The raw allowlist value.
 * @param string                         $feature   The feature name, used only for error messages.
 *
 * @return string The formatted allowlist (`()`, `*`, `(self)`, `("https://x")`, `(self "https://x")`, or a raw passthrough).
 *
 * @throws InvalidArgumentException On unsupported value type or empty origin string in an array.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\security
 */
function formatPermissionsPolicyAllowlist( bool|string|array $allowlist , string $feature ) : string
{
    if ( $allowlist === false )
    {
        return '()' ;
    }

    if ( $allowlist === true || $allowlist === '*' )
    {
        return '*' ;
    }

    if ( is_string( $allowlist ) )
    {
        if ( $allowlist === 'self' )
        {
            return '(self)' ;
        }

        // Raw passthrough — caller already formatted the allowlist.
        if ( $allowlist !== '' && $allowlist[ 0 ] === '(' )
        {
            return $allowlist ;
        }

        if ( $allowlist === '' )
        {
            throw new InvalidArgumentException( sprintf
            (
                'Permissions-Policy feature `%s` allowlist string must be non-empty.' ,
                $feature ,
            ) ) ;
        }

        // Treat as a single origin to quote.
        return '("' . $allowlist . '")' ;
    }

    // Array — compose token by token.
    if ( $allowlist === [] )
    {
        return '()' ;
    }

    $tokens = [] ;

    foreach ( $allowlist as $item )
    {
        if ( !is_string( $item ) || $item === '' )
        {
            throw new InvalidArgumentException( sprintf
            (
                'Permissions-Policy feature `%s` allowlist items must be non-empty strings.' ,
                $feature ,
            ) ) ;
        }

        $tokens[] = ( $item === 'self' ) ? 'self' : '"' . $item . '"' ;
    }

    return '(' . implode( ' ' , $tokens ) . ')' ;
}
