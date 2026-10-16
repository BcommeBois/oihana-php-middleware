<?php

declare( strict_types = 1 );

namespace oihana\middleware\idempotency ;

/**
 * Contract for the store that keeps the answers given to idempotency keys.
 *
 * The helpers {@see \oihana\middleware\helpers\idempotency\beginIdempotency()} and
 * {@see \oihana\middleware\helpers\idempotency\completeIdempotency()} are its canonical
 * consumers ; they build the store key from `{prefix}:{sha256(scope, method, path, key)}`
 * and let the store do the rest.
 *
 * 🚨 **`begin()` must be atomic.** Two requests carrying the same key at the same time — two
 * tabs, a retry fired while the first send is still running — must never both get the key,
 * otherwise both run and the duplicate the key exists to prevent is created anyway. Real
 * backends expose the primitive : an insert refused on an existing key (a database unique
 * key, `Memcached::add()`, Redis `SET NX`).
 *
 * The store must also **keep** what it is given for the whole `$ttl` : a key that disappears
 * early (a cache flushed or evicted) lets the next replay run the request a second time.
 *
 * The shipped {@see InMemoryIdempotencyStore} is process-local only : for tests and
 * single-process scripts.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\idempotency
 */
interface IdempotencyStore
{
    /**
     * Atomically claims `$key` for `$lease` seconds.
     *
     * When the key is free — never seen, expired, or held by a running request whose lease has
     * lapsed (the process died) — the implementation MUST store a running record
     * (`$fingerprint`, `lockedUntil = now + $lease`) and return `null` : the caller owns the key
     * and runs the request. Otherwise it MUST return the record already held, running or
     * completed, and change nothing.
     *
     * @param string $key         The store key, fully namespaced by the caller.
     * @param string $fingerprint The fingerprint of the claiming request.
     * @param int    $lease       Seconds the claim holds.
     *
     * @return ?IdempotencyRecord `null` when the caller now owns the key, the held record otherwise.
     */
    public function begin( string $key , string $fingerprint , int $lease ) :?IdempotencyRecord ;

    /**
     * Keeps the completed record of `$key` for `$ttl` seconds, replacing its running record.
     *
     * @param string            $key    The store key.
     * @param IdempotencyRecord $record The completed record.
     * @param int               $ttl    Seconds the record is kept.
     *
     * @return void
     */
    public function complete( string $key , IdempotencyRecord $record , int $ttl ) :void ;

    /**
     * Reads the record held under `$key`, without claiming it.
     *
     * @param string $key The store key.
     *
     * @return ?IdempotencyRecord The record, or `null` when the key is free.
     */
    public function find( string $key ) :?IdempotencyRecord ;

    /**
     * Frees `$key` : the next request carrying it runs again.
     *
     * Called when the answer must not be kept (a server error, a refused authentication) or
     * when the request failed with an exception.
     *
     * @param string $key The store key.
     *
     * @return void
     */
    public function release( string $key ) :void ;
}
