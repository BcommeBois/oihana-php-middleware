<?php

namespace tests\oihana\middleware\helpers\rateLimit ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ResponseInterface ;
use Slim\Psr7\Factory\ResponseFactory ;

use oihana\middleware\rateLimit\RateLimitDecision ;

use function oihana\middleware\helpers\rateLimit\withRateLimitHeaders ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\rateLimit\withRateLimitHeaders()}.
 */
class WithRateLimitHeadersTest extends TestCase
{
    private function newResponse() :ResponseInterface
    {
        return ( new ResponseFactory() )->createResponse() ;
    }

    private function allowed() :RateLimitDecision
    {
        return new RateLimitDecision
        (
            allowed    : true   ,
            limit      : 100    ,
            remaining  : 42     ,
            reset      : 1_700_000_000 ,
            retryAfter : 0      ,
        ) ;
    }

    private function blocked() :RateLimitDecision
    {
        return new RateLimitDecision
        (
            allowed    : false  ,
            limit      : 100    ,
            remaining  : 0      ,
            reset      : 1_700_000_000 ,
            retryAfter : 17     ,
        ) ;
    }

    public function testAllowedDecisionEmitsLegacyHeadersByDefault() :void
    {
        $response = withRateLimitHeaders( $this->newResponse() , $this->allowed() ) ;

        $this->assertSame( '100'           , $response->getHeaderLine( 'X-RateLimit-Limit'     ) ) ;
        $this->assertSame( '42'            , $response->getHeaderLine( 'X-RateLimit-Remaining' ) ) ;
        $this->assertSame( '1700000000'    , $response->getHeaderLine( 'X-RateLimit-Reset'     ) ) ;
        $this->assertFalse( $response->hasHeader( 'Retry-After' ) ) ;
    }

    public function testBlockedDecisionAddsRetryAfter() :void
    {
        $response = withRateLimitHeaders( $this->newResponse() , $this->blocked() ) ;

        $this->assertSame( '100' , $response->getHeaderLine( 'X-RateLimit-Limit'     ) ) ;
        $this->assertSame( '0'   , $response->getHeaderLine( 'X-RateLimit-Remaining' ) ) ;
        $this->assertSame( '17'  , $response->getHeaderLine( 'Retry-After'           ) ) ;
    }

    public function testRfc9421FlagSwitchesToTheIetfDraftFamily() :void
    {
        $response = withRateLimitHeaders( $this->newResponse() , $this->allowed() , rfc9421 : true ) ;

        $this->assertSame( '100'        , $response->getHeaderLine( 'RateLimit-Limit'     ) ) ;
        $this->assertSame( '42'         , $response->getHeaderLine( 'RateLimit-Remaining' ) ) ;
        $this->assertSame( '1700000000' , $response->getHeaderLine( 'RateLimit-Reset'     ) ) ;
        $this->assertFalse( $response->hasHeader( 'X-RateLimit-Limit' ) ) ;
        $this->assertFalse( $response->hasHeader( 'Retry-After'       ) ) ;
    }

    public function testRfc9421BlockedDecisionStillAddsRetryAfter() :void
    {
        $response = withRateLimitHeaders( $this->newResponse() , $this->blocked() , rfc9421 : true ) ;

        $this->assertSame( '17' , $response->getHeaderLine( 'Retry-After' ) ) ;
    }

    public function testInputResponseIsNotMutated() :void
    {
        $original = $this->newResponse() ;
        $augmented = withRateLimitHeaders( $original , $this->allowed() ) ;

        $this->assertFalse( $original->hasHeader( 'X-RateLimit-Limit' ) ) ;
        $this->assertTrue( $augmented->hasHeader( 'X-RateLimit-Limit' ) ) ;
        $this->assertNotSame( $original , $augmented ) ;
    }

    public function testPreservesPreExistingUnrelatedHeaders() :void
    {
        $base = $this->newResponse()->withHeader( 'X-Request-Id' , 'abc123' ) ;

        $response = withRateLimitHeaders( $base , $this->allowed() ) ;

        $this->assertSame( 'abc123' , $response->getHeaderLine( 'X-Request-Id'      ) ) ;
        $this->assertSame( '100'    , $response->getHeaderLine( 'X-RateLimit-Limit' ) ) ;
    }

    public function testReplacesPreExistingRateLimitHeaders() :void
    {
        $base = $this->newResponse()->withHeader( 'X-RateLimit-Limit' , 'stale' ) ;

        $response = withRateLimitHeaders( $base , $this->allowed() ) ;

        $this->assertSame( '100' , $response->getHeaderLine( 'X-RateLimit-Limit' ) ) ;
    }
}
