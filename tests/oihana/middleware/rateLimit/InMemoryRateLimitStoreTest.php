<?php

namespace tests\oihana\middleware\rateLimit ;

use PHPUnit\Framework\TestCase ;

use oihana\middleware\rateLimit\InMemoryRateLimitStore ;

/**
 * Unit coverage for {@see InMemoryRateLimitStore}.
 */
class InMemoryRateLimitStoreTest extends TestCase
{
    public function testFirstIncrementReturnsOne() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $this->assertSame( 1 , $store->increment( 'k' , 60 ) ) ;
    }

    public function testSubsequentIncrementsAreMonotonic() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $this->assertSame( 1 , $store->increment( 'k' , 60 ) ) ;
        $this->assertSame( 2 , $store->increment( 'k' , 60 ) ) ;
        $this->assertSame( 3 , $store->increment( 'k' , 60 ) ) ;
    }

    public function testDistinctKeysAreIndependent() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $store->increment( 'a' , 60 ) ;
        $store->increment( 'a' , 60 ) ;
        $store->increment( 'b' , 60 ) ;

        $this->assertSame( 3 , $store->increment( 'a' , 60 ) ) ;
        $this->assertSame( 2 , $store->increment( 'b' , 60 ) ) ;
    }

    public function testExpiredCounterIsReinitializedToOne() :void
    {
        $now   = 1_000_000 ;
        $clock = function() use ( &$now ) : int { return $now ; } ;

        $store = new InMemoryRateLimitStore( $clock ) ;

        $this->assertSame( 1 , $store->increment( 'k' , 10 ) ) ;
        $this->assertSame( 2 , $store->increment( 'k' , 10 ) ) ;

        // Jump past the TTL window.
        $now += 11 ;

        $this->assertSame( 1 , $store->increment( 'k' , 10 ) ) ;
    }

    public function testTtlIsNotExtendedOnSubsequentIncrements() :void
    {
        $now   = 1_000_000 ;
        $clock = function() use ( &$now ) : int { return $now ; } ;

        $store = new InMemoryRateLimitStore( $clock ) ;

        // First call at t=0 anchors the window to expire at t=10.
        $store->increment( 'k' , 10 ) ;

        // Spend 8 seconds inside the window — counter rises but window TTL stays anchored.
        $now += 8 ;
        $this->assertSame( 2 , $store->increment( 'k' , 10 ) ) ;

        // Cross the original deadline (anchor + window = 10s) — counter MUST reset to 1.
        $now += 3 ;
        $this->assertSame( 1 , $store->increment( 'k' , 10 ) ) ;
    }
}
