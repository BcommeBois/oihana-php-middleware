<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\body ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\enums\http\HttpHeader ;

/**
 * Checks whether the incoming request body fits within a declared
 * maximum size, reading the `Content-Length` header.
 *
 * Defense-in-depth helper for the pre-parsing stage : when an attacker
 * (or a misconfigured client) sends a multi-gigabyte body to an
 * endpoint that expects a small JSON payload, calling this BEFORE the
 * body parser lets the application reject the request based on the
 * declared length alone — no memory allocation, no parsing, no
 * streaming.
 *
 * Behaviour :
 *
 * - **No `Content-Length` header** ⇒ returns `true`. The helper cannot
 *   verify a streaming or chunked-encoded body without consuming it ;
 *   that concern belongs further up the stack (web server, PHP
 *   configuration `post_max_size` / `upload_max_filesize`, body parser
 *   streaming guards).
 * - **`Content-Length` ≤ `$maxBytes`** ⇒ returns `true`.
 * - **`Content-Length` > `$maxBytes`** ⇒ returns `false`. The caller
 *   typically responds with `413 Payload Too Large` (HTTP status code
 *   exposed as {@see \oihana\enums\http\HttpStatusCode::PAYLOAD_TOO_LARGE}).
 * - **`Content-Length` malformed** (negative, non-numeric, leading
 *   sign, multiple values) ⇒ returns `false`. Strict defensive default :
 *   if the helper cannot trust the declared length, the request is
 *   rejected rather than let through under an unknown size.
 *
 * The helper does not consume the body stream — it only inspects the
 * header. Multiple calls are safe and idempotent.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\body\enforceMaxBodySize ;
 * use oihana\enums\http\HttpStatusCode ;
 *
 * // Reject any body larger than 10 MiB before parsing.
 * if ( !enforceMaxBodySize( $request , 10 * 1024 * 1024 ) )
 * {
 *     return $responseFactory->createResponse( HttpStatusCode::PAYLOAD_TOO_LARGE ) ;
 * }
 *
 * $parsed = json_decode( (string) $request->getBody() , true ) ;
 * ```
 *
 * @param ServerRequestInterface $request  The incoming PSR-7 request.
 * @param int                    $maxBytes Maximum allowed body size in bytes. Must be positive.
 *
 * @return bool `true` when the body fits (or its length is unknown), `false` when it exceeds the limit or carries a malformed `Content-Length`.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\body
 */
function enforceMaxBodySize( ServerRequestInterface $request , int $maxBytes ) : bool
{
    $header = $request->getHeaderLine( HttpHeader::CONTENT_LENGTH ) ;

    if ( $header === '' )
    {
        // No declared length — can't verify here, let the caller deal with
        // streaming concerns higher up the stack.
        return true ;
    }

    // ctype_digit guards against leading signs, decimal points, spaces, and
    // non-numeric characters in a single pass — stricter than is_numeric().
    if ( !ctype_digit( $header ) )
    {
        return false ;
    }

    // PHP int cast on a digit-only string >= PHP_INT_MAX returns PHP_INT_MAX
    // (saturation, not overflow), which is already larger than any reasonable
    // $maxBytes — so the comparison stays correct on 64-bit platforms.
    return (int) $header <= $maxBytes ;
}
