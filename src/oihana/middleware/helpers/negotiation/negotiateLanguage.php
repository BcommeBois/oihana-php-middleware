<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\negotiation ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\enums\http\HttpHeader ;

use function oihana\http\helpers\negotiation\negotiate ;

/**
 * Selects the best server-side language tag for an incoming PSR-7
 * request, based on the `Accept-Language` header.
 *
 * Sibling of {@see negotiateMimeType()} targeting the
 * `Accept-Language` header instead of `Accept`. Delegates the actual
 * matching to {@see \oihana\http\helpers\negotiation\negotiate()}
 * which honours RFC 9110 §12.5.4 quality values and the universal
 * wildcard.
 *
 * Note : this helper matches language tags **as strings**, not by
 * subtag inheritance. `fr-CA` and `fr` are distinct candidates — if
 * the client sends `Accept-Language: fr` and the server lists
 * `[ 'fr-CA' , 'en' ]`, the helper returns the default (no exact
 * match). For full BCP 47 subtag lookup, layer a dedicated lib on
 * top.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\negotiation\negotiateLanguage ;
 *
 * $locale = negotiateLanguage( $request , [ 'en' , 'fr' , 'de' ] , 'en' ) ;
 *
 * // Client sends `Accept-Language: fr-CH,fr;q=0.8,en;q=0.5`
 * //   → returns 'fr' (matches the second entry — first wins among accepted)
 * // No Accept-Language header
 * //   → returns 'en' (default)
 * ```
 *
 * @param ServerRequestInterface $request   The incoming PSR-7 request.
 * @param string[]               $supported Server-side list of language tags, in preference order.
 * @param string|null            $default   Returned when no `$supported` value matches.
 *
 * @return string|null The selected language tag, or `$default`.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\negotiation
 */
function negotiateLanguage
(
    ServerRequestInterface $request   ,
    array                  $supported ,
    ?string                $default   = null ,
)
: ?string
{
    return negotiate( $request->getHeaderLine( HttpHeader::ACCEPT_LANGUAGE ) , $supported , $default ) ;
}
