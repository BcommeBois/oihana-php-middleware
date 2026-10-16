<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\idempotency ;

use oihana\enums\http\HttpStatusCode ;

/**
 * Indicates whether a response with the given status is kept under its idempotency key and
 * served again to every replay.
 *
 * Kept : the answers a replay would get again — `2xx`, `3xx`, and the `4xx` that depend on the
 * request only (`400`, `404`, `409`, `412`, `422`…).
 *
 * Not kept, so that a replay may succeed : every `5xx`, and the `4xx` that depend on the moment
 * rather than on the request — `401` (the session expired), `403` (a right not granted yet),
 * `408` (too slow), `425` (too early) and `429` (too many requests). The `1xx` are never final.
 *
 * @param int $status The status code of the response.
 *
 * @return bool `true` when the response is kept.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\idempotency\isIdempotencyRecordable ;
 *
 * isIdempotencyRecordable( 201 ) ; // true
 * isIdempotencyRecordable( 422 ) ; // true
 * isIdempotencyRecordable( 429 ) ; // false
 * isIdempotencyRecordable( 503 ) ; // false
 * ```
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\idempotency
 */
function isIdempotencyRecordable( int $status ) :bool
{
    if ( $status < HttpStatusCode::OK || $status >= HttpStatusCode::INTERNAL_SERVER_ERROR )
    {
        return false ;
    }

    return !in_array
    (
        $status ,
        [
            HttpStatusCode::UNAUTHORIZED      ,
            HttpStatusCode::FORBIDDEN         ,
            HttpStatusCode::REQUEST_TIMEOUT   ,
            HttpStatusCode::TOO_EARLY         ,
            HttpStatusCode::TOO_MANY_REQUESTS ,
        ] ,
        true
    ) ;
}
