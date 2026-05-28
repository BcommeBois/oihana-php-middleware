<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\observability ;

use Psr\Http\Message\ResponseInterface ;

use oihana\enums\http\HttpHeader ;
use oihana\middleware\enums\ResponseTimeOption ;

/**
 * Stamps the elapsed time since `$startMicrotime` on a PSR-7 response.
 *
 * Two output formats are supported, controlled by
 * {@see ResponseTimeOption::USE_SERVER_TIMING} :
 *
 * - **Default (`false`)** — emits the de-facto `X-Response-Time: 42.50ms`
 *   header, aligned with the Express / Koa convention. Picked up by
 *   most HTTP clients and dashboards out of the box, no further config
 *   required.
 * - **Opt-in (`true`)** — emits the standardised W3C
 *   `Server-Timing: total;dur=42.50` header, parsed natively by the
 *   Chromium and Firefox DevTools "Network" tab and by most APM
 *   ingesters (Datadog, New Relic, Sentry, etc.). The metric name
 *   (`total` by default) is configurable via
 *   {@see ResponseTimeOption::SERVER_TIMING_METRIC}.
 *
 * Duration is computed as `(microtime(true) - $startMicrotime) * 1000`
 * and formatted with {@see ResponseTimeOption::PRECISION} decimal
 * places (default `2`). Negative precision is clamped to `0`. Negative
 * elapsed time (clock skew, mistaken `$startMicrotime`) is clamped to
 * `0.00` so the header never carries a meaningless negative value.
 *
 * PSR-7 immutable : a new response instance is returned, the input
 * response is never mutated. Any pre-existing value of the chosen
 * header is replaced (`withHeader` semantics).
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\observability\withResponseTime ;
 * use oihana\middleware\enums\ResponseTimeOption ;
 *
 * // Inside a PSR-15 middleware
 * $start    = microtime( true ) ;
 * $response = $handler->handle( $request ) ;
 *
 * // Default — emits X-Response-Time: 12.34ms
 * return withResponseTime( $response , $start ) ;
 *
 * // Opt-in — emits Server-Timing: app;dur=12.3
 * return withResponseTime( $response , $start ,
 * [
 *     ResponseTimeOption::USE_SERVER_TIMING    => true ,
 *     ResponseTimeOption::SERVER_TIMING_METRIC => 'app' ,
 *     ResponseTimeOption::PRECISION            => 1 ,
 * ]) ;
 * ```
 *
 * @param ResponseInterface    $response       The PSR-7 response to stamp.
 * @param float                $startMicrotime The reference timestamp captured at request entry (typically `microtime(true)`).
 * @param array<string, mixed> $options        Map of options keyed by {@see ResponseTimeOption} constants.
 *
 * @return ResponseInterface A new response carrying the duration header.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\observability
 */
function withResponseTime
(
    ResponseInterface $response ,
    float             $startMicrotime ,
    array             $options = []
)
: ResponseInterface
{
    $precision = $options[ ResponseTimeOption::PRECISION ] ?? 2 ;
    $precision = ( is_int( $precision ) && $precision >= 0 ) ? $precision : 2 ;

    $durationMs = max( 0.0 , ( microtime( true ) - $startMicrotime ) * 1000.0 ) ;
    $formatted  = number_format( $durationMs , $precision , '.' , '' ) ;

    if ( ( $options[ ResponseTimeOption::USE_SERVER_TIMING ] ?? false ) === true )
    {
        $metric = $options[ ResponseTimeOption::SERVER_TIMING_METRIC ] ?? 'total' ;
        $metric = ( is_string( $metric ) && $metric !== '' ) ? $metric : 'total' ;

        return $response->withHeader
        (
            HttpHeader::SERVER_TIMING ,
            $metric . ';dur=' . $formatted ,
        ) ;
    }

    return $response->withHeader( HttpHeader::X_RESPONSE_TIME , $formatted . 'ms' ) ;
}
