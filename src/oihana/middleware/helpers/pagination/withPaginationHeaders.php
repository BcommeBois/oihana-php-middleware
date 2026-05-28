<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\pagination ;

use Psr\Http\Message\ResponseInterface ;

use oihana\enums\http\HttpHeader ;
use oihana\middleware\pagination\PaginationLinks ;

/**
 * Stamps pagination headers on a PSR-7 response from a
 * {@see PaginationLinks} value object.
 *
 * Emits two headers :
 *
 * - **`Link`** ([RFC 5988](https://www.rfc-editor.org/rfc/rfc5988.html),
 *   updated by [RFC 8288](https://www.rfc-editor.org/rfc/rfc8288.html)) :
 *   serialises the non-null URIs from the value object as
 *   `<uri>; rel="rel-name"` entries, joined by `, `. Standard rel
 *   names emitted : `first`, `prev`, `next`, `last`. When ALL four
 *   URIs are `null`, the header is omitted entirely.
 * - **`X-Total-Count`** (de-facto convention popularised by GitHub) :
 *   emitted when `$links->totalCount !== null`. The standard does NOT
 *   reserve a header for the total — `X-Total-Count` is the de-facto
 *   choice ; if your client expects another (`Total-Count`, `Total`),
 *   stamp it yourself via `withHeader()`.
 *
 * PSR-7 immutable : a new response instance is returned, the input
 * response is never mutated.
 *
 * @example
 * ```php
 * use oihana\middleware\pagination\PaginationLinks ;
 *
 * use function oihana\middleware\helpers\pagination\withPaginationHeaders ;
 *
 * $links = new PaginationLinks
 * (
 *     first      : 'https://api.example.com/users?page=1' ,
 *     prev       : 'https://api.example.com/users?page=2' ,
 *     next       : 'https://api.example.com/users?page=4' ,
 *     last       : 'https://api.example.com/users?page=10' ,
 *     totalCount : 482 ,
 * ) ;
 *
 * return withPaginationHeaders( $response , $links ) ;
 * ```
 *
 * Output :
 *
 * ```
 * Link: <https://api.example.com/users?page=1>; rel="first",
 *       <https://api.example.com/users?page=2>; rel="prev",
 *       <https://api.example.com/users?page=4>; rel="next",
 *       <https://api.example.com/users?page=10>; rel="last"
 * X-Total-Count: 482
 * ```
 *
 * @param ResponseInterface $response The PSR-7 response to stamp.
 * @param PaginationLinks   $links    The pagination links + optional total count.
 *
 * @return ResponseInterface A new response carrying the `Link` and (optionally) `X-Total-Count` headers.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\pagination
 */
function withPaginationHeaders( ResponseInterface $response , PaginationLinks $links ) : ResponseInterface
{
    $parts = [] ;

    if ( $links->first !== null )
    {
        $parts[] = '<' . $links->first . '>; rel="first"' ;
    }

    if ( $links->prev !== null )
    {
        $parts[] = '<' . $links->prev . '>; rel="prev"' ;
    }

    if ( $links->next !== null )
    {
        $parts[] = '<' . $links->next . '>; rel="next"' ;
    }

    if ( $links->last !== null )
    {
        $parts[] = '<' . $links->last . '>; rel="last"' ;
    }

    if ( $parts !== [] )
    {
        $response = $response->withHeader( HttpHeader::LINK , implode( ', ' , $parts ) ) ;
    }

    if ( $links->totalCount !== null )
    {
        // X-Total-Count is de-facto, not RFC. Documented in the wiki page so
        // callers picking a different header name (Total-Count, Total, ...)
        // know to override with withHeader() themselves.
        $response = $response->withHeader( 'X-Total-Count' , (string) $links->totalCount ) ;
    }

    return $response ;
}
