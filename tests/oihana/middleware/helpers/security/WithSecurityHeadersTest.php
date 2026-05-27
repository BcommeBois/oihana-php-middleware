<?php

namespace tests\oihana\middleware\helpers\security ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ResponseInterface ;
use Slim\Psr7\Factory\ResponseFactory ;

use oihana\middleware\enums\CrossOriginEmbedderPolicy ;
use oihana\middleware\enums\CrossOriginOpenerPolicy ;
use oihana\middleware\enums\CrossOriginResourcePolicy ;
use oihana\middleware\enums\CspDirective ;
use oihana\middleware\enums\FrameOptions ;
use oihana\middleware\enums\PermissionsPolicyFeature ;
use oihana\middleware\enums\ReferrerPolicy ;
use oihana\middleware\enums\SecurityHeadersOption ;

use function oihana\middleware\helpers\security\withSecurityHeaders ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\security\withSecurityHeaders()}.
 */
class WithSecurityHeadersTest extends TestCase
{
    private function newResponse() :ResponseInterface
    {
        return ( new ResponseFactory() )->createResponse() ;
    }

    public function testEmptyOptionsAddsNoHeader() :void
    {
        $response = withSecurityHeaders( $this->newResponse() , [] ) ;

        $this->assertSame( [] , $response->getHeaders() ) ;
    }

    public function testHstsWithDefaultIncludeSubdomains() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::HSTS => 31536000 ,
        ]) ;

        $this->assertSame
        (
            'max-age=31536000; includeSubDomains' ,
            $response->getHeaderLine( 'Strict-Transport-Security' ) ,
        ) ;
    }

    public function testHstsWithoutIncludeSubdomains() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::HSTS                   => 600 ,
            SecurityHeadersOption::HSTS_INCLUDE_SUBDOMAINS => false ,
        ]) ;

        $this->assertSame
        (
            'max-age=600' ,
            $response->getHeaderLine( 'Strict-Transport-Security' ) ,
        ) ;
    }

    public function testHstsWithPreload() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::HSTS         => 31536000 ,
            SecurityHeadersOption::HSTS_PRELOAD => true ,
        ]) ;

        $this->assertSame
        (
            'max-age=31536000; includeSubDomains; preload' ,
            $response->getHeaderLine( 'Strict-Transport-Security' ) ,
        ) ;
    }

    public function testHstsZeroOrNullProducesNoHeader() :void
    {
        $r1 = withSecurityHeaders( $this->newResponse() , [ SecurityHeadersOption::HSTS => 0    ] ) ;
        $r2 = withSecurityHeaders( $this->newResponse() , [ SecurityHeadersOption::HSTS => null ] ) ;

        $this->assertFalse( $r1->hasHeader( 'Strict-Transport-Security' ) ) ;
        $this->assertFalse( $r2->hasHeader( 'Strict-Transport-Security' ) ) ;
    }

    public function testFrameOptionsDeny() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::FRAME_OPTIONS => FrameOptions::DENY ,
        ]) ;

        $this->assertSame( 'DENY' , $response->getHeaderLine( 'X-Frame-Options' ) ) ;
    }

    public function testFrameOptionsSameOrigin() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::FRAME_OPTIONS => FrameOptions::SAME_ORIGIN ,
        ]) ;

        $this->assertSame( 'SAMEORIGIN' , $response->getHeaderLine( 'X-Frame-Options' ) ) ;
    }

    public function testFrameOptionsEmptyStringProducesNoHeader() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::FRAME_OPTIONS => '' ,
        ]) ;

        $this->assertFalse( $response->hasHeader( 'X-Frame-Options' ) ) ;
    }

    public function testContentTypeNosniffEmitsHeader() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::CONTENT_TYPE_NOSNIFF => true ,
        ]) ;

        $this->assertSame( 'nosniff' , $response->getHeaderLine( 'X-Content-Type-Options' ) ) ;
    }

    public function testContentTypeNosniffFalseProducesNoHeader() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::CONTENT_TYPE_NOSNIFF => false ,
        ]) ;

        $this->assertFalse( $response->hasHeader( 'X-Content-Type-Options' ) ) ;
    }

    public function testReferrerPolicy() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::REFERRER_POLICY => ReferrerPolicy::STRICT_ORIGIN_WHEN_CROSS_ORIGIN ,
        ]) ;

        $this->assertSame
        (
            'strict-origin-when-cross-origin' ,
            $response->getHeaderLine( 'Referrer-Policy' ) ,
        ) ;
    }

    public function testCspFromString() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::CSP => "default-src 'self'" ,
        ]) ;

        $this->assertSame
        (
            "default-src 'self'" ,
            $response->getHeaderLine( 'Content-Security-Policy' ) ,
        ) ;
    }

    public function testCspFromArrayComposesViaBuildCspHeader() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::CSP =>
            [
                CspDirective::DEFAULT_SRC => "'self'" ,
                CspDirective::IMG_SRC     => [ "'self'" , 'data:' ] ,
            ] ,
        ]) ;

        $this->assertSame
        (
            "default-src 'self'; img-src 'self' data:" ,
            $response->getHeaderLine( 'Content-Security-Policy' ) ,
        ) ;
    }

    public function testCspEmptyArrayProducesNoHeader() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::CSP => [] ,
        ]) ;

        $this->assertFalse( $response->hasHeader( 'Content-Security-Policy' ) ) ;
    }

    public function testCspReportOnlySwitchesHeaderName() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::CSP             => "default-src 'self'" ,
            SecurityHeadersOption::CSP_REPORT_ONLY => true ,
        ]) ;

        $this->assertFalse( $response->hasHeader( 'Content-Security-Policy' ) ) ;
        $this->assertSame
        (
            "default-src 'self'" ,
            $response->getHeaderLine( 'Content-Security-Policy-Report-Only' ) ,
        ) ;
    }

    public function testCombinedOptions() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::HSTS                 => 31536000 ,
            SecurityHeadersOption::FRAME_OPTIONS        => FrameOptions::DENY ,
            SecurityHeadersOption::CONTENT_TYPE_NOSNIFF => true ,
            SecurityHeadersOption::REFERRER_POLICY      => ReferrerPolicy::SAME_ORIGIN ,
            SecurityHeadersOption::CSP                  => [ CspDirective::DEFAULT_SRC => "'self'" ] ,
        ]) ;

        $this->assertSame( 'max-age=31536000; includeSubDomains' , $response->getHeaderLine( 'Strict-Transport-Security' ) ) ;
        $this->assertSame( 'DENY'                                , $response->getHeaderLine( 'X-Frame-Options'           ) ) ;
        $this->assertSame( 'nosniff'                             , $response->getHeaderLine( 'X-Content-Type-Options'    ) ) ;
        $this->assertSame( 'same-origin'                         , $response->getHeaderLine( 'Referrer-Policy'           ) ) ;
        $this->assertSame( "default-src 'self'"                  , $response->getHeaderLine( 'Content-Security-Policy'   ) ) ;
    }

    public function testInputResponseIsNotMutated() :void
    {
        $original = $this->newResponse() ;

        withSecurityHeaders( $original ,
        [
            SecurityHeadersOption::HSTS                 => 31536000 ,
            SecurityHeadersOption::CONTENT_TYPE_NOSNIFF => true ,
        ]) ;

        $this->assertFalse( $original->hasHeader( 'Strict-Transport-Security' ) ) ;
        $this->assertFalse( $original->hasHeader( 'X-Content-Type-Options'    ) ) ;
    }

    public function testReturnsNewResponseInstance() :void
    {
        $original = $this->newResponse() ;
        $augmented = withSecurityHeaders( $original ,
        [
            SecurityHeadersOption::CONTENT_TYPE_NOSNIFF => true ,
        ]) ;

        $this->assertNotSame( $original , $augmented ) ;
    }

    public function testPreservesPreExistingUnrelatedHeaders() :void
    {
        $response = $this->newResponse()->withHeader( 'X-Custom' , 'foo' ) ;
        $augmented = withSecurityHeaders( $response ,
        [
            SecurityHeadersOption::CONTENT_TYPE_NOSNIFF => true ,
        ]) ;

        $this->assertSame( 'foo'     , $augmented->getHeaderLine( 'X-Custom'              ) ) ;
        $this->assertSame( 'nosniff' , $augmented->getHeaderLine( 'X-Content-Type-Options' ) ) ;
    }

    public function testCoop() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::COOP => CrossOriginOpenerPolicy::SAME_ORIGIN ,
        ]) ;

        $this->assertSame( 'same-origin' , $response->getHeaderLine( 'Cross-Origin-Opener-Policy' ) ) ;
    }

    public function testCoopEmptyStringProducesNoHeader() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::COOP => '' ,
        ]) ;

        $this->assertFalse( $response->hasHeader( 'Cross-Origin-Opener-Policy' ) ) ;
    }

    public function testCoep() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::COEP => CrossOriginEmbedderPolicy::REQUIRE_CORP ,
        ]) ;

        $this->assertSame( 'require-corp' , $response->getHeaderLine( 'Cross-Origin-Embedder-Policy' ) ) ;
    }

    public function testCorp() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::CORP => CrossOriginResourcePolicy::SAME_SITE ,
        ]) ;

        $this->assertSame( 'same-site' , $response->getHeaderLine( 'Cross-Origin-Resource-Policy' ) ) ;
    }

    public function testCrossOriginIsolationTriad() :void
    {
        // The classic 3-headers combo that unlocks SharedArrayBuffer.
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::COOP => CrossOriginOpenerPolicy::SAME_ORIGIN ,
            SecurityHeadersOption::COEP => CrossOriginEmbedderPolicy::REQUIRE_CORP ,
            SecurityHeadersOption::CORP => CrossOriginResourcePolicy::SAME_ORIGIN ,
        ]) ;

        $this->assertSame( 'same-origin'   , $response->getHeaderLine( 'Cross-Origin-Opener-Policy'   ) ) ;
        $this->assertSame( 'require-corp'  , $response->getHeaderLine( 'Cross-Origin-Embedder-Policy' ) ) ;
        $this->assertSame( 'same-origin'   , $response->getHeaderLine( 'Cross-Origin-Resource-Policy' ) ) ;
    }

    public function testPermissionsPolicyFromArrayComposesViaHelper() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::PERMISSIONS_POLICY =>
            [
                PermissionsPolicyFeature::GEOLOCATION => false ,
                PermissionsPolicyFeature::CAMERA      => 'self' ,
            ] ,
        ]) ;

        $this->assertSame
        (
            'geolocation=(), camera=(self)' ,
            $response->getHeaderLine( 'Permissions-Policy' ) ,
        ) ;
    }

    public function testPermissionsPolicyFromString() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::PERMISSIONS_POLICY => 'geolocation=(), camera=*' ,
        ]) ;

        $this->assertSame( 'geolocation=(), camera=*' , $response->getHeaderLine( 'Permissions-Policy' ) ) ;
    }

    public function testPermissionsPolicyEmptyArrayProducesNoHeader() :void
    {
        $response = withSecurityHeaders( $this->newResponse() ,
        [
            SecurityHeadersOption::PERMISSIONS_POLICY => [] ,
        ]) ;

        $this->assertFalse( $response->hasHeader( 'Permissions-Policy' ) ) ;
    }
}
