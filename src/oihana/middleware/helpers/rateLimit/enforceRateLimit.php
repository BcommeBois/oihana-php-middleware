<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\rateLimit ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\middleware\enums\RateLimitOption ;
use oihana\middleware\rateLimit\RateLimitDecision ;
use oihana\middleware\rateLimit\RateLimitStore ;

use function oihana\http\helpers\ips\getClientIp ;

/**
 * Enforces a fixed-window rate-limit policy on an incoming PSR-7
 * request and returns the resulting decision.
 *
 * Algorithm — fixed window keyed on `(scope, identity, windowStart)` :
 *
 * - `windowStart = floor(now / window) * window` — current window
 *   anchor, deterministic from the clock.
 * - `reset = windowStart + window` — Unix timestamp when the window
 *   closes.
 * - The counter for the resulting key is incremented atomically via
 *   `$store->increment( $key , $window )`. Initial creation seeds the
 *   counter at `1` with a TTL of `window` seconds — see the
 *   {@see RateLimitStore} contract.
 * - `allowed = (count <= limit)`. When over the limit, `remaining` is
 *   clamped to `0` and `retryAfter = reset - now` exposes the wait.
 *
 * Identity resolution :
 *
 * - When the `KEY` option is a non-empty `string`, it is used verbatim.
 * - When `KEY` is a `callable` `fn(ServerRequestInterface): string`, it
 *   is invoked on each call (lets the caller hash an email, return a
 *   service `_key`, etc.).
 * - When `KEY` is omitted or `null`, the helper falls back to the
 *   client IP resolved via
 *   {@see getClientIp}. When that helper
 *   itself returns `null` (no usable address), the sentinel string
 *   `'unknown'` is used so the helper never silently degrades into
 *   "no key, no quota".
 *
 * Side effects :
 *
 * - Atomic increment of the counter on every call — the helper IS the
 *   rate-limit decision, not a peek.
 * - No `ResponseInterface` is produced. The caller decides what to do
 *   with the decision (typically : on `!allowed`, build a 429 ; on
 *   pass, hand off to the next handler ; in both cases, stamp the
 *   headers via {@see withRateLimitHeaders()}).
 *
 * @param ServerRequestInterface $request The incoming PSR-7 request.
 * @param RateLimitStore         $store   Counter store (atomic increment).
 * @param array<string, mixed>   $config  Map keyed by {@see RateLimitOption} constants.
 *
 * @return RateLimitDecision The decision (allowed / over-limit) plus the headers payload.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\rateLimit\enforceRateLimit ;
 * use function oihana\middleware\helpers\rateLimit\withRateLimitHeaders ;
 * use oihana\middleware\enums\RateLimitOption ;
 *
 * $decision = enforceRateLimit( $request , $store ,
 * [
 *     RateLimitOption::LIMIT  => 10  ,
 *     RateLimitOption::WINDOW => 60  ,
 *     RateLimitOption::SCOPE  => 'auth' ,
 * ]) ;
 *
 * if ( !$decision->allowed )
 * {
 *     return withRateLimitHeaders( $responseFactory->createResponse( 429 ) , $decision ) ;
 * }
 *
 * $response = $handler->handle( $request ) ;
 *
 * return withRateLimitHeaders( $response , $decision ) ;
 * ```
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\rateLimit
 */
function enforceRateLimit
(
    ServerRequestInterface $request ,
    RateLimitStore         $store   ,
    array                  $config  = []
)
: RateLimitDecision
{
    $limit  = $config[ RateLimitOption::LIMIT  ] ?? 100 ;
    $window = $config[ RateLimitOption::WINDOW ] ?? 60  ;

    $limit  = ( is_int( $limit  ) && $limit  > 0 ) ? $limit  : 100 ;
    $window = ( is_int( $window ) && $window > 0 ) ? $window : 60  ;

    $now = $config[ RateLimitOption::NOW ] ?? null ;
    $now = is_int( $now ) ? $now : time() ;

    $windowStart = (int) floor( $now / $window ) * $window ;
    $windowReset = $windowStart + $window ;

    $keyOption = $config[ RateLimitOption::KEY ] ?? null ;

    if ( is_string( $keyOption ) && $keyOption !== '' )
    {
        $identity = $keyOption ;
    }
    else
    {
        $resolved = is_callable( $keyOption )
                  ? $keyOption( $request )
                  : getClientIp( $request ) ;

        $identity = ( is_string( $resolved ) && $resolved !== '' ) ? $resolved : 'unknown' ;
    }

    $prefix = $config[ RateLimitOption::KEY_PREFIX ] ?? 'ratelimit' ;
    $prefix = ( is_string( $prefix ) && $prefix !== '' ) ? $prefix : 'ratelimit' ;

    $scope = $config[ RateLimitOption::SCOPE ] ?? null ;
    $scope = ( is_string( $scope ) && $scope !== '' ) ? $scope : null ;

    $key = $scope !== null
         ? "$prefix:$scope:$identity:$windowStart"
         : "$prefix:$identity:$windowStart" ;

    $count = $store->increment( $key , $window ) ;

    $allowed    = ( $count <= $limit ) ;
    $remaining  = $allowed ? max( 0 , $limit - $count ) : 0 ;
    $retryAfter = $allowed ? 0 : max( 0 , $windowReset - $now ) ;

    return new RateLimitDecision
    (
        allowed    : $allowed     ,
        limit      : $limit       ,
        remaining  : $remaining   ,
        reset      : $windowReset ,
        retryAfter : $retryAfter  ,
    ) ;
}
