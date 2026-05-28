<?php

namespace tests\oihana\middleware\helpers\cors ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ServerRequestInterface ;
use Slim\Psr7\Factory\ServerRequestFactory ;

use function oihana\middleware\helpers\cors\isCorsPreflight ;
use function oihana\middleware\helpers\cors\isCorsRequest ;

/**
 * Unit coverage for the CORS predicate helpers
 * {@see \oihana\middleware\helpers\cors\isCorsRequest()} and
 * {@see \oihana\middleware\helpers\cors\isCorsPreflight()}.
 */
class CorsPredicatesTest extends TestCase
{
    private function newRequest( string $method = 'GET' ) :ServerRequestInterface
    {
        return ( new ServerRequestFactory() )->createServerRequest( $method , '/' ) ;
    }

    public function testIsCorsRequestFalseWhenNoOriginHeader() :void
    {
        $this->assertFalse( isCorsRequest( $this->newRequest() ) ) ;
    }

    public function testIsCorsRequestTrueWhenOriginHeaderPresent() :void
    {
        $request = $this->newRequest()->withHeader( 'Origin' , 'https://app.example.com' ) ;

        $this->assertTrue( isCorsRequest( $request ) ) ;
    }

    public function testIsCorsRequestTrueEvenWithEmptyOriginValue() :void
    {
        // `hasHeader` is true if the header is set, even with an empty value
        // (Fetch spec calls this a "tainted same-origin" request).
        $request = $this->newRequest()->withHeader( 'Origin' , '' ) ;

        $this->assertTrue( isCorsRequest( $request ) ) ;
    }

    public function testIsCorsPreflightFalseWhenMethodIsNotOptions() :void
    {
        $request = $this->newRequest( 'GET' )
                        ->withHeader( 'Access-Control-Request-Method' , 'POST' ) ;

        $this->assertFalse( isCorsPreflight( $request ) ) ;
    }

    public function testIsCorsPreflightFalseWhenOptionsWithoutRequestMethodHeader() :void
    {
        // A bare OPTIONS (server-info probe, route discovery) is NOT a preflight.
        $request = $this->newRequest( 'OPTIONS' ) ;

        $this->assertFalse( isCorsPreflight( $request ) ) ;
    }

    public function testIsCorsPreflightTrueWhenOptionsWithRequestMethodHeader() :void
    {
        $request = $this->newRequest( 'OPTIONS' )
                        ->withHeader( 'Access-Control-Request-Method' , 'POST' ) ;

        $this->assertTrue( isCorsPreflight( $request ) ) ;
    }
}
