<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Keys of the array produced by {@see \oihana\middleware\idempotency\IdempotencyRecord::toArray()}
 * and read back by {@see \oihana\middleware\idempotency\IdempotencyRecord::fromArray()}.
 *
 * A store that serialises its records (a database document, a cache entry) reads and writes
 * these keys rather than literal strings.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class IdempotencyField
{
    use ConstantsTrait ;

    /**
     * `body` — the body of the kept response, as a `string`, or `null` while the request runs.
     */
    public const string BODY = 'body' ;

    /**
     * `fingerprint` — the SHA-256 fingerprint of the request that claimed the key.
     */
    public const string FINGERPRINT = 'fingerprint' ;

    /**
     * `headers` — the kept response headers, as a map `name => list<string>`.
     */
    public const string HEADERS = 'headers' ;

    /**
     * `lockedUntil` — Unix timestamp at which the claim of a running request lapses (`0` once completed).
     */
    public const string LOCKED_UNTIL = 'lockedUntil' ;

    /**
     * `status` — the status code of the kept response, or `null` while the request runs.
     */
    public const string STATUS = 'status' ;
}
