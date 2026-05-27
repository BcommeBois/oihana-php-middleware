<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\requestId ;

use Psr\Http\Message\ServerRequestInterface ;

use function oihana\core\encoding\randomBase64Url ;

/**
 * Extracts the request ID from an incoming request, or generates a new
 * one when the request does not carry a valid one.
 *
 * The helper accepts an existing `X-Request-Id` header value (so a
 * trusted upstream — load balancer, API gateway, calling service — can
 * propagate its own correlation ID), but only when it passes a
 * conservative shape check: 1 to 128 characters, restricted to the URL-
 * safe alphabet `[A-Za-z0-9_-]`. This defends against header-injection
 * attacks where a client could forge an `X-Request-Id` carrying CRLF,
 * control characters, or any value designed to mess up downstream logs.
 *
 * When the header is missing, empty, or fails the shape check, the
 * helper generates a fresh ID via {@see \oihana\core\encoding\randomBase64Url()}
 * (128 bits of CSPRNG entropy, 22 base64url characters).
 *
 * Typical wiring inside a middleware:
 *
 * ```php
 * $id      = requestIdFromRequest( $request ) ;
 * $request = $request->withAttribute( RequestIdField::ATTRIBUTE_NAME , $id ) ;
 * $response = $handler->handle( $request ) ;
 * $response = withRequestIdHeader( $response , $id ) ;
 * return $response ;
 * ```
 *
 * @param ServerRequestInterface $request The incoming PSR-7 request.
 * @param string $headerName The header name carrying the incoming ID. Defaults to `X-Request-Id` — use {@see \oihana\middleware\enums\RequestIdField::HEADER_NAME}.
 *
 * @return string The validated incoming ID, or a freshly generated one.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\requestId
 */
function requestIdFromRequest( ServerRequestInterface $request , string $headerName = 'X-Request-Id' ) :string
{
    $incoming = $request->getHeaderLine( $headerName ) ;

    if ( $incoming !== '' && preg_match( '/^[A-Za-z0-9_\-]{1,128}$/' , $incoming ) === 1 )
    {
        return $incoming ;
    }

    return randomBase64Url( 16 ) ;
}
