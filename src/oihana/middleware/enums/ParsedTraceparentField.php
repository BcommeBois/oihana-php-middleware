<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Keys of the associative array returned by
 * {@see \oihana\middleware\tracing\parseTraceparent()}.
 *
 * The function is a pure parser : it normalises the raw 55-character
 * `traceparent` header into a small array of decoded components, then
 * its caller (typically
 * {@see \oihana\middleware\helpers\tracing\traceContextFromRequest()})
 * reads those components to assemble a {@see \oihana\middleware\tracing\TraceContext}.
 *
 * Exposing the keys as typed constants here keeps producer and
 * consumer in sync without duplicating string literals on either side
 * — matching the "zero magic strings" convention of the ecosystem.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class ParsedTraceparentField
{
    use ConstantsTrait ;

    /**
     * `traceId` — 32 lowercase hex characters identifying the end-to-end distributed trace (inherited verbatim from the incoming header).
     */
    public const string TRACE_ID = 'traceId' ;

    /**
     * `parentSpanId` — 16 lowercase hex characters identifying the span of the upstream service that called us.
     */
    public const string PARENT_SPAN_ID = 'parentSpanId' ;

    /**
     * `sampled` — `bool`. Decoded value of the `sampled` flag bit (bit 0 of the flags byte). Other flag bits are reserved by W3C and stripped during parsing.
     */
    public const string SAMPLED = 'sampled' ;
}
