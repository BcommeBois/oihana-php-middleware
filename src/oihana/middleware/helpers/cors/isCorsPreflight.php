<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\cors ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\enums\http\HttpHeader ;
use oihana\enums\http\HttpMethod ;

/**
 * Tells whether the incoming request is a CORS preflight — an
 * `OPTIONS` request carrying the `Access-Control-Request-Method`
 * header.
 *
 * Per the Fetch spec, a CORS preflight is the browser's permission
 * check before a "non-simple" cross-origin request : it sends an
 * `OPTIONS` request with `Access-Control-Request-Method` (and often
 * `Access-Control-Request-Headers`) so the server can advertise which
 * methods and headers are allowed. A regular `OPTIONS` request (no
 * `Access-Control-Request-Method`) is NOT a preflight — it might be a
 * routing query or a server-info probe — and must not be treated as
 * one.
 *
 * Useful inside a CORS middleware to short-circuit the handler chain
 * (no `$handler->handle($request)` call, return the preflight
 * response directly) when the request is a preflight.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\cors\isCorsPreflight ;
 *
 * if ( isCorsPreflight( $request ) )
 * {
 *     return applyCorsHeaders( $request , $factory->createResponse( 204 ) , $config ) ;
 * }
 * ```
 *
 * @param ServerRequestInterface $request The incoming PSR-7 request.
 *
 * @return bool `true` when the request is an `OPTIONS` with `Access-Control-Request-Method`.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\cors
 */
function isCorsPreflight( ServerRequestInterface $request ) : bool
{
    return $request->getMethod() === HttpMethod::OPTIONS
        && $request->hasHeader( HttpHeader::ACCESS_CONTROL_REQUEST_METHOD ) ;
}
