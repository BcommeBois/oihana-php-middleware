<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\negotiation ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\enums\http\HttpHeader ;

use function oihana\http\helpers\negotiation\negotiate ;

/**
 * Selects the best server-side content encoding for an incoming
 * PSR-7 request, based on the `Accept-Encoding` header.
 *
 * Sibling of {@see negotiateMimeType()} targeting the
 * `Accept-Encoding` header. Useful when the server can serve a
 * response in multiple compressed forms (e.g. `br`, `gzip`,
 * `identity`) and needs to pick the one the client supports.
 *
 * Per RFC 9110 §12.5.3, a missing `Accept-Encoding` header signals
 * the client accepts any encoding (typically `identity`). This
 * helper does NOT inject that default — when the header is absent
 * the negotiation returns `$default` (or `null`). Callers wanting
 * RFC-compliant behaviour for absent header should pass
 * `'identity'` (or whatever uncompressed format they ship) as
 * `$default`.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\negotiation\negotiateEncoding ;
 *
 * $encoding = negotiateEncoding(
 *     $request ,
 *     [ 'br' , 'gzip' , 'identity' ] ,
 *     'identity' ,
 * ) ;
 *
 * // Client sends `Accept-Encoding: br, gzip;q=0.8`
 * //   → returns 'br'
 * // No Accept-Encoding header
 * //   → returns 'identity' (default)
 * ```
 *
 * @param ServerRequestInterface $request   The incoming PSR-7 request.
 * @param string[]               $supported Server-side list of encoding tokens, in preference order.
 * @param string|null            $default   Returned when no `$supported` value matches.
 *
 * @return string|null The selected encoding, or `$default`.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\negotiation
 */
function negotiateEncoding
(
    ServerRequestInterface $request ,
    array                  $supported ,
    ?string                $default = null ,
)
: ?string
{
    return negotiate( $request->getHeaderLine( HttpHeader::ACCEPT_ENCODING ) , $supported , $default ) ;
}
