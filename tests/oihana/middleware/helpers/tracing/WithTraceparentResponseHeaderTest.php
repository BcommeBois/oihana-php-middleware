<?php

namespace tests\oihana\middleware\helpers\tracing ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ResponseInterface ;
use Slim\Psr7\Factory\ResponseFactory ;

use oihana\middleware\tracing\TraceContext ;

use function oihana\middleware\helpers\tracing\withTraceparentResponseHeader ;

/**
 * Unit coverage for {@see withTraceparentResponseHeader}.
 */
class WithTraceparentResponseHeaderTest extends TestCase
{
    private function newResponse() :ResponseInterface
    {
        return new ResponseFactory()->createResponse() ;
    }

    private function context() :TraceContext
    {
        return new TraceContext
        (
            traceId      : '4bf92f3577b34da6a3ce929d0e0e4736' ,
            spanId       : 'a1b2c3d4e5f60718' ,
            parentSpanId : null ,
            sampled      : true ,
        ) ;
    }

    public function testStampsCanonicalTraceparentValue() :void
    {
        $response = withTraceparentResponseHeader( $this->newResponse() , $this->context() ) ;

        $this->assertSame
        (
            '00-4bf92f3577b34da6a3ce929d0e0e4736-a1b2c3d4e5f60718-01' ,
            $response->getHeaderLine( 'traceparent' ) ,
        ) ;
    }

    public function testInputResponseIsNotMutated() :void
    {
        $original  = $this->newResponse() ;
        $augmented = withTraceparentResponseHeader( $original , $this->context() ) ;

        $this->assertFalse( $original->hasHeader( 'traceparent' ) ) ;
        $this->assertNotSame( $original , $augmented ) ;
    }

    public function testReplacesPreExistingTraceparent() :void
    {
        $base = $this->newResponse()->withHeader( 'traceparent' , 'stale' ) ;

        $response = withTraceparentResponseHeader( $base , $this->context() ) ;

        $this->assertSame
        (
            '00-4bf92f3577b34da6a3ce929d0e0e4736-a1b2c3d4e5f60718-01' ,
            $response->getHeaderLine( 'traceparent' ) ,
        ) ;
    }
}
