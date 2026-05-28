<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\cors ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\enums\http\HttpHeader ;

/**
 * Tells whether the incoming request carries an `Origin` header — the
 * de-facto signal of a cross-origin browser request.
 *
 * Useful inside a CORS middleware to short-circuit the CORS-specific
 * branch (preflight handling, `Access-Control-*` headers, `Vary: Origin`)
 * when the request is same-origin and therefore doesn't need any CORS
 * treatment. Pure predicate — no side effects, no header injection.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\cors\isCorsRequest ;
 *
 * if ( isCorsRequest( $request ) )
 * {
 *     $response = applyCorsHeaders( $request , $response , $config ) ;
 * }
 * ```
 *
 * @param ServerRequestInterface $request The incoming PSR-7 request.
 *
 * @return bool `true` when the request carries an `Origin` header.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\cors
 */
function isCorsRequest( ServerRequestInterface $request ) : bool
{
    return $request->hasHeader( HttpHeader::ORIGIN ) ;
}
