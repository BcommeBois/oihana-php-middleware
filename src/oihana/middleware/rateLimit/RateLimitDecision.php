<?php

declare( strict_types = 1 );

namespace oihana\middleware\rateLimit ;

/**
 * Result of a rate-limit evaluation.
 *
 * Immutable value object produced by
 * {@see enforceRateLimit()} and consumed by {@see withRateLimitHeaders()} to stamp
 * the relevant `X-RateLimit-*` (or `RateLimit-*`) headers on the response.
 *
 * Semantics :
 *
 * - `$allowed` — `true` when the request fits inside the current window
 *   quota, `false` when the counter has overflown.
 * - `$limit` — the quota in effect for this window (verbatim copy of the
 *   limit declared in the helper config).
 * - `$remaining` — number of requests still allowed before the window
 *   resets. Clamped to `0` when the quota is exhausted.
 * - `$reset` — absolute Unix timestamp when the current window ends and
 *   the counter rolls over.
 * - `$retryAfter` — seconds until `$reset`, or `0` when `$allowed` is
 *   `true`. Suitable as the value of the `Retry-After` header on a 429.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\rateLimit
 */
final readonly class RateLimitDecision
{
    public function __construct
    (
        public bool $allowed    ,
        public int  $limit      ,
        public int  $remaining  ,
        public int  $reset      ,
        public int  $retryAfter ,
    ) {}
}
