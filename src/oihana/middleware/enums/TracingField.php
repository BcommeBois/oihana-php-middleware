<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Default field names for the W3C Trace Context helpers.
 *
 * Provides typed constants for the two W3C Trace Context headers and
 * the conventional PSR-7 request attribute used to propagate the
 * resolved {@see \oihana\middleware\tracing\TraceContext} through the
 * middleware chain.
 *
 * The two header names duplicate `HttpHeader::TRACEPARENT` /
 * `HttpHeader::TRACESTATE` on purpose — they give callers a local
 * "field map" for the tracing family so they don't have to `use` the
 * generic `HttpHeader` enum just to spell the standard names.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class TracingField
{
    use ConstantsTrait ;

    /**
     * `traceparent` — W3C Trace Context header carrying the trace id, parent span id, and sampling flag (RFC: W3C Recommendation, 2021).
     */
    public const string HEADER_TRACEPARENT = 'traceparent' ;

    /**
     * `tracestate` — companion W3C Trace Context header carrying vendor-specific key/value pairs. Propagated verbatim.
     */
    public const string HEADER_TRACESTATE = 'tracestate' ;

    /**
     * `traceContext` — conventional PSR-7 request attribute name carrying the resolved {@see \oihana\middleware\tracing\TraceContext} value object through the middleware chain.
     */
    public const string ATTRIBUTE_NAME = 'traceContext' ;
}
