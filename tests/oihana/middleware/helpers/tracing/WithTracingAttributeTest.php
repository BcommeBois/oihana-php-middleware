<?php

namespace tests\oihana\middleware\helpers\tracing ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ServerRequestInterface ;
use Slim\Psr7\Factory\ServerRequestFactory ;

use oihana\middleware\enums\TracingField ;
use oihana\middleware\tracing\TraceContext ;

use function oihana\middleware\helpers\tracing\withTracingAttribute ;

/**
 * Unit coverage for {@see withTracingAttribute}.
 */
class WithTracingAttributeTest extends TestCase
{
    private function newRequest() :ServerRequestInterface
    {
        return new ServerRequestFactory()->createServerRequest( 'GET' , '/' ) ;
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

    public function testStampsDefaultAttribute() :void
    {
        $request = withTracingAttribute( $this->newRequest() , $this->context() ) ;

        $this->assertSame( $this->context()->traceId , $request->getAttribute( TracingField::ATTRIBUTE_NAME )->traceId ) ;
    }

    public function testStampsCustomAttributeName() :void
    {
        $request = withTracingAttribute( $this->newRequest() , $this->context() , 'tc' ) ;

        $this->assertInstanceOf( TraceContext::class , $request->getAttribute( 'tc' ) ) ;
        $this->assertNull( $request->getAttribute( TracingField::ATTRIBUTE_NAME ) ) ;
    }

    public function testInputRequestIsNotMutated() :void
    {
        $original  = $this->newRequest() ;
        $augmented = withTracingAttribute( $original , $this->context() ) ;

        $this->assertNull( $original->getAttribute( TracingField::ATTRIBUTE_NAME ) ) ;
        $this->assertNotSame( $original , $augmented ) ;
    }
}
