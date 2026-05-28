<?php

namespace tests\oihana\middleware\helpers\negotiation ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ServerRequestInterface ;
use Slim\Psr7\Factory\ServerRequestFactory ;

use function oihana\middleware\helpers\negotiation\negotiateMimeType ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\negotiation\negotiateMimeType()}.
 */
class NegotiateMimeTypeTest extends TestCase
{
    private function newRequest( ?string $accept = null ) :ServerRequestInterface
    {
        $request = ( new ServerRequestFactory() )->createServerRequest( 'GET' , '/' ) ;

        return $accept !== null
             ? $request->withHeader( 'Accept' , $accept )
             : $request ;
    }

    public function testSelectsHighestQValueMatch() :void
    {
        $picked = negotiateMimeType
        (
            $this->newRequest( 'text/html;q=0.9, application/json' ) ,
            [ 'application/json' , 'text/html' ] ,
        ) ;

        $this->assertSame( 'application/json' , $picked ) ;
    }

    public function testHonoursServerPreferenceOrderOnQTie() :void
    {
        $picked = negotiateMimeType
        (
            $this->newRequest( 'application/json, text/html' ) ,
            [ 'text/html' , 'application/json' ] ,
        ) ;

        // Both at q=1.0 — the helper walks the parsed Accept entries in order,
        // so the first Accept token that matches wins (json before html here).
        $this->assertSame( 'application/json' , $picked ) ;
    }

    public function testReturnsNullWhenNoMatchAndNoDefault() :void
    {
        $picked = negotiateMimeType
        (
            $this->newRequest( 'application/xml' ) ,
            [ 'application/json' , 'text/html' ] ,
        ) ;

        $this->assertNull( $picked ) ;
    }

    public function testReturnsDefaultWhenNoMatch() :void
    {
        $picked = negotiateMimeType
        (
            $this->newRequest( 'application/xml' ) ,
            [ 'application/json' , 'text/html' ] ,
            'application/json' ,
        ) ;

        $this->assertSame( 'application/json' , $picked ) ;
    }

    public function testReturnsDefaultWhenNoAcceptHeader() :void
    {
        $picked = negotiateMimeType
        (
            $this->newRequest() ,
            [ 'application/json' , 'text/html' ] ,
            'application/json' ,
        ) ;

        $this->assertSame( 'application/json' , $picked ) ;
    }

    public function testUniversalWildcardMatchesFirstSupported() :void
    {
        $picked = negotiateMimeType
        (
            $this->newRequest( '*/' . '*' ) ,
            [ 'text/html' , 'application/json' ] ,
        ) ;

        $this->assertSame( 'text/html' , $picked ) ;
    }

    public function testTypeWildcardMatchesAnySubtype() :void
    {
        $picked = negotiateMimeType
        (
            $this->newRequest( 'text/*' ) ,
            [ 'application/json' , 'text/csv' , 'text/html' ] ,
        ) ;

        $this->assertSame( 'text/csv' , $picked ) ;
    }

    public function testExplicitRefusalIsSkipped() :void
    {
        $picked = negotiateMimeType
        (
            $this->newRequest( 'application/json;q=0, text/html' ) ,
            [ 'application/json' , 'text/html' ] ,
        ) ;

        // q=0 is an explicit refusal — `application/json` is skipped.
        $this->assertSame( 'text/html' , $picked ) ;
    }

    public function testEmptySupportedListReturnsDefault() :void
    {
        $picked = negotiateMimeType
        (
            $this->newRequest( 'application/json' ) ,
            [] ,
            'application/json' ,
        ) ;

        $this->assertSame( 'application/json' , $picked ) ;
    }
}
