<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\tracing ;

use Psr\Http\Message\ResponseInterface ;

use oihana\middleware\enums\TracingField ;
use oihana\middleware\tracing\TraceContext ;

/**
 * Stamps the resolved `traceparent` value on the PSR-7 response.
 *
 * **Opt-in helper** — the W3C Trace Context recommendation defines
 * `traceparent` as a forward-propagation header (incoming requests
 * and outgoing service-to-service calls). It does NOT mandate
 * stamping it on the response sent back to the client.
 *
 * However, exposing it on the response is a common pragmatic choice :
 * it lets a frontend / mobile client capture the trace id and feed it
 * back to support ("here's the trace id of my failed checkout"),
 * which makes support-driven debugging dramatically faster across a
 * distributed architecture.
 *
 * Use this helper when :
 *
 * - You operate a distributed backend (multiple services / DB hops).
 * - You want users (or your support team) to be able to copy a trace
 *   id from a failed response and find the full causal chain in your
 *   APM / log aggregator in seconds.
 *
 * Skip it when :
 *
 * - You run a monolith — the trace id is already on every log line
 *   via {@see \oihana\middleware\helpers\requestId\withRequestIdHeader()}.
 * - You explicitly want to hide infrastructure detail from clients.
 *
 * PSR-7 immutable : a new response instance is returned, the input
 * response is never mutated.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\tracing\traceContextFromRequest ;
 * use function oihana\middleware\helpers\tracing\withTraceparentResponseHeader ;
 *
 * $context  = traceContextFromRequest( $request ) ;
 * $response = $handler->handle( $request ) ;
 *
 * return withTraceparentResponseHeader( $response , $context ) ;
 * ```
 *
 * @param ResponseInterface $response The PSR-7 response to stamp.
 * @param TraceContext      $context  The resolved trace context.
 *
 * @return ResponseInterface A new response carrying the `traceparent` header.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\tracing
 */
function withTraceparentResponseHeader
(
    ResponseInterface $response ,
    TraceContext      $context ,
)
: ResponseInterface
{
    return $response->withHeader( TracingField::HEADER_TRACEPARENT , $context->toTraceparent() ) ;
}
