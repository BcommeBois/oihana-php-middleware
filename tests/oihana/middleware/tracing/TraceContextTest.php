<?php

namespace tests\oihana\middleware\tracing ;

use PHPUnit\Framework\TestCase ;

use oihana\middleware\tracing\TraceContext ;

/**
 * Unit coverage for {@see TraceContext}.
 */
class TraceContextTest extends TestCase
{
    public function testToTraceparentBuildsTheCanonicalShapeWhenSampled() :void
    {
        $context = new TraceContext
        (
            traceId      : '4bf92f3577b34da6a3ce929d0e0e4736' ,
            spanId       : 'a1b2c3d4e5f60718' ,
            parentSpanId : '00f067aa0ba902b7' ,
            sampled      : true ,
        ) ;

        $this->assertSame
        (
            '00-4bf92f3577b34da6a3ce929d0e0e4736-a1b2c3d4e5f60718-01' ,
            $context->toTraceparent() ,
        ) ;
    }

    public function testToTraceparentEmitsZeroFlagsWhenNotSampled() :void
    {
        $context = new TraceContext
        (
            traceId      : '4bf92f3577b34da6a3ce929d0e0e4736' ,
            spanId       : 'a1b2c3d4e5f60718' ,
            parentSpanId : null ,
            sampled      : false ,
        ) ;

        $this->assertStringEndsWith( '-00' , $context->toTraceparent() ) ;
    }

    public function testToTraceparentAlwaysFiftyFiveChars() :void
    {
        $context = new TraceContext
        (
            traceId      : '4bf92f3577b34da6a3ce929d0e0e4736' ,
            spanId       : 'a1b2c3d4e5f60718' ,
            parentSpanId : null ,
            sampled      : true ,
        ) ;

        $this->assertSame( 55 , strlen( $context->toTraceparent() ) ) ;
    }
}
