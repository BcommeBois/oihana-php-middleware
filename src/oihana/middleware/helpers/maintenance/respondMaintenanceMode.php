<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\maintenance ;

use DateTimeInterface ;

use Psr\Http\Message\ResponseInterface ;

use oihana\enums\http\HttpHeader ;
use oihana\enums\http\HttpStatusCode ;
use oihana\middleware\enums\MaintenanceOption ;

use function oihana\http\helpers\dates\formatHttpDate ;

/**
 * Turns a PSR-7 response into a clean `503 Service Unavailable`
 * maintenance-mode response, with optional `Retry-After` header and
 * optional body message.
 *
 * Returns a new `ResponseInterface` — the input response status is
 * overridden (PSR-7 `withStatus`), and the body is written to via the
 * underlying `StreamInterface`. Pre-existing headers not touched by
 * the options are preserved.
 *
 * Supported options (keys exposed as typed constants in
 * {@see MaintenanceOption}):
 *
 * - `RETRY_AFTER` — value of the `Retry-After` header. Three forms accepted:
 *   - `int` — delta-seconds (RFC 7231 §7.1.3 form 1). Must be positive.
 *   - `DateTimeInterface` — absolute moment, formatted as IMF-fixdate via {@see formatHttpDate()}.
 *   - non-empty `string` — passed through verbatim (caller is responsible for the format).
 *   - Omitted / `null` / invalid ⇒ no `Retry-After` header.
 * - `MESSAGE` (`string`) — body content. Omitted, `null` or empty string ⇒ no body.
 * - `CONTENT_TYPE` (`string`) — `Content-Type` of the body. Only used when `MESSAGE` is supplied. Defaults to `text/plain; charset=utf-8`.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\maintenance\respondMaintenanceMode ;
 * use oihana\middleware\enums\MaintenanceOption ;
 *
 * // Plain delta-seconds with a JSON body.
 * $response = respondMaintenanceMode( $response ,
 * [
 *     MaintenanceOption::RETRY_AFTER  => 120 ,
 *     MaintenanceOption::MESSAGE      => json_encode( [ 'status' => 'maintenance' , 'eta' => 120 ] ) ,
 *     MaintenanceOption::CONTENT_TYPE => 'application/json' ,
 * ]) ;
 *
 * // Absolute moment (HTTP-date), no body.
 * $response = respondMaintenanceMode( $response ,
 * [
 *     MaintenanceOption::RETRY_AFTER => new DateTimeImmutable( '+30 minutes' ) ,
 * ]) ;
 * ```
 *
 * @param ResponseInterface $response The PSR-7 response to convert.
 * @param array<string, mixed> $options Map of options keyed by {@see MaintenanceOption} constants.
 *
 * @return ResponseInterface A new response carrying the 503 status, the optional headers and body.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\maintenance
 */
function respondMaintenanceMode( ResponseInterface $response , array $options = [] ) :ResponseInterface
{
    // 1. Status 503.
    $response = $response->withStatus( HttpStatusCode::SERVICE_UNAVAILABLE , 'Service Unavailable' ) ;

    // 2. Retry-After header.
    $retryAfter = $options[ MaintenanceOption::RETRY_AFTER ] ?? null ;

    if ( is_int( $retryAfter ) && $retryAfter > 0 )
    {
        $response = $response->withHeader( HttpHeader::RETRY_AFTER , (string) $retryAfter ) ;
    }
    elseif ( $retryAfter instanceof DateTimeInterface )
    {
        $response = $response->withHeader( HttpHeader::RETRY_AFTER , formatHttpDate( $retryAfter ) ) ;
    }
    elseif ( is_string( $retryAfter ) && $retryAfter !== '' )
    {
        $response = $response->withHeader( HttpHeader::RETRY_AFTER , $retryAfter ) ;
    }

    // 3. Body + Content-Type when a message is supplied.
    $message = $options[ MaintenanceOption::MESSAGE ] ?? null ;

    if ( is_string( $message ) && $message !== '' )
    {
        $contentType = $options[ MaintenanceOption::CONTENT_TYPE ] ?? 'text/plain; charset=utf-8' ;

        if ( !is_string( $contentType ) || $contentType === '' )
        {
            $contentType = 'text/plain; charset=utf-8' ;
        }

        $response = $response->withHeader( HttpHeader::CONTENT_TYPE , $contentType ) ;
        $response->getBody()->write( $message ) ;
    }

    return $response ;
}
