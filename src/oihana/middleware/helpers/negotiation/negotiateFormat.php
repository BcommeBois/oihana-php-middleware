<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\negotiation ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\enums\Param ;

/**
 * Resolves the short format token a client asks for — `json`, `csv`, `xml`… — from the `Accept`
 * header, with an optional query-string override.
 *
 * Downstream handlers rarely branch on a full media type : they pick a serializer from a short
 * token. This helper walks that last step, and adds the shortcut most APIs end up exposing anyway.
 *
 * Three rules, in order :
 *
 * 1. **`Accept` header** — negotiated by {@see negotiateMimeType()} against the keys of
 *    `$formatByMime`, then the winning media type is mapped to its token. Quality values,
 *    wildcards and `q=0` refusals are honoured, since the matching is the same.
 * 2. **`?format=` query parameter** — wins over the negotiated token, **only when its value is one
 *    of the tokens of the map**. An unknown value is ignored rather than propagated : a token that
 *    never came from the server has no serializer behind it, and letting it through only moves the
 *    failure further down.
 * 3. **`$default`** — returned when nothing matched, and when an unknown override is the only
 *    thing on offer.
 *
 * Pass `$queryKey = null` to drop the override entirely and negotiate on the `Accept` header alone.
 *
 * The map is ordered : its first entry is the server preference, which breaks quality ties and
 * answers a bare wildcard. Several media types may share a token — `text/xml` and
 * `application/xml` both mapping to `xml` is a legitimate map.
 *
 * Out of scope, by design : stamping the response `Content-Type` (only the handler knows what it
 * really rendered), and refusing an unacceptable request. This helper returns `$default` so the
 * caller decides whether that deserves a `406 Not Acceptable`.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\negotiation\negotiateFormat ;
 *
 * $formats =
 * [
 *     'application/json' => 'json' ,
 *     'text/csv'         => 'csv'  ,
 *     'application/xml'  => 'xml'  ,
 * ] ;
 *
 * $format = negotiateFormat( $request , $formats ) ;
 *
 * // Client sends `Accept: application/json;q=0.9, text/csv`
 * //   → returns 'csv' (q=1.0 wins over q=0.9)
 * // Client sends `Accept: *​/*`
 * //   → returns 'json' (first entry of the map — the server preference)
 * // Client sends `Accept: text/html`
 * //   → returns null (no acceptable format — answer 406, or fall back)
 * // Client calls `?format=xml` with `Accept: application/json`
 * //   → returns 'xml' (the explicit override wins)
 * // Client calls `?format=yaml` with `Accept: application/json`
 * //   → returns 'json' (unknown token ignored)
 * ```
 *
 * @param ServerRequestInterface $request      The incoming PSR-7 request.
 * @param array<string,string>   $formatByMime Map of media type => short format token, in server preference order.
 * @param string|null            $queryKey     Query parameter carrying an explicit override, or `null` to disable it. Defaults to {@see Param::FORMAT}.
 * @param string|null            $default      Returned when neither the `Accept` header nor a valid override resolves a token. Defaults to `null`.
 *
 * @return string|null The selected format token, or `$default`.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\negotiation
 */
function negotiateFormat
(
    ServerRequestInterface $request      ,
    array                  $formatByMime ,
    ?string                $queryKey     = Param::FORMAT ,
    ?string                $default      = null ,
)
: ?string
{
    $mime = negotiateMimeType( $request , array_keys( $formatByMime ) ) ;

    $format = $mime !== null ? $formatByMime[ $mime ] : $default ;

    if( $queryKey === null )
    {
        return $format ;
    }

    $override = $request->getQueryParams()[ $queryKey ] ?? null ;

    return is_string( $override ) && in_array( $override , $formatByMime , true ) ? $override : $format ;
}
