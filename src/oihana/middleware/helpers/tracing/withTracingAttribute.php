<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\tracing ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\middleware\enums\TracingField ;
use oihana\middleware\tracing\TraceContext ;

/**
 * Stamps the resolved {@see TraceContext} on the PSR-7 request as the
 * `traceContext` attribute (or a caller-supplied attribute name) so
 * downstream handlers can read it without re-parsing the
 * `traceparent` header.
 *
 * Typical wiring inside a middleware :
 *
 * ```php
 * $context = traceContextFromRequest( $request ) ;
 * $request = withTracingAttribute( $request , $context ) ;
 *
 * $response = $handler->handle( $request ) ;
 *
 * // Downstream handler
 * $ctx = $request->getAttribute( TracingField::ATTRIBUTE_NAME ) ;
 * $logger->info( 'event' , [ 'trace_id' => $ctx->traceId ] ) ;
 * ```
 *
 * Returns a new `ServerRequestInterface` (PSR-7 immutable) — the
 * input request is never mutated.
 *
 * @param ServerRequestInterface $request       The PSR-7 request to augment.
 * @param TraceContext           $context       The resolved trace context.
 * @param string                 $attributeName The attribute name. Defaults to `traceContext` — use {@see TracingField::ATTRIBUTE_NAME}.
 *
 * @return ServerRequestInterface A new request carrying the trace context attribute.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\tracing
 */
function withTracingAttribute
(
    ServerRequestInterface $request ,
    TraceContext           $context ,
    string                 $attributeName = 'traceContext' ,
)
: ServerRequestInterface
{
    return $request->withAttribute( $attributeName , $context ) ;
}
