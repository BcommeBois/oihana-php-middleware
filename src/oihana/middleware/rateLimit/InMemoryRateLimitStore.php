<?php

declare( strict_types = 1 );

namespace oihana\middleware\rateLimit ;

/**
 * Process-local {@see RateLimitStore} implementation.
 *
 * Stores counters in a PHP array bound to the instance — there is no
 * shared state with other PHP processes, other workers in a pool, or
 * other servers in a fleet. It is intended for :
 *
 * - **Unit and integration tests** — deterministic, no external
 *   dependency, easy to inspect.
 * - **CLI scripts and single-process tools** — the counter survives as
 *   long as the script holds the store reference.
 * - **Demos and prototypes** — gets the helper running with zero setup.
 *
 * It MUST NOT be used to back a multi-worker HTTP application : every
 * worker would maintain its own counters and the effective rate would
 * be multiplied by the number of workers. Use a Memcached / Redis
 * backed store ({@see https://github.com/BcommeBois/oihana-php-memcached `oihana/php-memcached`})
 * for production traffic.
 *
 * Expired counters are reclaimed lazily on the next call that touches
 * the same key — there is no background sweeper. Memory therefore
 * grows with the cardinality of the keys observed during the lifetime
 * of the store instance.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\rateLimit
 */
final class InMemoryRateLimitStore implements RateLimitStore
{
    /**
     * @param ?callable $clock Optional `fn(): int` returning the current Unix timestamp. Defaults to `time()`.
     */
    public function __construct( ?callable $clock = null )
    {
        $this->clock = $clock ;
    }

    /**
     * Internal cell key holding the counter value.
     */
    private const string COUNT = 'count' ;

    /**
     * Internal cell key holding the Unix expiry timestamp.
     */
    private const string EXPIRES = 'expires' ;

    /**
     * Active counters keyed by store key.
     *
     * @var array<string, array{count:int, expires:int}>
     */
    private array $counters = [] ;

    /**
     * Optional injected clock — primarily used by tests to advance time
     * without sleeping. When `null`, the store reads `time()`.
     *
     * @var ?callable(): int
     */
    private $clock ;

    /**
     * {@inheritdoc}
     */
    public function increment( string $key , int $window ) : int
    {
        $now = ( $this->clock !== null ) ? ( $this->clock )() : time() ;

        $entry = $this->counters[ $key ] ?? null ;

        if ( $entry === null || $entry[ self::EXPIRES ] <= $now )
        {
            $this->counters[ $key ] = [ self::COUNT => 1 , self::EXPIRES => $now + $window ] ;
            return 1 ;
        }

        $count = $entry[ self::COUNT ] + 1 ;

        $this->counters[ $key ][ self::COUNT ] = $count ;

        return $count ;
    }
}
