<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\rateLimit ;

use Psr\Http\Message\ResponseInterface ;

use oihana\enums\http\HttpHeader ;
use oihana\middleware\rateLimit\RateLimitDecision ;

/**
 * Stamps rate-limit headers on a PSR-7 response from a
 * {@see RateLimitDecision}.
 *
 * Emits three counter headers on every call :
 *
 * - **Limit** — the quota in effect for the current window.
 * - **Remaining** — requests still allowed before reset, `0` once the
 *   quota is exhausted.
 * - **Reset** — absolute Unix timestamp when the window closes.
 *
 * When `!$decision->allowed`, also emits `Retry-After` (delta-seconds
 * until reset) so HTTP clients can honour standard back-off semantics
 * on a `429 Too Many Requests`.
 *
 * Header family is controlled by `$rfc9421` :
 *
 * - `false` (default) — legacy de-facto family `X-RateLimit-Limit /
 *   X-RateLimit-Remaining / X-RateLimit-Reset`. Aligned with what most
 *   HTTP clients (Postman, GitHub-style SDKs, browser DevTools)
 *   already display, and matches the existing oihana/api convention.
 * - `true` — IETF draft family `RateLimit-Limit / RateLimit-Remaining
 *   / RateLimit-Reset` (RFC 9421 draft "RateLimit Header Fields for
 *   HTTP"). Opt-in for forward-looking deployments.
 *
 * PSR-7 immutable : a new response instance is returned, the input
 * response is never mutated. Any pre-existing header with the same
 * name is replaced (`withHeader` semantics).
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\rateLimit\withRateLimitHeaders ;
 *
 * // Allow path.
 * $response = withRateLimitHeaders( $handler->handle( $request ) , $decision ) ;
 *
 * // Block path : caller builds the 429, helper stamps Limit / Remaining
 * // / Reset + Retry-After.
 * if ( !$decision->allowed )
 * {
 *     return withRateLimitHeaders( $factory->createResponse( 429 ) , $decision ) ;
 * }
 * ```
 *
 * @param ResponseInterface $response The PSR-7 response to stamp.
 * @param RateLimitDecision $decision The decision produced by {@see enforceRateLimit()}.
 * @param bool              $rfc9421  When `true`, emit the RFC 9421 draft family instead of the legacy `X-RateLimit-*` family.
 *
 * @return ResponseInterface A new response carrying the rate-limit headers.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\rateLimit
 */
function withRateLimitHeaders
(
    ResponseInterface $response ,
    RateLimitDecision $decision ,
    bool              $rfc9421 = false ,
)
: ResponseInterface
{
    if ( $rfc9421 )
    {
        $limitHeader     = HttpHeader::RATELIMIT_LIMIT     ;
        $remainingHeader = HttpHeader::RATELIMIT_REMAINING ;
        $resetHeader     = HttpHeader::RATELIMIT_RESET     ;
    }
    else
    {
        $limitHeader     = HttpHeader::X_RATELIMIT_LIMIT     ;
        $remainingHeader = HttpHeader::X_RATELIMIT_REMAINING ;
        $resetHeader     = HttpHeader::X_RATELIMIT_RESET     ;
    }

    $response = $response
        ->withHeader( $limitHeader     , (string) $decision->limit     )
        ->withHeader( $remainingHeader , (string) $decision->remaining )
        ->withHeader( $resetHeader     , (string) $decision->reset     ) ;

    if ( !$decision->allowed )
    {
        $response = $response->withHeader( HttpHeader::RETRY_AFTER , (string) $decision->retryAfter ) ;
    }

    return $response ;
}
