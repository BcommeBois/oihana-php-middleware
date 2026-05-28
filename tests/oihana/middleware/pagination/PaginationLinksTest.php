<?php

namespace tests\oihana\middleware\pagination ;

use PHPUnit\Framework\TestCase ;

use oihana\middleware\pagination\PaginationLinks ;

/**
 * Unit coverage for {@see \oihana\middleware\pagination\PaginationLinks}.
 */
class PaginationLinksTest extends TestCase
{
    public function testEmptyInstanceHasAllNulls() :void
    {
        $links = new PaginationLinks() ;

        $this->assertNull( $links->first ) ;
        $this->assertNull( $links->prev ) ;
        $this->assertNull( $links->next ) ;
        $this->assertNull( $links->last ) ;
        $this->assertNull( $links->totalCount ) ;
    }

    public function testFullyPopulatedInstance() :void
    {
        $links = new PaginationLinks
        (
            first      : 'https://api.example.com/users?page=1' ,
            prev       : 'https://api.example.com/users?page=2' ,
            next       : 'https://api.example.com/users?page=4' ,
            last       : 'https://api.example.com/users?page=10' ,
            totalCount : 482 ,
        ) ;

        $this->assertSame( 'https://api.example.com/users?page=1'  , $links->first ) ;
        $this->assertSame( 'https://api.example.com/users?page=10' , $links->last ) ;
        $this->assertSame( 482 , $links->totalCount ) ;
    }

    public function testPartialInstanceFirstPage() :void
    {
        // Typical first-page state : no prev, no first.
        $links = new PaginationLinks
        (
            next       : 'https://api.example.com/users?page=2' ,
            last       : 'https://api.example.com/users?page=10' ,
            totalCount : 482 ,
        ) ;

        $this->assertNull( $links->first ) ;
        $this->assertNull( $links->prev ) ;
        $this->assertSame( 'https://api.example.com/users?page=2'  , $links->next ) ;
    }
}
