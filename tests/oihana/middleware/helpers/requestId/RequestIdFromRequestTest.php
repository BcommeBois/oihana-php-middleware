<?php

namespace tests\oihana\middleware\helpers\requestId ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ServerRequestInterface ;
use Slim\Psr7\Factory\ServerRequestFactory ;

use oihana\middleware\enums\RequestIdField ;

use function oihana\middleware\helpers\requestId\requestIdFromRequest ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\requestId\requestIdFromRequest()}.
 */
class RequestIdFromRequestTest extends TestCase
{
    private function newRequest( ?string $headerValue = null , string $headerName = 'X-Request-Id' ) :ServerRequestInterface
    {
        $request = ( new ServerRequestFactory() )->createServerRequest( 'GET' , '/api/anything' ) ;

        if ( $headerValue !== null )
        {
            $request = $request->withHeader( $headerName , $headerValue ) ;
        }

        return $request ;
    }

    public function testNoHeaderGeneratesABase64UrlId() :void
    {
        $id = requestIdFromRequest( $this->newRequest() ) ;

        // randomBase64Url(16) ⇒ 22 chars, [A-Za-z0-9_-].
        $this->assertMatchesRegularExpression( '/^[A-Za-z0-9_\-]{22}$/' , $id ) ;
    }

    public function testEmptyHeaderGeneratesANewId() :void
    {
        $id = requestIdFromRequest( $this->newRequest( '' ) ) ;

        $this->assertMatchesRegularExpression( '/^[A-Za-z0-9_\-]{22}$/' , $id ) ;
    }

    public function testValidIncomingHeaderIsPropagated() :void
    {
        $forwarded = 'a1B2c3D4e5F6g7H8i9J0kL' ;

        $id = requestIdFromRequest( $this->newRequest( $forwarded ) ) ;

        $this->assertSame( $forwarded , $id ) ;
    }

    public function testIncomingHeaderWithCustomNameIsPropagated() :void
    {
        $forwarded = 'custom-trace-42_AbC' ;
        $request   = $this->newRequest( $forwarded , 'X-Trace-Id' ) ;

        $id = requestIdFromRequest( $request , 'X-Trace-Id' ) ;

        $this->assertSame( $forwarded , $id ) ;
    }

    public function testIncomingHeaderUsingTheEnumConstantIsPropagated() :void
    {
        $forwarded = 'enum-key-test_42' ;
        $request   = $this->newRequest( $forwarded , RequestIdField::HEADER_NAME ) ;

        $id = requestIdFromRequest( $request , RequestIdField::HEADER_NAME ) ;

        $this->assertSame( $forwarded , $id ) ;
    }

    public function testIncomingHeaderWithCharactersOutsideUrlSafeAlphabetIsReplaced() :void
    {
        // Characters legal in HTTP headers (RFC 7230 visible-ASCII) but
        // outside the conservative [A-Za-z0-9_-] alphabet enforced by the
        // helper, on purpose: defense-in-depth against polluting downstream
        // logs / trace pipelines with whatever a client decides to forge.
        // (CRLF injection is caught upstream by the PSR-7 implementation
        // itself — Slim PSR-7 refuses to insert such headers — so the helper
        // never sees them in practice.)
        $id = requestIdFromRequest( $this->newRequest( 'foo;bar=baz' ) ) ;

        $this->assertNotSame( 'foo;bar=baz' , $id ) ;
        $this->assertMatchesRegularExpression( '/^[A-Za-z0-9_\-]{22}$/' , $id ) ;
    }

    public function testIncomingHeaderWithSpacesIsReplaced() :void
    {
        $id = requestIdFromRequest( $this->newRequest( 'has spaces inside' ) ) ;

        $this->assertNotSame( 'has spaces inside' , $id ) ;
    }

    public function testIncomingHeaderTooLongIsReplaced() :void
    {
        $tooLong = str_repeat( 'a' , 129 ) ; // 1 over the 128-char cap

        $id = requestIdFromRequest( $this->newRequest( $tooLong ) ) ;

        $this->assertNotSame( $tooLong , $id ) ;
    }

    public function testIncomingHeaderAtMaxLengthIsAccepted() :void
    {
        $maxLength = str_repeat( 'a' , 128 ) ;

        $id = requestIdFromRequest( $this->newRequest( $maxLength ) ) ;

        $this->assertSame( $maxLength , $id ) ;
    }

    public function testIncomingHeaderSingleCharIsAccepted() :void
    {
        $id = requestIdFromRequest( $this->newRequest( 'x' ) ) ;

        $this->assertSame( 'x' , $id ) ;
    }
}
