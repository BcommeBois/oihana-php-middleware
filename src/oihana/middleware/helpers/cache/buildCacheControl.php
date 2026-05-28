<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\cache ;

use InvalidArgumentException ;

/**
 * Builds a `Cache-Control` header value from an associative array of
 * directives.
 *
 * Each entry maps a directive name (e.g. `max-age`, `public`,
 * `stale-while-revalidate`) to either an `int` (for delta-seconds
 * directives) or a `bool` (for flag directives).
 *
 * Accepted value shapes :
 *
 * - **`true`** — flag directive emitted bare (e.g. `public`).
 * - **`false`** — directive silently omitted. This is the canonical
 *   "off" semantics : a caller building a config map can set
 *   `[CacheDirective::PUBLIC => $isPublic]` without having to filter
 *   the array beforehand. Differs intentionally from
 *   {@see \oihana\middleware\helpers\security\buildCspHeader()}
 *   which throws on `false` — CSP directives have no meaningful
 *   "off" state, Cache-Control directives do.
 * - **non-negative `int`** — emitted as `directive=N` (delta-seconds
 *   form). Negative values are silently omitted, mirroring the
 *   `false` semantics.
 * - **`string`** — emitted as `directive=value` verbatim. Reserved
 *   for tokens like quoted form (`no-cache="Set-Cookie"`) — rare but
 *   legal per RFC 9111 §5.2.
 *
 * Directives are joined with `', '` per the RFC 9111 grammar. Empty
 * input yields an empty string — the caller can then skip emitting
 * the header entirely.
 *
 * Use the {@see \oihana\middleware\enums\CacheDirective} constants
 * for the keys to avoid magic strings ; raw directive name strings
 * are accepted too for emerging extensions or vendor tokens.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\cache\buildCacheControl;
 * use oihana\middleware\enums\CacheDirective;
 *
 * buildCacheControl([
 *     CacheDirective::PUBLIC                 => true,
 *     CacheDirective::MAX_AGE                => 3600,
 *     CacheDirective::S_MAXAGE               => 86400,
 *     CacheDirective::STALE_WHILE_REVALIDATE => 60,
 * ]);
 * // => 'public, max-age=3600, s-maxage=86400, stale-while-revalidate=60'
 * ```
 *
 * @param array<string, bool|int|string> $directives Map of directive name => value.
 *
 * @return string The `Cache-Control` header value, or `''` when no directive is supplied.
 *
 * @throws InvalidArgumentException On empty directive name or unsupported value type.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\cache
 */
function buildCacheControl( array $directives ) : string
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
                'Cache-Control directive name must be a non-empty string.'
            ) ;
        }

        if ( $value === true )
        {
            $parts[] = $name ;
            continue ;
        }

        if ( $value === false )
        {
            // Silent omit — canonical "off" semantics for Cache-Control flags.
            continue ;
        }

        if ( is_int( $value ) )
        {
            // Negative delta-seconds make no sense in Cache-Control ; silently
            // omit rather than emit a nonsensical "max-age=-1" that some caches
            // would treat as "always stale".
            if ( $value < 0 )
            {
                continue ;
            }

            $parts[] = $name . '=' . $value ;
            continue ;
        }

        if ( is_string( $value ) )
        {
            // Reserved for the rare quoted-string form (`no-cache="Set-Cookie"`).
            // Caller is responsible for any required quoting.
            $parts[] = $name . '=' . $value ;
            continue ;
        }

        throw new InvalidArgumentException( sprintf
        (
            'Cache-Control directive `%s` value must be bool, int or string. Got %s.' ,
            $name ,
            gettype( $value ) ,
        ) ) ;
    }

    return implode( ', ' , $parts ) ;
}
