<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\idempotency ;

use Psr\Http\Message\ServerRequestInterface ;
use Psr\Http\Message\StreamInterface ;
use Psr\Http\Message\UploadedFileInterface ;

use oihana\enums\HashAlgorithm ;

/**
 * Computes the SHA-256 fingerprint of a request body, to tell a replay of an idempotency key
 * (same body) from a reuse of the key for another request (another body).
 *
 * The fingerprint covers :
 *
 * - the **parsed body** (`getParsedBody()`), with the keys of every map sorted recursively, so
 *   that the same object gives the same fingerprint whatever the order of its members ; an
 *   object is read as the map of its public properties. Without a parsed body, the
 *   **raw body** is read instead (rewound before and after when the stream is seekable) ;
 * - each **uploaded file** (`getUploadedFiles()`, nested fields included) : its field path,
 *   client file name, client media type and the SHA-256 of its content, read by chunks —
 *   no byte of a file is kept.
 *
 * 🚨 A multipart body is never fingerprinted raw : the client draws a new boundary on every
 * attempt, so the raw bytes of two identical replays differ.
 *
 * The method and the path are not part of the fingerprint : they belong to the store key.
 *
 * @param ServerRequestInterface $request The incoming PSR-7 request.
 *
 * @return string The fingerprint, as 64 hexadecimal characters.
 *
 * @example
 * ```php
 * use function oihana\middleware\helpers\idempotency\idempotencyFingerprint ;
 *
 * $first  = idempotencyFingerprint( $request->withParsedBody( [ 'name' => 'Desk' , 'quantity' => 2 ] ) ) ;
 * $second = idempotencyFingerprint( $request->withParsedBody( [ 'quantity' => 2 , 'name' => 'Desk' ] ) ) ;
 *
 * $first === $second ; // true
 * ```
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\idempotency
 */
function idempotencyFingerprint( ServerRequestInterface $request ) :string
{
    $readChunked = static function( StreamInterface $stream , callable $consume ) :void
    {
        if ( $stream->isSeekable() )
        {
            $stream->rewind() ;
        }

        while ( !$stream->eof() )
        {
            $consume( $stream->read( 65536 ) ) ;
        }

        if ( $stream->isSeekable() )
        {
            $stream->rewind() ;
        }
    } ;

    $canonical = static function( mixed $value ) use ( &$canonical ) :mixed
    {
        if ( is_object( $value ) )
        {
            $value = get_object_vars( $value ) ;
        }

        if ( !is_array( $value ) )
        {
            return $value ;
        }

        $value = array_map( $canonical , $value ) ;

        if ( !array_is_list( $value ) )
        {
            ksort( $value , SORT_STRING ) ;
        }

        return $value ;
    } ;

    $parsed = $request->getParsedBody() ;
    $raw    = null ;

    if ( $parsed === null )
    {
        $context = hash_init( HashAlgorithm::SHA256 ) ;
        $readChunked( $request->getBody() , static fn( string $chunk ) => hash_update( $context , $chunk ) ) ;
        $raw = hash_final( $context ) ;
    }

    $files = [] ;

    $collect = static function( array $uploaded , array $path ) use ( &$collect , &$files , $readChunked ) :void
    {
        foreach ( $uploaded as $name => $item )
        {
            $itemPath = [ ...$path , $name ] ;

            if ( $item instanceof UploadedFileInterface )
            {
                $context = hash_init( HashAlgorithm::SHA256 ) ;
                $readChunked( $item->getStream() , static fn( string $chunk ) => hash_update( $context , $chunk ) ) ;

                $files[] =
                [
                    $itemPath ,
                    $item->getClientFilename() ,
                    $item->getClientMediaType() ,
                    hash_final( $context ) ,
                ] ;
            }
            elseif ( is_array( $item ) )
            {
                $collect( $item , $itemPath ) ;
            }
        }
    } ;

    $collect( $request->getUploadedFiles() , [] ) ;

    sort( $files ) ;

    return hash( HashAlgorithm::SHA256 , serialize( [ $canonical( $parsed ) , $raw , $files ] ) ) ;
}
