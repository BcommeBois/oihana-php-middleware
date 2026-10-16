<?php

namespace tests\oihana\middleware\idempotency ;

use PHPUnit\Framework\TestCase ;

use oihana\middleware\idempotency\IdempotencyRecord ;
use oihana\middleware\idempotency\InMemoryIdempotencyStore ;

/**
 * Unit coverage for {@see InMemoryIdempotencyStore}.
 */
class InMemoryIdempotencyStoreTest extends TestCase
{
    private int $now = 1_000_000 ;

    private function newStore() :InMemoryIdempotencyStore
    {
        return new InMemoryIdempotencyStore( fn() :int => $this->now ) ;
    }

    public function testTheFirstBeginClaimsTheKey() :void
    {
        $store = $this->newStore() ;

        $this->assertNull( $store->begin( 'k' , 'fp' , 120 ) ) ;

        $held = $store->find( 'k' ) ;

        $this->assertNotNull( $held ) ;
        $this->assertSame( 'fp' , $held->fingerprint ) ;
        $this->assertSame( 1_000_120 , $held->lockedUntil ) ;
        $this->assertFalse( $held->isCompleted() ) ;
    }

    public function testASecondBeginSeesTheRunningClaim() :void
    {
        $store = $this->newStore() ;

        $store->begin( 'k' , 'fp' , 120 ) ;

        $held = $store->begin( 'k' , 'other' , 120 ) ;

        $this->assertNotNull( $held ) ;
        $this->assertSame( 'fp' , $held->fingerprint , 'The first claim is left untouched' ) ;
    }

    public function testALapsedLeaseIsTakenOver() :void
    {
        $store = $this->newStore() ;

        $store->begin( 'k' , 'fp' , 120 ) ;

        $this->now += 120 ;

        $this->assertNull( $store->begin( 'k' , 'fp' , 120 ) ) ;
        $this->assertSame( 1_000_240 , $store->find( 'k' )?->lockedUntil ) ;
    }

    public function testACompletedRecordIsServedUntilItsTtl() :void
    {
        $store  = $this->newStore() ;
        $record = new IdempotencyRecord( 'fp' , status : 201 , body : '{}' ) ;

        $store->begin( 'k' , 'fp' , 120 ) ;
        $store->complete( 'k' , $record , 3600 ) ;

        $this->now += 3599 ;

        $this->assertSame( $record , $store->find( 'k' ) ) ;
        $this->assertSame( $record , $store->begin( 'k' , 'fp' , 120 ) ) ;

        $this->now += 1 ;

        $this->assertNull( $store->find( 'k' ) ) ;
    }

    public function testReleaseFreesTheKey() :void
    {
        $store = $this->newStore() ;

        $store->begin( 'k' , 'fp' , 120 ) ;
        $store->release( 'k' ) ;

        $this->assertNull( $store->find( 'k' ) ) ;
        $this->assertNull( $store->begin( 'k' , 'fp' , 120 ) ) ;
    }

    public function testDistinctKeysAreIndependent() :void
    {
        $store = $this->newStore() ;

        $this->assertNull( $store->begin( 'a' , 'fp' , 120 ) ) ;
        $this->assertNull( $store->begin( 'b' , 'fp' , 120 ) ) ;
    }

    public function testTheDefaultClockIsTheSystemTime() :void
    {
        $store = new InMemoryIdempotencyStore() ;

        $before = time() ;
        $store->begin( 'k' , 'fp' , 60 ) ;
        $after = time() ;

        $lockedUntil = $store->find( 'k' )?->lockedUntil ;

        $this->assertGreaterThanOrEqual( $before + 60 , $lockedUntil ) ;
        $this->assertLessThanOrEqual( $after + 60 , $lockedUntil ) ;
    }
}
