<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\cache ;

use Psr\Http\Message\ResponseInterface ;

use oihana\enums\http\HttpHeader ;
use oihana\enums\http\HttpStatusCode ;

/**
 * Turns a PSR-7 response into a clean `304 Not Modified` response
 * carrying the `ETag` header.
 *
 * Per [RFC 9110 §15.4.5](https://www.rfc-editor.org/rfc/rfc9110#status.304),
 * a `304` response :
 *
 * - MUST have an empty body — the body is what the cache already
 *   holds, the server is just confirming it's still fresh.
 * - SHOULD carry any headers the cache would update on the stored
 *   response (typically `Cache-Control`, `ETag`, `Vary`, `Date`,
 *   `Expires`). The helper stamps `ETag` ; the caller stamps
 *   anything else relevant via the standard `withHeader()` chain
 *   before or after the helper call.
 *
 * Pairs with {@see isNotModified()} :
 *
 * ```php
 * $etag = '"v42"' ;
 *
 * if ( isNotModified( $request , $etag ) )
 * {
 *     return respondNotModified( $factory->createResponse() , $etag ) ;
 * }
 *
 * // ... build the body, stamp the ETag, return 200 ...
 * ```
 *
 * PSR-7 immutable : returns a new response instance, the input
 * response is never mutated.
 *
 * @param ResponseInterface $response The PSR-7 response to convert.
 * @param string            $etag     The current ETag of the resource. Same value the caller would stamp on a `200` response — typically `'"v42"'` (strong) or `'W/"v42"'` (weak).
 *
 * @return ResponseInterface A new response carrying the `304` status, the `ETag` header and an empty body.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\cache
 */
function respondNotModified( ResponseInterface $response , string $etag ) : ResponseInterface
{
    return $response
        ->withStatus( HttpStatusCode::NOT_MODIFIED , 'Not Modified' )
        ->withHeader( HttpHeader::ETAG , $etag ) ;
}
