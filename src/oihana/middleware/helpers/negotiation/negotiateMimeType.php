<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\negotiation ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\enums\http\HttpHeader ;

use function oihana\http\helpers\negotiation\negotiate ;

/**
 * Selects the best server-side MIME type for an incoming PSR-7 request.
 *
 * Reads the `Accept` header (or the empty string when absent) and
 * delegates the actual matching to
 * {@see negotiate}, which honours
 * RFC 7231 quality values (q-values) and the standard `Accept`
 * wildcards (the universal one and `type/*`). Quality `q=0` entries
 * are treated as explicit refusals — they are never selected.
 *
 * Convenience wrapper : a thin PSR-7 adapter for the underlying
 * pure-string negotiation helper. Power users can call
 * {@see negotiate} directly to
 * negotiate `Accept-Language`, `Accept-Encoding`, `Accept-Charset`,
 * or any other `Accept*` header.
 *
 * @param ServerRequestInterface $request   The incoming PSR-7 request.
 * @param string[]               $supported The server-side list of MIME types, in preference order (ties broken by this order).
 * @param string|null            $default   Returned when no `$supported` value matches the `Accept` header. Defaults to `null`.
 *
 * @return string|null The selected MIME type, or `$default`.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\negotiation\negotiateMimeType ;
 *
 * $mime = negotiateMimeType( $request ,
 * [
 *     'application/json' ,
 *     'text/html' ,
 *     'text/csv' ,
 * ] ,
 * 'application/json' ) ;
 *
 * // Client sent `Accept: text/html;q=0.9, application/json`
 * //   → returns 'application/json' (q=1.0 wins over q=0.9)
 * // Client sent `Accept: text/*`
 * //   → returns 'text/html' (first available text/* match)
 * // No Accept header
 * //   → returns the default 'application/json'
 * ```
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\negotiation
 */
function negotiateMimeType
(
    ServerRequestInterface $request ,
    array                  $supported ,
    ?string                $default = null
)
: ?string
{
    return negotiate( $request->getHeaderLine( HttpHeader::ACCEPT ) , $supported , $default ) ;
}
