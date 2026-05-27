<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\requestId ;

use Psr\Http\Message\ResponseInterface ;

/**
 * Stamps the response with the request ID header so downstream consumers
 * (browser dev tools, log aggregators, support tickets, traceback
 * pipelines) can correlate the response with the server-side trace.
 *
 * Returns a new `ResponseInterface` (PSR-7 immutable) — the input
 * response is never mutated. Any pre-existing header with the same name
 * is replaced (PSR-7 `withHeader` semantics).
 *
 * Typical wiring inside a middleware:
 *
 * ```php
 * $id      = requestIdFromRequest( $request ) ;
 * $response = $handler->handle( $request ) ;
 * $response = withRequestIdHeader( $response , $id ) ;
 * return $response ;
 * ```
 *
 * @param ResponseInterface $response The PSR-7 response to stamp.
 * @param string $id The request ID to emit. Caller is responsible for the value (typically produced by {@see requestIdFromRequest()}).
 * @param string $headerName The header name. Defaults to `X-Request-Id` — use {@see \oihana\middleware\enums\RequestIdField::HEADER_NAME}.
 *
 * @return ResponseInterface A new response carrying the request ID header.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\requestId
 */
function withRequestIdHeader( ResponseInterface $response , string $id , string $headerName = 'X-Request-Id' ) :ResponseInterface
{
    return $response->withHeader( $headerName , $id ) ;
}
