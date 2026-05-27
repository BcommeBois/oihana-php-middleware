<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Option keys accepted by {@see enforceRateLimit()}.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class RateLimitOption
{
    use ConstantsTrait ;

    /**
     * `key` — identifier the counter is keyed on. Three accepted forms : a `string` (used verbatim), a `callable` `fn(ServerRequestInterface): string` (resolved on every call), or `null` (default — falls back to the client IP resolved via `oihana\http\helpers\ips\getClientIp()`).
     */
    public const string KEY = 'key' ;

    /**
     * `keyPrefix` — string prefix prepended to every store key. Defaults to `'ratelimit'`. Useful to isolate several limiters that share the same store backend.
     */
    public const string KEY_PREFIX = 'keyPrefix' ;

    /**
     * `limit` — maximum number of requests allowed per window. Positive `int`, defaults to `100`.
     */
    public const string LIMIT = 'limit' ;

    /**
     * `now` — Unix timestamp to use as the current time when computing the window. `int` or `null` (default — falls back to `time()`). Primarily intended for deterministic tests.
     */
    public const string NOW = 'now' ;

    /**
     * `scope` — optional segment inserted between the prefix and the key (e.g. `'auth'`, `'write'`, `'read'`). `string` or `null` (default — segment omitted).
     */
    public const string SCOPE = 'scope' ;

    /**
     * `window` — width of the rate-limit window, in seconds. Positive `int`, defaults to `60`.
     */
    public const string WINDOW = 'window' ;
}
