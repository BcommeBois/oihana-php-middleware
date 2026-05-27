<?php

declare( strict_types = 1 );

namespace oihana\middleware\rateLimit ;

/**
 * Contract for a fixed-window rate-limit counter store.
 *
 * Implementations expose a single atomic operation : increment a counter
 * and return its new value, creating it with a TTL when absent. The
 * helper {@see \oihana\middleware\helpers\rateLimit\enforceRateLimit()}
 * is the canonical consumer ; it builds the counter key from
 * `{prefix}:{scope?}:{identity}:{windowStart}` and lets the store do
 * the rest.
 *
 * Atomicity is essential : every concurrent request that lands in the
 * same window must see a unique counter value, otherwise two requests
 * could pass the limit check simultaneously. Real-world backends
 * (Memcached, Redis, APCu) all expose an atomic `increment`/`incr`
 * primitive that fits this contract directly. The shipped
 * {@see InMemoryRateLimitStore} is process-local only and not
 * thread-safe — useful for tests and single-process scripts, but should
 * never be used to back a multi-worker application.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\rateLimit
 */
interface RateLimitStore
{
    /**
     * Atomically increments the counter identified by `$key` and returns
     * its new value.
     *
     * When the counter does not exist yet, the implementation MUST
     * initialise it to `1` with a TTL of `$window` seconds — that initial
     * call therefore returns `1`. The TTL of an existing counter MUST NOT
     * be extended on subsequent increments : the window is anchored on
     * the first request, not the last.
     *
     * @param string $key    The counter key, fully namespaced by the caller.
     * @param int    $window TTL (seconds) to apply on initial creation.
     *
     * @return int The counter value after the increment.
     */
    public function increment( string $key , int $window ) : int ;
}
