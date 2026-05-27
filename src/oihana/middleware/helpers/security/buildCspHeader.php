<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\security ;

use InvalidArgumentException ;

/**
 * Builds a `Content-Security-Policy` header value from an associative
 * array of directives.
 *
 * Each entry maps a directive name (e.g. `default-src`, `script-src`)
 * to its sources. Sources can be expressed three ways:
 *
 * - As a `string` carrying one or more space-separated sources:
 *   `"'self' https://cdn.example.com"`.
 * - As a `list<string>` of sources: `["'self'", "https://cdn.example.com"]`.
 * - As the boolean `true` or an empty string `''`, for **flag directives**
 *   emitted bare (e.g. `upgrade-insecure-requests`).
 *
 * Directives are joined with `'; '` per the CSP grammar. Empty input
 * yields an empty string — the caller can then skip emitting the header
 * altogether.
 *
 * Use the {@see \oihana\middleware\enums\CspDirective} constants for the
 * keys to avoid magic strings — but raw strings are accepted too, so
 * callers can use less common directives not exposed by the enum.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\security\buildCspHeader;
 * use oihana\middleware\enums\CspDirective;
 *
 * buildCspHeader([
 *     CspDirective::DEFAULT_SRC               => "'self'",
 *     CspDirective::SCRIPT_SRC                => [ "'self'", 'https://cdn.example.com' ],
 *     CspDirective::IMG_SRC                   => "'self' data:",
 *     CspDirective::UPGRADE_INSECURE_REQUESTS => true,
 * ]);
 * // => "default-src 'self'; script-src 'self' https://cdn.example.com; img-src 'self' data:; upgrade-insecure-requests"
 * ```
 *
 * @param array<string, string|bool|array<int, string>> $directives Map of directive name => sources.
 *
 * @return string The CSP header value, or `''` when no directive is supplied.
 *
 * @throws InvalidArgumentException On unsupported value type, `false` value,
 *                                  empty directive name, or empty source.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\security
 */
function buildCspHeader( array $directives ) :string
{
    if ( $directives === [] )
    {
        return '' ;
    }

    $parts = [] ;

    foreach ( $directives as $name => $value )
    {
        if ( !is_string( $name ) || $name === '' )
        {
            throw new InvalidArgumentException
            (
                'CSP directive name must be a non-empty string.'
            ) ;
        }

        if ( $value === true || $value === '' )
        {
            // Flag directive — emitted bare (e.g. `upgrade-insecure-requests`).
            $parts[] = $name ;
            continue ;
        }

        if ( $value === false )
        {
            throw new InvalidArgumentException( sprintf
            (
                'CSP directive `%s` cannot have value `false`. Omit the key, use `true` for a flag, or pass sources.' ,
                $name ,
            ) ) ;
        }

        if ( is_string( $value ) )
        {
            $parts[] = $name . ' ' . $value ;
            continue ;
        }

        if ( is_array( $value ) )
        {
            $sources = [] ;

            foreach ( $value as $source )
            {
                if ( !is_string( $source ) || $source === '' )
                {
                    throw new InvalidArgumentException( sprintf
                    (
                        'CSP directive `%s` sources must be non-empty strings.' ,
                        $name ,
                    ) ) ;
                }

                $sources[] = $source ;
            }

            if ( $sources === [] )
            {
                // Empty list — treat as a flag (consistent with `true` / empty string).
                $parts[] = $name ;
                continue ;
            }

            $parts[] = $name . ' ' . implode( ' ' , $sources ) ;
            continue ;
        }

        throw new InvalidArgumentException( sprintf
        (
            'CSP directive `%s` value must be string, array<string>, or boolean true. Got %s.' ,
            $name ,
            gettype( $value ) ,
        ) ) ;
    }

    return implode( '; ' , $parts ) ;
}
