<?php

namespace tests\oihana\middleware\helpers\tracing ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ServerRequestInterface ;
use Random\RandomException;
use Slim\Psr7\Factory\ServerRequestFactory ;

use function oihana\middleware\helpers\tracing\traceContextFromRequest ;

/**
 * Unit coverage for {@see traceContextFromRequest}.
 */
class TraceContextFromRequestTest extends TestCase
{
    private function newRequest() :ServerRequestInterface
    {
        return new ServerRequestFactory()->createServerRequest( 'GET' , '/' ) ;
    }

    /**
     * @return void
     * @throws RandomException
     */
    public function testInheritsValidIncomingTraceparent() :void
    {
        $request = $this->newRequest()->withHeader
        (
            'traceparent' ,
            '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01' ,
        ) ;

        $context = traceContextFromRequest( $request ) ;

        $this->assertSame( '4bf92f3577b34da6a3ce929d0e0e4736' , $context->traceId ) ;
        $this->assertSame( '00f067aa0ba902b7'                 , $context->parentSpanId ) ;
        $this->assertTrue( $context->sampled ) ;
        $this->assertMatchesRegularExpression( '/^[0-9a-f]{16}$/' , $context->spanId ) ;
        // Fresh span id, NOT inherited from the incoming parent.
        $this->assertNotSame( '00f067aa0ba902b7' , $context->spanId ) ;
    }

    /**
     * @return void
     * @throws RandomException
     */
    public function testGeneratesFreshContextWhenNoTraceparentHeader() :void
    {
        $context = traceContextFromRequest( $this->newRequest() ) ;

        $this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/' , $context->traceId ) ;
        $this->assertMatchesRegularExpression( '/^[0-9a-f]{16}$/' , $context->spanId ) ;
        $this->assertNull ( $context->parentSpanId ) ;
        $this->assertTrue ( $context->sampled ) ;     // Default for fresh contexts.
        $this->assertNull ( $context->tracestate ) ;
    }

    /**
     * @return void
     * @throws RandomException
     */
    public function testGeneratesFreshContextOnInvalidIncomingTraceparent() :void
    {
        $request = $this->newRequest()->withHeader( 'traceparent' , 'garbage' ) ;

        $context = traceContextFromRequest( $request ) ;

        // Silent regen — no exception, no inherited fields.
        $this->assertNull( $context->parentSpanId ) ;
        $this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/' , $context->traceId ) ;
    }

    /**
     * @return void
     * @throws RandomException
     */
    public function testPropagatesTracestateVerbatim() :void
    {
        $request = $this->newRequest()
            ->withHeader( 'traceparent' , '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01' )
            ->withHeader( 'tracestate'  , 'vendor=key:value,other=42' ) ;

        $context = traceContextFromRequest( $request ) ;

        $this->assertSame( 'vendor=key:value,other=42' , $context->tracestate ) ;
    }

    /**
     * @return void
     * @throws RandomException
     */
    public function testEmptyTracestateBecomesNull() :void
    {
        $request = $this->newRequest()
            ->withHeader( 'traceparent' , '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01' )
            ->withHeader( 'tracestate'  , '' ) ;

        $context = traceContextFromRequest( $request ) ;

        $this->assertNull( $context->tracestate ) ;
    }

    /**
     * @return void
     * @throws RandomException
     */
    public function testInheritsNotSampledFlag() :void
    {
        $request = $this->newRequest()->withHeader
        (
            'traceparent' ,
            '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-00' ,
        ) ;

        $context = traceContextFromRequest( $request ) ;

        $this->assertFalse( $context->sampled ) ;
    }
}
