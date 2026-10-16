<?php

declare( strict_types = 1 );

namespace oihana\middleware\idempotency ;

use oihana\middleware\enums\IdempotencyOutcome ;

/**
 * Result of {@see \oihana\middleware\helpers\idempotency\beginIdempotency()}.
 *
 * Immutable value object handed back to
 * {@see \oihana\middleware\helpers\idempotency\completeIdempotency()} once the request has run.
 *
 * Semantics :
 *
 * - `$outcome` — one of the {@see IdempotencyOutcome} constants.
 * - `$key` — the store key ; `null` for `skip`, `missing` and `invalid`, which touch no store.
 * - `$fingerprint` — the fingerprint of the request ; `null` when no store was touched.
 * - `$record` — the record already held : the response to serve on `replay`, the first request
 *   on `mismatch` and `inProgress` ; `null` otherwise.
 * - `$retryAfter` — seconds to suggest in `Retry-After` on `inProgress` ; `0` otherwise.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\idempotency
 */
final readonly class IdempotencyDecision
{
    public function __construct
    (
        public string             $outcome            ,
        public ?string            $key         = null ,
        public ?string            $fingerprint = null ,
        public ?IdempotencyRecord $record      = null ,
        public int                $retryAfter  = 0    ,
    ) {}
}
