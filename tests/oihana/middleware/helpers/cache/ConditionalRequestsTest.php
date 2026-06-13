<?php

namespace tests\oihana\middleware\helpers\cache ;

use DateTimeImmutable ;
use DateTimeZone ;
use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface ;
use Slim\Psr7\Factory\ResponseFactory ;
use Slim\Psr7\Factory\ServerRequestFactory ;

use function oihana\middleware\helpers\cache\isNotModified ;
use function oihana\middleware\helpers\cache\matchIfNoneMatch ;
use function oihana\middleware\helpers\cache\respondNotModified ;
use function oihana\middleware\helpers\cache\stripWeakPrefix ;

/**
 * Unit coverage for the conditional-request helpers in
 * {@see \oihana\middleware\helpers\cache\}.
 */
class ConditionalRequestsTest extends TestCase
{
    private function newRequest() :ServerRequestInterface
    {
        return ( new ServerRequestFactory() )->createServerRequest( 'GET' , '/' ) ;
    }

    private function newResponse() :ResponseInterface
    {
        return ( new ResponseFactory() )->createResponse() ;
    }

    // -------------------------------------------------------------------------
    // isNotModified
    // -------------------------------------------------------------------------

    public function testNoPreconditionHeadersFallsThroughToFalse() :void
    {
        $this->assertFalse( isNotModified( $this->newRequest() , '"v42"' ) ) ;
    }

    public function testIfNoneMatchExactMatchReturnsTrue() :void
    {
        $request = $this->newRequest()->withHeader( 'If-None-Match' , '"v42"' ) ;

        $this->assertTrue( isNotModified( $request , '"v42"' ) ) ;
    }

    public function testIfNoneMatchNoMatchReturnsFalse() :void
    {
        $request = $this->newRequest()->withHeader( 'If-None-Match' , '"v41"' ) ;

        $this->assertFalse( isNotModified( $request , '"v42"' ) ) ;
    }

    public function testIfNoneMatchWildcardMatches() :void
    {
        $request = $this->newRequest()->withHeader( 'If-None-Match' , '*' ) ;

        $this->assertTrue( isNotModified( $request , '"v42"' ) ) ;
    }

    public function testIfNoneMatchMultiValueMatchesWhenAnyEntryMatches() :void
    {
        $request = $this->newRequest()->withHeader( 'If-None-Match' , '"v40", "v41", "v42"' ) ;

        $this->assertTrue( isNotModified( $request , '"v42"' ) ) ;
    }

    public function testIfNoneMatchWeakComparisonStripsWPrefixOnBothSides() :void
    {
        // Weak comparison per RFC 9110 §8.8.3.2.
        $request1 = $this->newRequest()->withHeader( 'If-None-Match' , 'W/"v42"' ) ;
        $request2 = $this->newRequest()->withHeader( 'If-None-Match' , '"v42"' ) ;

        $this->assertTrue( isNotModified( $request1 , '"v42"'   ) ) ;
        $this->assertTrue( isNotModified( $request2 , 'W/"v42"' ) ) ;
        $this->assertTrue( isNotModified( $request1 , 'W/"v42"' ) ) ;
    }

    public function testIfNoneMatchTakesPrecedenceOverIfModifiedSince() :void
    {
        // Per RFC 9110 §13.1.3, If-None-Match wins. Even if the date check
        // would say "modified", a non-matching If-None-Match returns false.
        $oldDate = new DateTimeImmutable( '2020-01-01' , new DateTimeZone( 'UTC' ) ) ;

        $request = $this->newRequest()
            ->withHeader( 'If-None-Match'     , '"v41"' )                            // Does not match $etag.
            ->withHeader( 'If-Modified-Since' , 'Sun, 01 Jan 2030 00:00:00 GMT' ) ;  // Would say "not modified".

        $this->assertFalse( isNotModified( $request , '"v42"' , $oldDate ) ) ;
    }

    public function testIfModifiedSinceReturnsTrueWhenLastModifiedIsEarlier() :void
    {
        $lastModified = new DateTimeImmutable( '2026-05-28 10:00:00' , new DateTimeZone( 'UTC' ) ) ;

        $request = $this->newRequest()->withHeader( 'If-Modified-Since' , 'Thu, 28 May 2026 11:00:00 GMT' ) ;

        $this->assertTrue( isNotModified( $request , '' , $lastModified ) ) ;
    }

    public function testIfModifiedSinceReturnsFalseWhenLastModifiedIsLater() :void
    {
        $lastModified = new DateTimeImmutable( '2026-05-28 12:00:00' , new DateTimeZone( 'UTC' ) ) ;

        $request = $this->newRequest()->withHeader( 'If-Modified-Since' , 'Thu, 28 May 2026 11:00:00 GMT' ) ;

        $this->assertFalse( isNotModified( $request , '' , $lastModified ) ) ;
    }

    public function testMalformedIfModifiedSinceReturnsFalse() :void
    {
        $lastModified = new DateTimeImmutable( '2026-05-28 10:00:00' , new DateTimeZone( 'UTC' ) ) ;

        $request = $this->newRequest()->withHeader( 'If-Modified-Since' , 'not a date' ) ;

        $this->assertFalse( isNotModified( $request , '' , $lastModified ) ) ;
    }

    public function testIfModifiedSinceWithoutLastModifiedReturnsFalse() :void
    {
        // No reference date — can't compare, force regeneration.
        $request = $this->newRequest()->withHeader( 'If-Modified-Since' , 'Thu, 28 May 2026 11:00:00 GMT' ) ;

        $this->assertFalse( isNotModified( $request , '' , null ) ) ;
    }

    public function testLastModifiedSetButNoIfModifiedSinceHeaderReturnsFalse() :void
    {
        // A reference date is known but the client sent no precondition
        // header at all (no If-None-Match, no If-Modified-Since) — nothing to
        // compare against, so the cache can't be validated.
        $lastModified = new DateTimeImmutable( '2026-05-28 10:00:00' , new DateTimeZone( 'UTC' ) ) ;

        $this->assertFalse( isNotModified( $this->newRequest() , '' , $lastModified ) ) ;
    }

    // -------------------------------------------------------------------------
    // matchIfNoneMatch (internal helper, exposed for testability)
    // -------------------------------------------------------------------------

    public function testMatchIfNoneMatchWithEmptyEtagReturnsFalseExceptWildcard() :void
    {
        $this->assertFalse( matchIfNoneMatch( '"v42"' , '' ) ) ;
        // Wildcard matches anything that exists — the helper doesn't re-verify
        // that the etag is non-empty.
        $this->assertTrue ( matchIfNoneMatch( '*'     , '' ) ) ;
    }

    public function testStripWeakPrefixHandlesBothShapes() :void
    {
        $this->assertSame( '"v42"' , stripWeakPrefix( '"v42"' ) ) ;
        $this->assertSame( '"v42"' , stripWeakPrefix( 'W/"v42"' ) ) ;
        $this->assertSame( ''      , stripWeakPrefix( '' ) ) ;
    }

    // -------------------------------------------------------------------------
    // respondNotModified
    // -------------------------------------------------------------------------

    public function testRespondNotModifiedSetsStatusTo304() :void
    {
        $response = respondNotModified( $this->newResponse() , '"v42"' ) ;

        $this->assertSame( 304            , $response->getStatusCode() ) ;
        $this->assertSame( 'Not Modified' , $response->getReasonPhrase() ) ;
    }

    public function testRespondNotModifiedStampsTheEtagHeader() :void
    {
        $response = respondNotModified( $this->newResponse() , '"v42"' ) ;

        $this->assertSame( '"v42"' , $response->getHeaderLine( 'ETag' ) ) ;
    }

    public function testRespondNotModifiedInputResponseIsNotMutated() :void
    {
        $original  = $this->newResponse() ;
        $augmented = respondNotModified( $original , '"v42"' ) ;

        $this->assertSame( 200 , $original->getStatusCode() ) ;
        $this->assertFalse( $original->hasHeader( 'ETag' ) ) ;
        $this->assertNotSame( $original , $augmented ) ;
    }
}
