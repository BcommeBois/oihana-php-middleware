<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\tracing ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\middleware\enums\ParsedTraceparentField ;
use oihana\middleware\enums\TracingField ;
use oihana\middleware\tracing\TraceContext ;

use Random\RandomException;

use function oihana\middleware\tracing\parseTraceparent ;

/**
 * Resolves the W3C Trace Context for an incoming PSR-7 request.
 *
 * Reads the `traceparent` and `tracestate` headers, validates the
 * incoming context (per W3C §3.2.2.4 — see {@see parseTraceparent}),
 * generates a fresh span id for this hop, and returns the resolved
 * {@see TraceContext}.
 *
 * Behaviour :
 *
 * - **Valid incoming `traceparent`** — the trace id and parent span id
 *   are inherited verbatim. The sampling flag is inherited. A fresh
 *   span id is generated for the current hop. `tracestate` is
 *   propagated verbatim when present.
 * - **Missing or invalid incoming `traceparent`** — silently
 *   regenerate the entire context : fresh 128-bit trace id, fresh
 *   64-bit span id, no parent, sampled = `true`. This matches the W3C
 *   recommendation's "treat as if no traceparent received" guidance
 *   for malformed headers and prevents misconfigured upstream proxies
 *   from breaking trace generation.
 *
 * Defaults rationale :
 *
 * - **Fresh sampling defaults to `true`** — first-hop is the worst
 *   place to silently drop traces ; the application can wrap the
 *   helper with its own sampler when a ratio is needed.
 * - **No tracestate generated when missing** — `tracestate` is for
 *   vendor-specific data we have no opinion about.
 *
 * @param ServerRequestInterface $request The incoming PSR-7 request.
 *
 * @return TraceContext The resolved trace context (inherited or freshly generated).
 *
 * @throws RandomException
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\tracing\traceContextFromRequest ;
 * use function oihana\middleware\helpers\tracing\withTracingAttribute ;
 *
 * // Middleware entry
 * $context = traceContextFromRequest( $request ) ;
 * $request = withTracingAttribute( $request , $context ) ;
 *
 * // Later, when calling a downstream service
 * $guzzle->get( 'https://api.partner.com/charge' ,
 * [
 *     'headers' => [ 'traceparent' => $context->toTraceparent() ] ,
 * ]) ;
 * ```
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\tracing
 */
function traceContextFromRequest( ServerRequestInterface $request ) : TraceContext
{
    $parsed = parseTraceparent( $request->getHeaderLine( TracingField::HEADER_TRACEPARENT ) ) ;

    // bin2hex(random_bytes(8)) gives us 16 lowercase hex characters,
    // which is exactly the W3C span id format. Same primitive that
    // backs the OpenTelemetry PHP SDK — no point in indirecting.
    $spanId = bin2hex( random_bytes( 8 ) ) ;

    if ( $parsed === null )
    {
        // No valid incoming context : freshly generate a 128-bit trace id
        // and default to sampled = true (drop-on-first-hop is a worse
        // failure mode than over-collecting on a single request).
        return new TraceContext
        (
            traceId      : bin2hex( random_bytes( 16 ) ) ,
            spanId       : $spanId ,
            parentSpanId : null ,
            sampled      : true ,
            tracestate   : null ,
        ) ;
    }

    $incomingTracestate = $request->getHeaderLine( TracingField::HEADER_TRACESTATE ) ;

    return new TraceContext
    (
        traceId      : $parsed[ ParsedTraceparentField::TRACE_ID ] ,
        spanId       : $spanId ,
        parentSpanId : $parsed[ ParsedTraceparentField::PARENT_SPAN_ID ] ,
        sampled      : $parsed[ ParsedTraceparentField::SAMPLED ] ,
        tracestate   : $incomingTracestate !== '' ? $incomingTracestate : null ,
    ) ;
}
