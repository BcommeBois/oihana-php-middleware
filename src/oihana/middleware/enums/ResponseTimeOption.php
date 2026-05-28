<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Option keys accepted by {@see \oihana\middleware\helpers\observability\withResponseTime()}.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class ResponseTimeOption
{
    use ConstantsTrait ;

    /**
     * `precision` — number of decimal places kept on the duration value in milliseconds (`int`, default `2`). Negative values fall back to `0`.
     */
    public const string PRECISION = 'precision' ;

    /**
     * `serverTimingMetric` — name of the metric emitted on the `Server-Timing` header (`string`, default `'total'`). Only used when `USE_SERVER_TIMING` is `true`.
     */
    public const string SERVER_TIMING_METRIC = 'serverTimingMetric' ;

    /**
     * `useServerTiming` — when `true`, emits the duration as the W3C `Server-Timing` header (`metric;dur=ms`) instead of the de-facto `X-Response-Time` (`Nms`) (`bool`, default `false`).
     */
    public const string USE_SERVER_TIMING = 'useServerTiming' ;
}
