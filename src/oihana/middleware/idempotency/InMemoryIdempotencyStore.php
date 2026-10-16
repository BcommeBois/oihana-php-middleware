<?php

declare( strict_types = 1 );

namespace oihana\middleware\idempotency ;

/**
 * Process-local {@see IdempotencyStore} implementation.
 *
 * Keeps the records in a PHP array bound to the instance — nothing is shared with other PHP
 * processes, other workers or other servers. It is intended for :
 *
 * - **Unit and integration tests** — deterministic, no external dependency, a clock that can
 *   be moved forward without sleeping.
 * - **CLI scripts and single-process tools** — the keys live as long as the store instance.
 *
 * It MUST NOT back a multi-worker HTTP application : each worker would keep its own keys, and
 * a replay landing on another worker would run the request a second time.
 *
 * Expired entries are reclaimed lazily, on the next call that touches the same key.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\idempotency
 */
final class InMemoryIdempotencyStore implements IdempotencyStore
{
    /**
     * @param ?callable $clock Optional `fn(): int` returning the current Unix timestamp. Defaults to `time()`.
     */
    public function __construct( ?callable $clock = null )
    {
        $this->clock = $clock ;
    }

    /**
     * Internal entry key holding the Unix expiry timestamp.
     */
    private const string EXPIRES = 'expires' ;

    /**
     * Internal entry key holding the record.
     */
    private const string RECORD = 'record' ;

    /**
     * Optional injected clock. When `null`, the store reads `time()`.
     *
     * @var ?callable(): int
     */
    private $clock ;

    /**
     * The entries, keyed by store key.
     *
     * @var array<string, array{record: IdempotencyRecord, expires: int}>
     */
    private array $entries = [] ;

    /**
     * {@inheritdoc}
     */
    public function begin( string $key , string $fingerprint , int $lease ) :?IdempotencyRecord
    {
        $held = $this->find( $key ) ;

        if ( $held !== null )
        {
            return $held ;
        }

        $lockedUntil = $this->now() + $lease ;

        $this->entries[ $key ] =
        [
            self::RECORD  => new IdempotencyRecord( $fingerprint , $lockedUntil ) ,
            self::EXPIRES => $lockedUntil ,
        ] ;

        return null ;
    }

    /**
     * {@inheritdoc}
     */
    public function complete( string $key , IdempotencyRecord $record , int $ttl ) :void
    {
        $this->entries[ $key ] =
        [
            self::RECORD  => $record ,
            self::EXPIRES => $this->now() + $ttl ,
        ] ;
    }

    /**
     * {@inheritdoc}
     */
    public function find( string $key ) :?IdempotencyRecord
    {
        $entry = $this->entries[ $key ] ?? null ;

        if ( $entry === null )
        {
            return null ;
        }

        if ( $entry[ self::EXPIRES ] <= $this->now() )
        {
            unset( $this->entries[ $key ] ) ;
            return null ;
        }

        return $entry[ self::RECORD ] ;
    }

    /**
     * {@inheritdoc}
     */
    public function release( string $key ) :void
    {
        unset( $this->entries[ $key ] ) ;
    }

    /**
     * Reads the current Unix timestamp from the injected clock, or `time()`.
     *
     * @return int The current Unix timestamp.
     */
    private function now() :int
    {
        return ( $this->clock !== null ) ? ( $this->clock )() : time() ;
    }
}
