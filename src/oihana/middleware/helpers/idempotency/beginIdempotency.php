<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\idempotency ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\enums\Char ;
use oihana\enums\HashAlgorithm ;
use oihana\enums\http\HttpHeader ;
use oihana\enums\http\HttpMethod ;
use oihana\middleware\enums\IdempotencyOption ;
use oihana\middleware\enums\IdempotencyOutcome ;
use oihana\middleware\idempotency\IdempotencyDecision ;
use oihana\middleware\idempotency\IdempotencyStore ;

/**
 * Decides what to do with a request carrying — or lacking — an `Idempotency-Key` header, and
 * claims the key when the request may run.
 *
 * The outcomes, in the order they are checked ({@see IdempotencyOutcome}) :
 *
 * 1. `skip` — the method is not guarded (`GET`, `HEAD`, `OPTIONS` by default), or the header is
 *    absent and the key is optional. Nothing is claimed.
 * 2. `missing` — the header is absent and the key is `required`.
 * 3. `invalid` — the header is empty, or longer than `maxKeyLength` : refused rather than
 *    ignored, since an ignored key lets the request run unguarded.
 * 4. `proceed` — the key was free and is now claimed by this request for `lease` seconds : run
 *    the request, then hand the response to {@see completeIdempotency()}.
 * 5. `mismatch` — the key is held for another body (another fingerprint).
 * 6. `replay` — the key is held, for the same body, with a kept response : serve it with
 *    {@see respondIdempotentReplay()} without running the request.
 * 7. `inProgress` — the key is held, for the same body, by a request still running.
 *
 * The store key is `{keyPrefix}:{sha256(scope, METHOD, path, key)}` : two scopes, two methods or
 * two paths never share a key. 🚨 Pass the authenticated subject as `scope` — without it, every
 * caller shares the same keys, and a key read in a log would replay another account's answer.
 *
 * Side effect : on `proceed`, a running record is stored. The caller MUST then call
 * {@see completeIdempotency()}, or `$store->release()` when the request throws ; otherwise the
 * key stays claimed until its lease lapses.
 *
 * @param ServerRequestInterface $request The incoming PSR-7 request.
 * @param IdempotencyStore       $store   The store of the keys.
 * @param array<string, mixed>   $options Map keyed by {@see IdempotencyOption} constants.
 *
 * @return IdempotencyDecision The decision, to hand to {@see completeIdempotency()} once the request has run.
 *
 * @example
 * ```php
 * use oihana\middleware\enums\IdempotencyOption ;
 * use oihana\middleware\enums\IdempotencyOutcome ;
 *
 * use function oihana\middleware\helpers\idempotency\beginIdempotency ;
 * use function oihana\middleware\helpers\idempotency\completeIdempotency ;
 * use function oihana\middleware\helpers\idempotency\respondIdempotentReplay ;
 *
 * $options = [ IdempotencyOption::SCOPE => $userId ] ;
 *
 * $decision = beginIdempotency( $request , $store , $options ) ;
 *
 * return match ( $decision->outcome )
 * {
 *     IdempotencyOutcome::SKIP        => $handler->handle( $request ) ,
 *     IdempotencyOutcome::REPLAY      => respondIdempotentReplay( $responseFactory->createResponse() , $decision->record ) ,
 *     IdempotencyOutcome::PROCEED     => completeIdempotency( $decision , $handler->handle( $request ) , $store , $options ) ,
 *     IdempotencyOutcome::IN_PROGRESS => $responseFactory->createResponse( 429 )->withHeader( 'Retry-After' , (string) $decision->retryAfter ) ,
 *     IdempotencyOutcome::MISMATCH    => $responseFactory->createResponse( 422 ) ,
 *     default                         => $responseFactory->createResponse( 400 ) ,
 * } ;
 * ```
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\idempotency
 */
function beginIdempotency
(
    ServerRequestInterface $request ,
    IdempotencyStore       $store   ,
    array                  $options = []
)
: IdempotencyDecision
{
    $methods = $options[ IdempotencyOption::METHODS ] ?? null ;
    $methods = is_array( $methods )
             ? array_map( 'strtoupper' , array_filter( $methods , 'is_string' ) )
             : [ HttpMethod::POST , HttpMethod::PUT , HttpMethod::PATCH , HttpMethod::DELETE ] ;

    $method = strtoupper( $request->getMethod() ) ;

    if ( !in_array( $method , $methods , true ) )
    {
        return new IdempotencyDecision( IdempotencyOutcome::SKIP ) ;
    }

    if ( !$request->hasHeader( HttpHeader::IDEMPOTENCY_KEY ) )
    {
        $required = ( $options[ IdempotencyOption::REQUIRED ] ?? false ) === true ;
        return new IdempotencyDecision( $required ? IdempotencyOutcome::MISSING : IdempotencyOutcome::SKIP ) ;
    }

    $maxKeyLength = $options[ IdempotencyOption::MAX_KEY_LENGTH ] ?? 255 ;
    $maxKeyLength = ( is_int( $maxKeyLength ) && $maxKeyLength > 0 ) ? $maxKeyLength : 255 ;

    $key = trim( $request->getHeaderLine( HttpHeader::IDEMPOTENCY_KEY ) ) ;

    if ( $key === '' || strlen( $key ) > $maxKeyLength )
    {
        return new IdempotencyDecision( IdempotencyOutcome::INVALID ) ;
    }

    $scope = $options[ IdempotencyOption::SCOPE ] ?? null ;
    $scope = is_callable( $scope ) ? $scope( $request ) : $scope ;
    $scope = is_string( $scope ) ? $scope : '' ;

    $prefix = $options[ IdempotencyOption::KEY_PREFIX ] ?? 'idempotency' ;
    $prefix = ( is_string( $prefix ) && $prefix !== '' ) ? $prefix : 'idempotency' ;

    $lease = $options[ IdempotencyOption::LEASE ] ?? 120 ;
    $lease = ( is_int( $lease ) && $lease > 0 ) ? $lease : 120 ;

    $retryAfter = $options[ IdempotencyOption::RETRY_AFTER ] ?? 2 ;
    $retryAfter = ( is_int( $retryAfter ) && $retryAfter > 0 ) ? $retryAfter : 2 ;

    $storeKey    = $prefix . Char::COLON . hash( HashAlgorithm::SHA256 , serialize( [ $scope , $method , $request->getUri()->getPath() , $key ] ) ) ;
    $fingerprint = idempotencyFingerprint( $request ) ;

    $held = $store->begin( $storeKey , $fingerprint , $lease ) ;

    $outcome = match ( true )
    {
        $held === null                       => IdempotencyOutcome::PROCEED     ,
        $held->fingerprint !== $fingerprint  => IdempotencyOutcome::MISMATCH    ,
        $held->isCompleted()                 => IdempotencyOutcome::REPLAY      ,
        default                              => IdempotencyOutcome::IN_PROGRESS ,
    } ;

    return new IdempotencyDecision
    (
        outcome     : $outcome ,
        key         : $storeKey ,
        fingerprint : $fingerprint ,
        record      : $held ,
        retryAfter  : $outcome === IdempotencyOutcome::IN_PROGRESS ? $retryAfter : 0 ,
    ) ;
}
