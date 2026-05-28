<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\problem ;

use Psr\Http\Message\ResponseInterface ;

use oihana\enums\http\HttpHeader ;
use oihana\middleware\problem\Problem ;

/**
 * Turns a PSR-7 response into a standardised Problem Details response
 * per [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457.html) (formerly
 * RFC 7807).
 *
 * Effects on the response :
 *
 * - **Status code** — taken from `$problem->status` when present,
 *   defaults to `400 Bad Request` when `null` so the helper always
 *   produces a syntactically valid error response. Callers wanting
 *   another default should pass the status on the `Problem`.
 * - **Content-Type** — set to `application/problem+json` per RFC §3.
 * - **Body** — the `Problem` is serialised via
 *   {@see Problem::toArray()} and written as compact JSON.
 *
 * PSR-7 immutable : a new response instance is returned, the input
 * response is never mutated. Pre-existing headers not touched by this
 * helper are preserved.
 *
 * @example
 * ```php
 * use oihana\middleware\problem\Problem ;
 *
 * use function oihana\middleware\helpers\problem\respondProblemDetails ;
 *
 * $problem = new Problem
 * (
 *     type     : 'https://api.example.com/probs/validation-failed' ,
 *     title    : 'Validation failed' ,
 *     status   : 422 ,
 *     detail   : 'Email must be unique.' ,
 *     instance : '/users' ,
 *     extensions :
 *     [
 *         'field' => 'email' ,
 *         'value' => 'jane@example.com' ,
 *     ] ,
 * ) ;
 *
 * return respondProblemDetails( $response , $problem ) ;
 * ```
 *
 * @param ResponseInterface $response The PSR-7 response to convert.
 * @param Problem           $problem  The Problem Details payload.
 *
 * @return ResponseInterface A new response carrying the status, the `application/problem+json` Content-Type and the JSON body.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\problem
 */
function respondProblemDetails( ResponseInterface $response , Problem $problem ) : ResponseInterface
{
    // Default to 400 when the Problem carries no status. The RFC itself
    // is silent on the default — picking 400 keeps the response a valid
    // "client error" by convention, which matches the most common use
    // case (validation failure, bad input). Callers wanting another
    // default pass it explicitly on the Problem.
    $status = $problem->status ?? 400 ;

    $response = $response
        ->withStatus( $status )
        ->withHeader( HttpHeader::CONTENT_TYPE , 'application/problem+json' ) ;

    // JSON_UNESCAPED_SLASHES keeps URI references readable. JSON_UNESCAPED_UNICODE
    // lets non-ASCII titles / details survive without \uXXXX bloat.
    $response->getBody()->write( json_encode
    (
        $problem->toArray() ,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ,
    ) ) ;

    return $response ;
}
