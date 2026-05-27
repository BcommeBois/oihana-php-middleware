<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\cors ;

use InvalidArgumentException ;

use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface ;

use oihana\enums\http\HttpHeader ;
use oihana\enums\http\HttpMethod ;
use oihana\middleware\enums\CorsOption ;

/**
 * Applies the CORS response headers to a PSR-7 `Response`, including
 * preflight handling.
 *
 * Returns a new `ResponseInterface` (PSR-7 immutable) — the input
 * response is never mutated. The status code of the response is **not**
 * altered: callers wiring the helper inside a middleware decide whether
 * to short-circuit the preflight with a 204 or proceed to the next
 * handler.
 *
 * Algorithm:
 *
 * 1. Reads `Origin` from the request. Empty `Origin` ⇒ not a CORS
 *    request, the response is returned untouched.
 * 2. Resolves the `Access-Control-Allow-Origin` value:
 *    - `allowedOrigins: '*'` ⇒ `Allow-Origin: *`, no `Vary`. Throws
 *      when combined with `allowCredentials: true` (browsers reject
 *      this combination).
 *    - `allowedOrigins: ['https://app.example.com', ...]` and the
 *      request origin is in the list ⇒ `Allow-Origin: <origin>` plus
 *      `Vary: Origin`.
 *    - Otherwise ⇒ origin not allowed, response left untouched.
 * 3. When credentials are enabled, emits
 *    `Access-Control-Allow-Credentials: true`.
 * 4. When `exposedHeaders` is non-empty, emits
 *    `Access-Control-Expose-Headers: …`.
 * 5. If the request is a preflight (`OPTIONS` method + non-empty
 *    `Access-Control-Request-Method` header), additionally emits:
 *    - `Access-Control-Allow-Methods` from `allowedMethods` if set.
 *    - `Access-Control-Allow-Headers` from `allowedHeaders` if set,
 *      otherwise echoes back the client's
 *      `Access-Control-Request-Headers` (if any).
 *    - `Access-Control-Max-Age` from `maxAge` if a positive int.
 *
 * Option keys are exposed as typed constants on {@see CorsOption}.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\cors\applyCorsHeaders;
 * use oihana\middleware\enums\CorsOption;
 *
 * $response = applyCorsHeaders( $request , $response , [
 *     CorsOption::ALLOWED_ORIGINS   => [ 'https://app.example.com' ],
 *     CorsOption::ALLOWED_METHODS   => [ 'GET', 'POST', 'DELETE' ],
 *     CorsOption::ALLOWED_HEADERS   => [ 'Authorization', 'Content-Type' ],
 *     CorsOption::ALLOW_CREDENTIALS => true,
 *     CorsOption::MAX_AGE           => 3600,
 * ]) ;
 * ```
 *
 * @param ServerRequestInterface $request The incoming PSR-7 request.
 * @param ResponseInterface $response The PSR-7 response to augment.
 * @param array<string, mixed> $options Map of CORS options keyed by {@see CorsOption} constants.
 *
 * @return ResponseInterface A new response with the CORS headers applied (or unchanged when the request is not a CORS one).
 *
 * @throws InvalidArgumentException When `allowedOrigins: '*'` is combined with `allowCredentials: true`.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\cors
 */
function applyCorsHeaders( ServerRequestInterface $request , ResponseInterface $response , array $options = [] ) :ResponseInterface
{
    $origin = $request->getHeaderLine( HttpHeader::ORIGIN ) ;

    // Not a CORS request — leave the response untouched.
    if ( $origin === '' )
    {
        return $response ;
    }

    $allowedOrigins = $options[ CorsOption::ALLOWED_ORIGINS   ] ?? null ;
    $credentials    = ( $options[ CorsOption::ALLOW_CREDENTIALS ] ?? false ) === true ;

    // Resolve the Access-Control-Allow-Origin value.
    if ( $allowedOrigins === '*' )
    {
        if ( $credentials )
        {
            throw new InvalidArgumentException
            (
                'CORS: `allowedOrigins: "*"` and `allowCredentials: true` cannot be combined ' .
                '— browsers reject this configuration. Either list explicit origins or drop credentials.'
            ) ;
        }

        $allowOriginValue = '*' ;
        $varyOnOrigin     = false ;
    }
    elseif ( is_array( $allowedOrigins ) && in_array( $origin , $allowedOrigins , true ) )
    {
        $allowOriginValue = $origin ;
        $varyOnOrigin     = true ;
    }
    else
    {
        // Origin not allowed (or no allowlist supplied) — leave the
        // response untouched. The caller's routing logic decides the
        // 403 / 200 outcome separately.
        return $response ;
    }

    $response = $response->withHeader( HttpHeader::ACCESS_CONTROL_ALLOW_ORIGIN , $allowOriginValue ) ;

    if ( $varyOnOrigin )
    {
        $vary = $response->getHeader( HttpHeader::VARY ) ;

        if ( !in_array( HttpHeader::ORIGIN , $vary , true ) )
        {
            $response = $response->withAddedHeader( HttpHeader::VARY , HttpHeader::ORIGIN ) ;
        }
    }

    if ( $credentials )
    {
        $response = $response->withHeader( HttpHeader::ACCESS_CONTROL_ALLOW_CREDENTIALS , 'true' ) ;
    }

    $exposedHeaders = $options[ CorsOption::EXPOSED_HEADERS ] ?? [] ;

    if ( is_array( $exposedHeaders ) && $exposedHeaders !== [] )
    {
        $response = $response->withHeader
        (
            HttpHeader::ACCESS_CONTROL_EXPOSE_HEADERS ,
            implode( ', ' , $exposedHeaders ) ,
        ) ;
    }

    // Preflight: emit Allow-Methods, Allow-Headers, Max-Age.
    $isPreflight =
        $request->getMethod() === HttpMethod::OPTIONS &&
        $request->getHeaderLine( HttpHeader::ACCESS_CONTROL_REQUEST_METHOD ) !== '' ;

    if ( $isPreflight )
    {
        $allowedMethods = $options[ CorsOption::ALLOWED_METHODS ] ?? [] ;

        if ( is_array( $allowedMethods ) && $allowedMethods !== [] )
        {
            $response = $response->withHeader
            (
                HttpHeader::ACCESS_CONTROL_ALLOW_METHODS ,
                implode( ', ' , $allowedMethods ) ,
            ) ;
        }

        $allowedHeaders = $options[ CorsOption::ALLOWED_HEADERS ] ?? null ;

        if ( is_array( $allowedHeaders ) && $allowedHeaders !== [] )
        {
            $response = $response->withHeader
            (
                HttpHeader::ACCESS_CONTROL_ALLOW_HEADERS ,
                implode( ', ' , $allowedHeaders ) ,
            ) ;
        }
        else
        {
            // Echo back the client's Access-Control-Request-Headers, if any.
            $requested = $request->getHeaderLine( HttpHeader::ACCESS_CONTROL_REQUEST_HEADERS ) ;

            if ( $requested !== '' )
            {
                $response = $response->withHeader( HttpHeader::ACCESS_CONTROL_ALLOW_HEADERS , $requested ) ;
            }
        }

        $maxAge = $options[ CorsOption::MAX_AGE ] ?? null ;

        if ( is_int( $maxAge ) && $maxAge > 0 )
        {
            $response = $response->withHeader
            (
                HttpHeader::ACCESS_CONTROL_MAX_AGE ,
                (string) $maxAge ,
            ) ;
        }
    }

    return $response ;
}
