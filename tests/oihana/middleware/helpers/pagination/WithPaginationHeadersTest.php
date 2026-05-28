<?php

namespace tests\oihana\middleware\helpers\pagination ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ResponseInterface ;
use Slim\Psr7\Factory\ResponseFactory ;

use oihana\middleware\pagination\PaginationLinks ;

use function oihana\middleware\helpers\pagination\withPaginationHeaders ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\pagination\withPaginationHeaders()}.
 */
class WithPaginationHeadersTest extends TestCase
{
    private function newResponse() :ResponseInterface
    {
        return ( new ResponseFactory() )->createResponse() ;
    }

    public function testEmptyLinksProducesNoHeaders() :void
    {
        $response = withPaginationHeaders( $this->newResponse() , new PaginationLinks() ) ;

        $this->assertFalse( $response->hasHeader( 'Link' ) ) ;
        $this->assertFalse( $response->hasHeader( 'X-Total-Count' ) ) ;
    }

    public function testSingleNextLinkEmitsOneEntry() :void
    {
        $response = withPaginationHeaders
        (
            $this->newResponse() ,
            new PaginationLinks( next : 'https://api.example.com/users?page=2' ) ,
        ) ;

        $this->assertSame
        (
            '<https://api.example.com/users?page=2>; rel="next"' ,
            $response->getHeaderLine( 'Link' ) ,
        ) ;
    }

    public function testFullSetEmitsAllFourEntriesInFixedOrder() :void
    {
        $response = withPaginationHeaders
        (
            $this->newResponse() ,
            new PaginationLinks
            (
                first : 'https://api.example.com/users?page=1' ,
                prev  : 'https://api.example.com/users?page=2' ,
                next  : 'https://api.example.com/users?page=4' ,
                last  : 'https://api.example.com/users?page=10' ,
            ) ,
        ) ;

        $this->assertSame
        (
            '<https://api.example.com/users?page=1>; rel="first", '
          . '<https://api.example.com/users?page=2>; rel="prev", '
          . '<https://api.example.com/users?page=4>; rel="next", '
          . '<https://api.example.com/users?page=10>; rel="last"' ,
            $response->getHeaderLine( 'Link' ) ,
        ) ;
    }

    public function testTotalCountEmitsXTotalCountHeader() :void
    {
        $response = withPaginationHeaders
        (
            $this->newResponse() ,
            new PaginationLinks( totalCount : 482 ) ,
        ) ;

        $this->assertSame( '482' , $response->getHeaderLine( 'X-Total-Count' ) ) ;
        // No links ⇒ no Link header even though X-Total-Count is set.
        $this->assertFalse( $response->hasHeader( 'Link' ) ) ;
    }

    public function testZeroTotalCountIsEmitted() :void
    {
        // totalCount=0 is meaningful (empty result set), not null — must be emitted.
        $response = withPaginationHeaders
        (
            $this->newResponse() ,
            new PaginationLinks( totalCount : 0 ) ,
        ) ;

        $this->assertSame( '0' , $response->getHeaderLine( 'X-Total-Count' ) ) ;
    }

    public function testInputResponseIsNotMutated() :void
    {
        $original  = $this->newResponse() ;
        $augmented = withPaginationHeaders( $original , new PaginationLinks( next : '/?page=2' ) ) ;

        $this->assertFalse( $original->hasHeader( 'Link' ) ) ;
        $this->assertNotSame( $original , $augmented ) ;
    }

    public function testReplacesPreExistingLinkHeader() :void
    {
        $base = $this->newResponse()->withHeader( 'Link' , '<stale>; rel="next"' ) ;

        $response = withPaginationHeaders( $base , new PaginationLinks( next : '/?page=2' ) ) ;

        $this->assertSame
        (
            '</?page=2>; rel="next"' ,
            $response->getHeaderLine( 'Link' ) ,
        ) ;
    }
}
