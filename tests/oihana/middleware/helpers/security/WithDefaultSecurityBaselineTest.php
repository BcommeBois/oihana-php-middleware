<?php

namespace tests\oihana\middleware\helpers\security ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ResponseInterface ;
use Slim\Psr7\Factory\ResponseFactory ;

use oihana\middleware\enums\CrossOriginEmbedderPolicy ;
use oihana\middleware\enums\FrameOptions ;
use oihana\middleware\enums\SecurityHeadersOption ;

use function oihana\middleware\helpers\security\withDefaultSecurityBaseline ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\security\withDefaultSecurityBaseline()}.
 */
class WithDefaultSecurityBaselineTest extends TestCase
{
    private function newResponse() :ResponseInterface
    {
        return ( new ResponseFactory() )->createResponse() ;
    }

    public function testBaselineEmitsTheExpectedSetOfHeaders() :void
    {
        $response = withDefaultSecurityBaseline( $this->newResponse() ) ;

        $this->assertSame
        (
            'max-age=31536000; includeSubDomains' ,
            $response->getHeaderLine( 'Strict-Transport-Security' ) ,
        ) ;
        $this->assertSame( 'DENY'                            , $response->getHeaderLine( 'X-Frame-Options'              ) ) ;
        $this->assertSame( 'nosniff'                         , $response->getHeaderLine( 'X-Content-Type-Options'       ) ) ;
        $this->assertSame( 'strict-origin-when-cross-origin' , $response->getHeaderLine( 'Referrer-Policy'              ) ) ;
        $this->assertSame( 'same-origin'                     , $response->getHeaderLine( 'Cross-Origin-Opener-Policy'   ) ) ;
        $this->assertSame( 'same-origin'                     , $response->getHeaderLine( 'Cross-Origin-Resource-Policy' ) ) ;
    }

    public function testBaselineDoesNotEmitCspByDefault() :void
    {
        $response = withDefaultSecurityBaseline( $this->newResponse() ) ;

        $this->assertFalse( $response->hasHeader( 'Content-Security-Policy'        ) ) ;
        $this->assertFalse( $response->hasHeader( 'Permissions-Policy'             ) ) ;
        $this->assertFalse( $response->hasHeader( 'Cross-Origin-Embedder-Policy'   ) ) ;
    }

    public function testOverrideTunesAnIndividualBaselineValue() :void
    {
        $response = withDefaultSecurityBaseline
        (
            $this->newResponse() ,
            [ SecurityHeadersOption::HSTS => 300 ] ,
        ) ;

        $this->assertSame
        (
            'max-age=300; includeSubDomains' ,
            $response->getHeaderLine( 'Strict-Transport-Security' ) ,
        ) ;
        // Other baseline values remain intact.
        $this->assertSame( 'DENY' , $response->getHeaderLine( 'X-Frame-Options' ) ) ;
    }

    public function testOverrideAddsAnExtraNonBaselineOption() :void
    {
        $response = withDefaultSecurityBaseline
        (
            $this->newResponse() ,
            [
                SecurityHeadersOption::COEP => CrossOriginEmbedderPolicy::REQUIRE_CORP ,
                SecurityHeadersOption::CSP  => [ 'default-src' => "'self'" ] ,
            ] ,
        ) ;

        $this->assertSame( 'require-corp'     , $response->getHeaderLine( 'Cross-Origin-Embedder-Policy' ) ) ;
        $this->assertSame( "default-src 'self'" , $response->getHeaderLine( 'Content-Security-Policy'    ) ) ;
        // Baseline still applied.
        $this->assertSame( 'same-origin' , $response->getHeaderLine( 'Cross-Origin-Opener-Policy' ) ) ;
    }

    public function testFrameOptionsOverrideReplacesTheBaselineValue() :void
    {
        $response = withDefaultSecurityBaseline
        (
            $this->newResponse() ,
            [ SecurityHeadersOption::FRAME_OPTIONS => FrameOptions::SAME_ORIGIN ] ,
        ) ;

        $this->assertSame( 'SAMEORIGIN' , $response->getHeaderLine( 'X-Frame-Options' ) ) ;
    }

    public function testInputResponseIsNotMutated() :void
    {
        $original  = $this->newResponse() ;
        $augmented = withDefaultSecurityBaseline( $original ) ;

        $this->assertFalse( $original->hasHeader( 'X-Frame-Options' ) ) ;
        $this->assertTrue ( $augmented->hasHeader( 'X-Frame-Options' ) ) ;
        $this->assertNotSame( $original , $augmented ) ;
    }
}
