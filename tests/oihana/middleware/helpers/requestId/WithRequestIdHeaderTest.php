<?php

namespace tests\oihana\middleware\helpers\requestId ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ResponseInterface ;
use Slim\Psr7\Factory\ResponseFactory ;

use oihana\middleware\enums\RequestIdField ;

use function oihana\middleware\helpers\requestId\withRequestIdHeader ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\requestId\withRequestIdHeader()}.
 */
class WithRequestIdHeaderTest extends TestCase
{
    private function newResponse() :ResponseInterface
    {
        return ( new ResponseFactory() )->createResponse() ;
    }

    public function testStampsTheDefaultHeader() :void
    {
        $augmented = withRequestIdHeader( $this->newResponse() , 'abc123' ) ;

        $this->assertSame( 'abc123' , $augmented->getHeaderLine( 'X-Request-Id' ) ) ;
    }

    public function testStampsACustomHeaderName() :void
    {
        $augmented = withRequestIdHeader( $this->newResponse() , 'abc123' , 'X-Trace-Id' ) ;

        $this->assertSame( 'abc123' , $augmented->getHeaderLine( 'X-Trace-Id' ) ) ;
        $this->assertFalse( $augmented->hasHeader( 'X-Request-Id' ) ) ;
    }

    public function testWorksWithTheEnumConstant() :void
    {
        $augmented = withRequestIdHeader( $this->newResponse() , 'abc123' , RequestIdField::HEADER_NAME ) ;

        $this->assertSame( 'abc123' , $augmented->getHeaderLine( 'X-Request-Id' ) ) ;
    }

    public function testReturnsANewInstance() :void
    {
        $original  = $this->newResponse() ;
        $augmented = withRequestIdHeader( $original , 'abc123' ) ;

        $this->assertNotSame( $original , $augmented ) ;
    }

    public function testInputResponseIsNotMutated() :void
    {
        $original = $this->newResponse() ;

        withRequestIdHeader( $original , 'abc123' ) ;

        $this->assertFalse( $original->hasHeader( 'X-Request-Id' ) ) ;
    }

    public function testReplacesAnExistingHeaderValue() :void
    {
        $response = $this->newResponse()->withHeader( 'X-Request-Id' , 'old' ) ;

        $augmented = withRequestIdHeader( $response , 'new' ) ;

        $this->assertSame( 'new' , $augmented->getHeaderLine( 'X-Request-Id' ) ) ;
    }

    public function testPreservesPreExistingUnrelatedHeaders() :void
    {
        $response  = $this->newResponse()->withHeader( 'X-Custom' , 'foo' ) ;
        $augmented = withRequestIdHeader( $response , 'abc123' ) ;

        $this->assertSame( 'foo'    , $augmented->getHeaderLine( 'X-Custom'      ) ) ;
        $this->assertSame( 'abc123' , $augmented->getHeaderLine( 'X-Request-Id' ) ) ;
    }
}
