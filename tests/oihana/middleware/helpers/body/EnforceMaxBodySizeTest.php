<?php

namespace tests\oihana\middleware\helpers\body ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ServerRequestInterface ;
use Slim\Psr7\Factory\ServerRequestFactory ;

use function oihana\middleware\helpers\body\enforceMaxBodySize ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\body\enforceMaxBodySize()}.
 */
class EnforceMaxBodySizeTest extends TestCase
{
    private function newRequest( ?string $contentLength = null ) :ServerRequestInterface
    {
        $request = ( new ServerRequestFactory() )->createServerRequest( 'POST' , '/upload' ) ;

        return $contentLength !== null
             ? $request->withHeader( 'Content-Length' , $contentLength )
             : $request ;
    }

    public function testReturnsTrueWhenContentLengthMissing() :void
    {
        // Streaming / chunked encoding — can't verify here, let upper layers
        // (web server, body parser) deal with it.
        $this->assertTrue( enforceMaxBodySize( $this->newRequest() , 1024 ) ) ;
    }

    public function testReturnsTrueWhenBelowLimit() :void
    {
        $this->assertTrue( enforceMaxBodySize( $this->newRequest( '500' ) , 1024 ) ) ;
    }

    public function testReturnsTrueWhenExactlyAtLimit() :void
    {
        $this->assertTrue( enforceMaxBodySize( $this->newRequest( '1024' ) , 1024 ) ) ;
    }

    public function testReturnsFalseWhenAboveLimit() :void
    {
        $this->assertFalse( enforceMaxBodySize( $this->newRequest( '1025' ) , 1024 ) ) ;
    }

    public function testZeroContentLengthIsAllowed() :void
    {
        $this->assertTrue( enforceMaxBodySize( $this->newRequest( '0' ) , 1024 ) ) ;
    }

    public function testNegativeContentLengthIsRejected() :void
    {
        // ctype_digit rejects the leading `-` ⇒ strict defensive default.
        $this->assertFalse( enforceMaxBodySize( $this->newRequest( '-1' ) , 1024 ) ) ;
    }

    public function testNonNumericContentLengthIsRejected() :void
    {
        $this->assertFalse( enforceMaxBodySize( $this->newRequest( 'abc' ) , 1024 ) ) ;
    }

    public function testContentLengthWithLeadingPlusIsRejected() :void
    {
        // ctype_digit considers `+5` invalid (RFC 9110 §8.6 grammar is `1*DIGIT`).
        $this->assertFalse( enforceMaxBodySize( $this->newRequest( '+5' ) , 1024 ) ) ;
    }

    public function testContentLengthWithDecimalIsRejected() :void
    {
        $this->assertFalse( enforceMaxBodySize( $this->newRequest( '500.0' ) , 1024 ) ) ;
    }

    public function testOverflowDigitStringStaysRejectedAboveReasonableLimit() :void
    {
        // PHP int cast saturates at PHP_INT_MAX on 64-bit — still > 1024.
        $this->assertFalse( enforceMaxBodySize( $this->newRequest( '999999999999999999999999' ) , 1024 ) ) ;
    }
}
