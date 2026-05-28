<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\negotiation ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\enums\http\HttpHeader ;

use function oihana\http\helpers\negotiation\negotiate ;

/**
 * Selects the best server-side charset for an incoming PSR-7 request,
 * based on the `Accept-Charset` header.
 *
 * Sibling of {@see negotiateMimeType()} targeting the
 * `Accept-Charset` header.
 *
 * **Note** : `Accept-Charset` is deprecated by RFC 9110 §12.5.2 —
 * modern clients (every mainstream browser) no longer send it,
 * relying instead on the `charset` parameter of the response
 * `Content-Type`. The helper remains useful for non-browser HTTP
 * clients (curl scripts, legacy APIs) that still rely on it ; for
 * everything else, just set `Content-Type: text/html; charset=utf-8`
 * and move on.
 *
 * @param ServerRequestInterface $request   The incoming PSR-7 request.
 * @param string[]               $supported Server-side list of charset tokens, in preference order.
 * @param string|null            $default   Returned when no `$supported` value matches.
 *
 * @return string|null The selected charset, or `$default`.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\negotiation
 */
function negotiateCharset
(
    ServerRequestInterface $request ,
    array                  $supported ,
    ?string                $default = null ,
)
: ?string
{
    return negotiate( $request->getHeaderLine( HttpHeader::ACCEPT_CHARSET ) , $supported , $default ) ;
}
