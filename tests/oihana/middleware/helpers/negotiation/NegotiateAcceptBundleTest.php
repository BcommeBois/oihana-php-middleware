<?php

namespace tests\oihana\middleware\helpers\negotiation ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ServerRequestInterface ;
use Slim\Psr7\Factory\ServerRequestFactory ;

use function oihana\middleware\helpers\negotiation\negotiateCharset ;
use function oihana\middleware\helpers\negotiation\negotiateEncoding ;
use function oihana\middleware\helpers\negotiation\negotiateLanguage ;

/**
 * Smoke coverage for the three Accept-* wrappers added in v0.7 (Lot B).
 *
 * The negotiation logic itself lives in `oihana/php-http` and is
 * tested there ; we only verify that each wrapper reads the correct
 * header and passes through to it.
 */
class NegotiateAcceptBundleTest extends TestCase
{
    private function newRequest( string $header , string $value ) :ServerRequestInterface
    {
        return ( new ServerRequestFactory() )->createServerRequest( 'GET' , '/' )->withHeader( $header , $value ) ;
    }

    // -------------------------------------------------------------------------
    // negotiateLanguage
    // -------------------------------------------------------------------------

    public function testNegotiateLanguagePicksHighestQValue() :void
    {
        $picked = negotiateLanguage
        (
            $this->newRequest( 'Accept-Language' , 'fr;q=0.8, en;q=0.5' ) ,
            [ 'en' , 'fr' , 'de' ] ,
        ) ;

        $this->assertSame( 'fr' , $picked ) ;
    }

    public function testNegotiateLanguageReturnsDefaultWhenHeaderAbsent() :void
    {
        $request = ( new ServerRequestFactory() )->createServerRequest( 'GET' , '/' ) ;

        $this->assertSame( 'en' , negotiateLanguage( $request , [ 'en' , 'fr' ] , 'en' ) ) ;
    }

    // -------------------------------------------------------------------------
    // negotiateEncoding
    // -------------------------------------------------------------------------

    public function testNegotiateEncodingPicksHighestQValue() :void
    {
        $picked = negotiateEncoding
        (
            $this->newRequest( 'Accept-Encoding' , 'br, gzip;q=0.8' ) ,
            [ 'br' , 'gzip' , 'identity' ] ,
        ) ;

        $this->assertSame( 'br' , $picked ) ;
    }

    public function testNegotiateEncodingReturnsDefaultWhenHeaderAbsent() :void
    {
        $request = ( new ServerRequestFactory() )->createServerRequest( 'GET' , '/' ) ;

        $this->assertSame
        (
            'identity' ,
            negotiateEncoding( $request , [ 'gzip' , 'identity' ] , 'identity' ) ,
        ) ;
    }

    // -------------------------------------------------------------------------
    // negotiateCharset
    // -------------------------------------------------------------------------

    public function testNegotiateCharsetPicksHighestQValue() :void
    {
        $picked = negotiateCharset
        (
            $this->newRequest( 'Accept-Charset' , 'utf-8, iso-8859-1;q=0.5' ) ,
            [ 'utf-8' , 'iso-8859-1' ] ,
        ) ;

        $this->assertSame( 'utf-8' , $picked ) ;
    }

    public function testNegotiateCharsetReturnsDefaultWhenHeaderAbsent() :void
    {
        $request = ( new ServerRequestFactory() )->createServerRequest( 'GET' , '/' ) ;

        $this->assertSame( 'utf-8' , negotiateCharset( $request , [ 'utf-8' ] , 'utf-8' ) ) ;
    }
}
