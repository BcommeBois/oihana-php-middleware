<?php

namespace tests\oihana\middleware\helpers\observability ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ResponseInterface ;
use Slim\Psr7\Factory\ResponseFactory ;

use oihana\middleware\enums\ResponseTimeOption ;

use function oihana\middleware\helpers\observability\withResponseTime ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\observability\withResponseTime()}.
 */
class WithResponseTimeTest extends TestCase
{
    private function newResponse() :ResponseInterface
    {
        return ( new ResponseFactory() )->createResponse() ;
    }

    public function testEmitsLegacyXResponseTimeHeaderByDefault() :void
    {
        $response = withResponseTime( $this->newResponse() , microtime( true ) ) ;

        $this->assertMatchesRegularExpression
        (
            '/^\d+\.\d{2}ms$/' ,
            $response->getHeaderLine( 'X-Response-Time' ) ,
        ) ;
        $this->assertFalse( $response->hasHeader( 'Server-Timing' ) ) ;
    }

    public function testHonoursPrecisionOption() :void
    {
        $response = withResponseTime
        (
            $this->newResponse() ,
            microtime( true ) - 0.1234567 ,
            [ ResponseTimeOption::PRECISION => 4 ] ,
        ) ;

        $this->assertMatchesRegularExpression
        (
            '/^\d+\.\d{4}ms$/' ,
            $response->getHeaderLine( 'X-Response-Time' ) ,
        ) ;
    }

    public function testZeroPrecisionEmitsIntegerOnly() :void
    {
        $response = withResponseTime
        (
            $this->newResponse() ,
            microtime( true ) - 0.5 ,
            [ ResponseTimeOption::PRECISION => 0 ] ,
        ) ;

        $this->assertMatchesRegularExpression
        (
            '/^\d+ms$/' ,
            $response->getHeaderLine( 'X-Response-Time' ) ,
        ) ;
    }

    public function testNegativePrecisionFallsBackToDefault() :void
    {
        $response = withResponseTime
        (
            $this->newResponse() ,
            microtime( true ) ,
            [ ResponseTimeOption::PRECISION => -3 ] ,
        ) ;

        // Defaults to 2 decimals.
        $this->assertMatchesRegularExpression
        (
            '/^\d+\.\d{2}ms$/' ,
            $response->getHeaderLine( 'X-Response-Time' ) ,
        ) ;
    }

    public function testFutureStartTimeIsClampedToZero() :void
    {
        // $startMicrotime in the future ⇒ duration negative ⇒ clamped to 0.00.
        $response = withResponseTime( $this->newResponse() , microtime( true ) + 60 ) ;

        $this->assertSame( '0.00ms' , $response->getHeaderLine( 'X-Response-Time' ) ) ;
    }

    public function testServerTimingOptInEmitsW3cFormat() :void
    {
        $response = withResponseTime
        (
            $this->newResponse() ,
            microtime( true ) ,
            [ ResponseTimeOption::USE_SERVER_TIMING => true ] ,
        ) ;

        $this->assertMatchesRegularExpression
        (
            '/^total;dur=\d+\.\d{2}$/' ,
            $response->getHeaderLine( 'Server-Timing' ) ,
        ) ;
        $this->assertFalse( $response->hasHeader( 'X-Response-Time' ) ) ;
    }

    public function testServerTimingMetricNameIsConfigurable() :void
    {
        $response = withResponseTime
        (
            $this->newResponse() ,
            microtime( true ) ,
            [
                ResponseTimeOption::USE_SERVER_TIMING    => true ,
                ResponseTimeOption::SERVER_TIMING_METRIC => 'app' ,
            ] ,
        ) ;

        $this->assertStringStartsWith( 'app;dur=' , $response->getHeaderLine( 'Server-Timing' ) ) ;
    }

    public function testServerTimingEmptyMetricNameFallsBackToTotal() :void
    {
        $response = withResponseTime
        (
            $this->newResponse() ,
            microtime( true ) ,
            [
                ResponseTimeOption::USE_SERVER_TIMING    => true ,
                ResponseTimeOption::SERVER_TIMING_METRIC => '' ,
            ] ,
        ) ;

        $this->assertStringStartsWith( 'total;dur=' , $response->getHeaderLine( 'Server-Timing' ) ) ;
    }

    public function testInputResponseIsNotMutated() :void
    {
        $original  = $this->newResponse() ;
        $augmented = withResponseTime( $original , microtime( true ) ) ;

        $this->assertFalse( $original->hasHeader( 'X-Response-Time' ) ) ;
        $this->assertTrue ( $augmented->hasHeader( 'X-Response-Time' ) ) ;
        $this->assertNotSame( $original , $augmented ) ;
    }

    public function testPreservesPreExistingUnrelatedHeaders() :void
    {
        $response  = $this->newResponse()->withHeader( 'X-Request-Id' , 'abc123' ) ;
        $augmented = withResponseTime( $response , microtime( true ) ) ;

        $this->assertSame( 'abc123' , $augmented->getHeaderLine( 'X-Request-Id' ) ) ;
        $this->assertTrue ( $augmented->hasHeader( 'X-Response-Time' ) ) ;
    }
}
