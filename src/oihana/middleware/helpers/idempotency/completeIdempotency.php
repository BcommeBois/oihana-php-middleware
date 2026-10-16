<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\idempotency ;

use Psr\Http\Message\ResponseInterface ;

use oihana\enums\http\HttpHeader ;
use oihana\middleware\enums\IdempotencyOption ;
use oihana\middleware\enums\IdempotencyOutcome ;
use oihana\middleware\idempotency\IdempotencyDecision ;
use oihana\middleware\idempotency\IdempotencyRecord ;
use oihana\middleware\idempotency\IdempotencyStore ;

/**
 * Keeps the response of a request that ran with its idempotency key claimed — or frees the key
 * when the response must not be kept — and returns the response untouched.
 *
 * Acts only on a `proceed` decision of {@see beginIdempotency()} ; any other decision returns
 * the response as is.
 *
 * - When {@see isIdempotencyRecordable()} accepts the status, the store keeps, for `ttl` seconds,
 *   the status, the response headers listed in `headers` and the body.
 * - Otherwise (`5xx`, `401`, `403`, `408`, `425`, `429`), the key is released : the next replay
 *   runs the request again and may succeed.
 *
 * 🚨 The body is read to be kept. A seekable body is rewound afterwards ; a non-seekable body
 * cannot be read without being consumed, so its response is never kept and the key is released.
 *
 * @param IdempotencyDecision  $decision The decision returned by {@see beginIdempotency()}.
 * @param ResponseInterface    $response The response the request produced.
 * @param IdempotencyStore     $store    The store of the keys.
 * @param array<string, mixed> $options  Map keyed by {@see IdempotencyOption} constants ; reads `ttl` and `headers`.
 *
 * @return ResponseInterface The same response, its body rewound when seekable.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\idempotency
 */
function completeIdempotency
(
    IdempotencyDecision $decision ,
    ResponseInterface   $response ,
    IdempotencyStore    $store    ,
    array               $options  = []
)
: ResponseInterface
{
    if ( $decision->outcome !== IdempotencyOutcome::PROCEED || $decision->key === null || $decision->fingerprint === null )
    {
        return $response ;
    }

    $body = $response->getBody() ;

    if ( !isIdempotencyRecordable( $response->getStatusCode() ) || !$body->isSeekable() )
    {
        $store->release( $decision->key ) ;
        return $response ;
    }

    $ttl = $options[ IdempotencyOption::TTL ] ?? 604800 ;
    $ttl = ( is_int( $ttl ) && $ttl > 0 ) ? $ttl : 604800 ;

    $names = $options[ IdempotencyOption::HEADERS ] ?? null ;
    $names = is_array( $names )
           ? array_filter( $names , 'is_string' )
           : [ HttpHeader::CONTENT_TYPE , HttpHeader::CONTENT_LOCATION , HttpHeader::LOCATION , HttpHeader::ETAG , HttpHeader::LAST_MODIFIED ] ;

    $headers = [] ;

    foreach ( $names as $name )
    {
        if ( $response->hasHeader( $name ) )
        {
            $headers[ $name ] = $response->getHeader( $name ) ;
        }
    }

    $body->rewind() ;
    $content = $body->getContents() ;
    $body->rewind() ;

    $store->complete
    (
        $decision->key ,
        new IdempotencyRecord
        (
            fingerprint : $decision->fingerprint ,
            status      : $response->getStatusCode() ,
            headers     : $headers ,
            body        : $content ,
        ) ,
        $ttl
    ) ;

    return $response ;
}
