<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Option keys accepted by {@see \oihana\middleware\helpers\idempotency\beginIdempotency()} and
 * {@see \oihana\middleware\helpers\idempotency\completeIdempotency()}.
 *
 * The options are read on every call : a caller may resolve them per request (per route, per
 * client application) without any change to the helpers.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class IdempotencyOption
{
    use ConstantsTrait ;

    /**
     * `headers` — response headers kept with the response and served again on a replay. `list<string>`, defaults to `Content-Type`, `Content-Location`, `Location`, `ETag` and `Last-Modified`. Read by `completeIdempotency()`.
     */
    public const string HEADERS = 'headers' ;

    /**
     * `keyPrefix` — prefix of every store key. Defaults to `'idempotency'`. Isolates several users of the same store backend.
     */
    public const string KEY_PREFIX = 'keyPrefix' ;

    /**
     * `lease` — seconds a running request holds its key. Past it, the claim of a process that died is taken over by the next replay. Positive `int`, defaults to `120`.
     */
    public const string LEASE = 'lease' ;

    /**
     * `maxKeyLength` — longest `Idempotency-Key` accepted, in bytes. A longer one is `invalid`, never ignored. Positive `int`, defaults to `255`.
     */
    public const string MAX_KEY_LENGTH = 'maxKeyLength' ;

    /**
     * `methods` — the methods guarded. `list<string>`, defaults to `POST`, `PUT`, `PATCH` and `DELETE`. Any other method is `skip`.
     */
    public const string METHODS = 'methods' ;

    /**
     * `required` — `true` when a guarded request without the header is `missing` rather than `skip`. `bool`, defaults to `false`.
     */
    public const string REQUIRED = 'required' ;

    /**
     * `retryAfter` — seconds suggested to a client whose key is `inProgress`. Positive `int`, defaults to `2`.
     */
    public const string RETRY_AFTER = 'retryAfter' ;

    /**
     * `scope` — the account the key belongs to : a `string`, a `callable` `fn(ServerRequestInterface): ?string`, or `null`. Two scopes never share a key. Without a scope, every caller shares the same keys : pass the authenticated subject.
     */
    public const string SCOPE = 'scope' ;

    /**
     * `ttl` — seconds a completed response is kept. Positive `int`, defaults to `604800` (7 days). Read by `completeIdempotency()`.
     */
    public const string TTL = 'ttl' ;
}
