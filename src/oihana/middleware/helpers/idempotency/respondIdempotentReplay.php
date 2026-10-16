<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\idempotency ;

use InvalidArgumentException ;

use Psr\Http\Message\ResponseInterface ;

use oihana\enums\Boolean ;
use oihana\enums\http\HttpHeader ;
use oihana\middleware\idempotency\IdempotencyRecord ;

/**
 * Serves again the response kept under an idempotency key, without running the request.
 *
 * Effects on the response : the kept status, the kept headers (replacing any value already set),
 * the kept body written to the response body, and `Idempotent-Replayed: true` — so the client
 * can tell a replay from a first execution.
 *
 * PSR-7 immutable : a new response instance is returned. Pass a fresh response (an empty
 * body) : the kept body is written after whatever the body already holds.
 *
 * @param ResponseInterface $response A fresh PSR-7 response to fill.
 * @param IdempotencyRecord $record   The completed record, from a `replay` decision.
 *
 * @return ResponseInterface The replayed response.
 *
 * @throws InvalidArgumentException When the record is still running : it holds no response to serve.
 *
 * @example
 * ```php
 * use oihana\middleware\enums\IdempotencyOutcome ;
 *
 * use function oihana\middleware\helpers\idempotency\respondIdempotentReplay ;
 *
 * if ( $decision->outcome === IdempotencyOutcome::REPLAY )
 * {
 *     return respondIdempotentReplay( $responseFactory->createResponse() , $decision->record ) ;
 * }
 * ```
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\idempotency
 */
function respondIdempotentReplay( ResponseInterface $response , IdempotencyRecord $record ) :ResponseInterface
{
    if ( $record->status === null )
    {
        throw new InvalidArgumentException( 'A running idempotency record holds no response to replay.' ) ;
    }

    $response = $response->withStatus( $record->status ) ;

    foreach ( $record->headers as $name => $values )
    {
        $response = $response->withHeader( $name , $values ) ;
    }

    $response = $response->withHeader( HttpHeader::IDEMPOTENT_REPLAYED , Boolean::TRUE ) ;

    $response->getBody()->write( $record->body ?? '' ) ;

    return $response ;
}
