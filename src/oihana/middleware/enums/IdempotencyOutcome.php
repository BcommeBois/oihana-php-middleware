<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Outcomes of {@see \oihana\middleware\helpers\idempotency\beginIdempotency()}, carried by
 * {@see \oihana\middleware\idempotency\IdempotencyDecision::$outcome}.
 *
 * Only {@see self::PROCEED} lets the request run with its key claimed ; {@see self::SKIP} lets it
 * run untouched. Every other outcome is answered by the caller without running the request.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class IdempotencyOutcome
{
    use ConstantsTrait ;

    /**
     * `inProgress` — the key is claimed by a request still running. Typical answer : `429` with `Retry-After`.
     */
    public const string IN_PROGRESS = 'inProgress' ;

    /**
     * `invalid` — the `Idempotency-Key` header is empty or longer than the accepted length. Typical answer : `400`.
     */
    public const string INVALID = 'invalid' ;

    /**
     * `mismatch` — the key is known but was first sent with another body. Typical answer : `422`.
     */
    public const string MISMATCH = 'mismatch' ;

    /**
     * `missing` — the key is required and the request carries none. Typical answer : `400`.
     */
    public const string MISSING = 'missing' ;

    /**
     * `proceed` — the key is now claimed by this request : run it, then call `completeIdempotency()`.
     */
    public const string PROCEED = 'proceed' ;

    /**
     * `replay` — the key is known, with the same body, and its response is kept : serve it with `respondIdempotentReplay()`.
     */
    public const string REPLAY = 'replay' ;

    /**
     * `skip` — a safe method, or no key where it is optional : the request runs and nothing is kept.
     */
    public const string SKIP = 'skip' ;
}
