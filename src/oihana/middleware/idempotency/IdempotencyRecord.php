<?php

declare( strict_types = 1 );

namespace oihana\middleware\idempotency ;

use InvalidArgumentException ;

use oihana\middleware\enums\IdempotencyField ;

/**
 * What an {@see IdempotencyStore} keeps under an idempotency key.
 *
 * A record lives in two states :
 *
 * - **running** — claimed by a request that has not answered yet : it carries the fingerprint
 *   of that request and the end of its lease (`$lockedUntil`), but no status.
 * - **completed** — the answer of that request : its status, the headers kept and the body,
 *   served again to every replay of the same key.
 *
 * `toArray()` and `fromArray()` let a store that serialises its records (a database document,
 * a cache entry) write and read them with the {@see IdempotencyField} keys.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\idempotency
 */
final readonly class IdempotencyRecord
{
    /**
     * @param string                       $fingerprint The SHA-256 fingerprint of the request that claimed the key.
     * @param int                          $lockedUntil Unix timestamp at which the claim lapses ; `0` once completed.
     * @param ?int                         $status      The status of the kept response ; `null` while running.
     * @param array<string, list<string>>  $headers     The kept response headers.
     * @param ?string                      $body        The body of the kept response ; `null` while running.
     */
    public function __construct
    (
        public string  $fingerprint        ,
        public int     $lockedUntil = 0    ,
        public ?int    $status      = null ,
        public array   $headers     = []   ,
        public ?string $body        = null ,
    ) {}

    /**
     * Rebuilds a record from the array written by {@see toArray()}.
     *
     * A record without a fingerprint cannot be compared to a request : it is refused rather
     * than read as a mismatch, which would answer the client with a misleading error.
     *
     * @param array<array-key, mixed> $data The serialised record.
     *
     * @return IdempotencyRecord The record.
     *
     * @throws InvalidArgumentException When the fingerprint is missing or not a non-empty string.
     */
    public static function fromArray( array $data ) :IdempotencyRecord
    {
        $fingerprint = $data[ IdempotencyField::FINGERPRINT ] ?? null ;

        if ( !is_string( $fingerprint ) || $fingerprint === '' )
        {
            throw new InvalidArgumentException( 'An idempotency record needs a non-empty fingerprint.' ) ;
        }

        $lockedUntil = $data[ IdempotencyField::LOCKED_UNTIL ] ?? 0 ;
        $status      = $data[ IdempotencyField::STATUS       ] ?? null ;
        $body        = $data[ IdempotencyField::BODY         ] ?? null ;
        $rawHeaders  = $data[ IdempotencyField::HEADERS      ] ?? [] ;

        $headers = [] ;

        if ( is_array( $rawHeaders ) )
        {
            foreach ( $rawHeaders as $name => $values )
            {
                if ( !is_string( $name ) )
                {
                    continue ;
                }

                $values = is_array( $values ) ? $values : [ $values ] ;
                $values = array_values( array_filter( $values , 'is_string' ) ) ;

                if ( $values !== [] )
                {
                    $headers[ $name ] = $values ;
                }
            }
        }

        return new IdempotencyRecord
        (
            fingerprint : $fingerprint ,
            lockedUntil : is_int( $lockedUntil ) ? $lockedUntil : 0 ,
            status      : is_int( $status      ) ? $status      : null ,
            headers     : $headers ,
            body        : is_string( $body ) ? $body : null ,
        ) ;
    }

    /**
     * Indicates whether the record holds a response to serve again.
     *
     * @return bool `true` once the request that claimed the key has answered and its response was kept.
     */
    public function isCompleted() :bool
    {
        return $this->status !== null ;
    }

    /**
     * Serialises the record with the {@see IdempotencyField} keys.
     *
     * @return array{fingerprint: string, lockedUntil: int, status: ?int, headers: array<string, list<string>>, body: ?string}
     */
    public function toArray() :array
    {
        return
        [
            IdempotencyField::FINGERPRINT  => $this->fingerprint ,
            IdempotencyField::LOCKED_UNTIL => $this->lockedUntil ,
            IdempotencyField::STATUS       => $this->status      ,
            IdempotencyField::HEADERS      => $this->headers     ,
            IdempotencyField::BODY         => $this->body        ,
        ] ;
    }
}
