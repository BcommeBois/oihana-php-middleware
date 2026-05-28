<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\cache ;

use DateTimeInterface ;

use Psr\Http\Message\ServerRequestInterface ;

use oihana\enums\http\HttpHeader ;

use function oihana\http\helpers\dates\parseHttpDate ;

/**
 * Evaluates HTTP conditional-request preconditions against a known
 * `ETag` (and optional `Last-Modified` date) for the resource.
 *
 * Returns `true` when the response would be a `304 Not Modified` for
 * a `GET` / `HEAD` request — meaning the client already holds a
 * fresh copy and we should skip rebuilding / re-sending the body.
 *
 * Precondition resolution per [RFC 9110 §13.1.3](https://www.rfc-editor.org/rfc/rfc9110#section-13.1.3) :
 *
 * 1. If `If-None-Match` is present, it is evaluated and its result
 *    decides the outcome — `If-Modified-Since` is ignored.
 * 2. Otherwise, if `If-Modified-Since` is present AND a
 *    `$lastModified` is supplied, the date is compared (with
 *    second-level granularity per HTTP-date semantics).
 *
 * `If-None-Match` semantics :
 *
 * - `*` matches anything — always returns `true` (the resource
 *   exists, which is enough for the wildcard).
 * - A comma-separated list of etags : `true` when ANY entry matches
 *   the supplied `$etag` using **weak comparison** — the `W/` prefix
 *   is stripped on both sides before comparing, per RFC 9110
 *   §8.8.3.2 (the weak/strong distinction matters for `If-Match` /
 *   `If-Range`, not for `If-None-Match`).
 *
 * `If-Modified-Since` semantics :
 *
 * - The header is parsed via
 *   {@see \oihana\http\helpers\dates\parseHttpDate()} which handles
 *   the three HTTP-date formats (IMF-fixdate, RFC 850, asctime).
 * - Returns `true` when `$lastModified->getTimestamp()` <=
 *   `$ifModifiedSince->getTimestamp()` — the resource has not been
 *   modified since the client last fetched it.
 * - Malformed `If-Modified-Since` ⇒ returns `false` (defensive : if
 *   we can't parse the date, we can't claim the client is up-to-date).
 *
 * When neither precondition header is present, returns `false` — the
 * caller proceeds to build and send the full response.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\cache\isNotModified ;
 * use function oihana\middleware\helpers\cache\respondNotModified ;
 *
 * $etag         = '"v42"' ;
 * $lastModified = new DateTimeImmutable( '2026-05-28 10:32:14' ) ;
 *
 * if ( isNotModified( $request , $etag , $lastModified ) )
 * {
 *     return respondNotModified( $responseFactory->createResponse() , $etag ) ;
 * }
 *
 * // ... build the full body, stamp ETag/Last-Modified, return 200 ...
 * ```
 *
 * @param ServerRequestInterface $request      The incoming PSR-7 request.
 * @param string                 $etag         Current ETag of the resource (caller-supplied — typically `'"v42"'` strong or `'W/"v42"'` weak). Empty string ⇒ no etag check, only date.
 * @param DateTimeInterface|null $lastModified Optional `Last-Modified` reference of the resource. When `null`, only `If-None-Match` is evaluated.
 *
 * @return bool `true` when the client's cached copy is still fresh, `false` when the full response should be generated.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\cache
 */
function isNotModified
(
    ServerRequestInterface $request ,
    string                 $etag ,
    ?DateTimeInterface     $lastModified = null ,
)
: bool
{
    $ifNoneMatch = $request->getHeaderLine( HttpHeader::IF_NONE_MATCH ) ;

    if ( $ifNoneMatch !== '' )
    {
        // Per RFC 9110 §13.1.3, If-None-Match takes precedence over
        // If-Modified-Since when both are present. We don't fall through to
        // the date check even if the etag comparison says "not matched" —
        // that's an explicit "client's cache is invalid, rebuild" signal.
        return matchIfNoneMatch( $ifNoneMatch , $etag ) ;
    }

    if ( $lastModified === null )
    {
        return false ;
    }

    $ifModifiedSince = $request->getHeaderLine( HttpHeader::IF_MODIFIED_SINCE ) ;

    if ( $ifModifiedSince === '' )
    {
        return false ;
    }

    $since = parseHttpDate( $ifModifiedSince ) ;

    if ( $since === null )
    {
        // Malformed HTTP-date — can't trust it. Force a full response.
        return false ;
    }

    return $lastModified->getTimestamp() <= $since->getTimestamp() ;
}

/**
 * Tests whether an `If-None-Match` header value matches a given etag.
 *
 * Internal helper. Public so callers that already have a parsed
 * precondition header can reuse the comparison without re-walking
 * the request.
 *
 * @param string $ifNoneMatch The raw `If-None-Match` header value (already known to be non-empty).
 * @param string $etag        The current etag of the resource. Empty string ⇒ no possible match.
 *
 * @return bool `true` when at least one entry matches (or on the `*` wildcard).
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\cache
 */
function matchIfNoneMatch( string $ifNoneMatch , string $etag ) : bool
{
    $trimmed = trim( $ifNoneMatch ) ;

    if ( $trimmed === '*' )
    {
        // Wildcard matches anything that exists — per RFC, callers should
        // only send `*` against resources known to exist, but we don't
        // re-validate that here.
        return true ;
    }

    if ( $etag === '' )
    {
        return false ;
    }

    $normalisedEtag = stripWeakPrefix( $etag ) ;

    foreach ( explode( ',' , $ifNoneMatch ) as $candidate )
    {
        if ( stripWeakPrefix( trim( $candidate ) ) === $normalisedEtag )
        {
            return true ;
        }
    }

    return false ;
}

/**
 * Strips the optional `W/` weak-validator prefix from an etag.
 *
 * Internal helper. The weak/strong distinction matters for `If-Match`
 * and `If-Range` (which require strong comparison per RFC 9110
 * §8.8.3.1), but for `If-None-Match` weak comparison is the rule
 * (§8.8.3.2) — both sides are normalised to their strong form before
 * comparing.
 *
 * @param string $etag An etag value, optionally prefixed with `W/`.
 *
 * @return string The etag without the weak-validator prefix.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\cache
 */
function stripWeakPrefix( string $etag ) : string
{
    return str_starts_with( $etag , 'W/' ) ? substr( $etag , 2 ) : $etag ;
}
