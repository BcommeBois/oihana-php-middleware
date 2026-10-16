<?php

namespace tests\oihana\middleware\idempotency ;

use InvalidArgumentException ;

use PHPUnit\Framework\TestCase ;

use oihana\middleware\enums\IdempotencyField ;
use oihana\middleware\idempotency\IdempotencyRecord ;

/**
 * Unit coverage for {@see IdempotencyRecord}.
 */
class IdempotencyRecordTest extends TestCase
{
    public function testARunningRecordIsNotCompleted() :void
    {
        $record = new IdempotencyRecord( 'abc' , 1_000_120 ) ;

        $this->assertFalse( $record->isCompleted() ) ;
        $this->assertSame( 1_000_120 , $record->lockedUntil ) ;
        $this->assertNull( $record->status ) ;
        $this->assertSame( [] , $record->headers ) ;
        $this->assertNull( $record->body ) ;
    }

    public function testARecordWithAStatusIsCompleted() :void
    {
        $this->assertTrue( new IdempotencyRecord( 'abc' , status : 201 )->isCompleted() ) ;
    }

    public function testToArrayUsesTheFieldKeys() :void
    {
        $record = new IdempotencyRecord
        (
            fingerprint : 'abc' ,
            status      : 201 ,
            headers     : [ 'Location' => [ '/orders/42' ] ] ,
            body        : '{"id":42}' ,
        ) ;

        $this->assertSame
        (
            [
                IdempotencyField::FINGERPRINT  => 'abc' ,
                IdempotencyField::LOCKED_UNTIL => 0 ,
                IdempotencyField::STATUS       => 201 ,
                IdempotencyField::HEADERS      => [ 'Location' => [ '/orders/42' ] ] ,
                IdempotencyField::BODY         => '{"id":42}' ,
            ] ,
            $record->toArray()
        ) ;
    }

    public function testFromArrayRestoresWhatToArrayWrote() :void
    {
        $record = new IdempotencyRecord
        (
            fingerprint : 'abc' ,
            lockedUntil : 7 ,
            status      : 409 ,
            headers     : [ 'Content-Type' => [ 'application/json' ] ] ,
            body        : '{"reason":"not_a_draft"}' ,
        ) ;

        $this->assertEquals( $record , IdempotencyRecord::fromArray( $record->toArray() ) ) ;
    }

    public function testFromArrayRefusesAMissingFingerprint() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        IdempotencyRecord::fromArray( [ IdempotencyField::STATUS => 201 ] ) ;
    }

    public function testFromArrayRefusesAnEmptyFingerprint() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        IdempotencyRecord::fromArray( [ IdempotencyField::FINGERPRINT => '' ] ) ;
    }

    public function testFromArrayFallsBackOnMistypedValues() :void
    {
        $record = IdempotencyRecord::fromArray
        ([
            IdempotencyField::FINGERPRINT  => 'abc' ,
            IdempotencyField::LOCKED_UNTIL => '12' ,
            IdempotencyField::STATUS       => '201' ,
            IdempotencyField::HEADERS      => 'Location: /orders/42' ,
            IdempotencyField::BODY         => 42 ,
        ]) ;

        $this->assertSame( 0 , $record->lockedUntil ) ;
        $this->assertNull( $record->status ) ;
        $this->assertSame( [] , $record->headers ) ;
        $this->assertNull( $record->body ) ;
    }

    public function testFromArrayNormalisesTheHeaders() :void
    {
        $record = IdempotencyRecord::fromArray
        ([
            IdempotencyField::FINGERPRINT => 'abc' ,
            IdempotencyField::HEADERS     =>
            [
                'Location'     => '/orders/42' ,
                'ETag'         => [ '"v1"' , 7 ] ,
                'Content-Type' => [ 7 ] ,
                0              => [ 'dropped' ] ,
            ] ,
        ]) ;

        $this->assertSame
        (
            [
                'Location' => [ '/orders/42' ] ,
                'ETag'     => [ '"v1"' ] ,
            ] ,
            $record->headers
        ) ;
    }
}
