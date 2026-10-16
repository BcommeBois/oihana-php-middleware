<?php

namespace tests\oihana\middleware\enums ;

use PHPUnit\Framework\TestCase ;
use oihana\middleware\enums\IdempotencyOutcome ;

/**
 * Unit coverage for {@see IdempotencyOutcome}.
 */
class IdempotencyOutcomeTest extends TestCase
{
    public function testLiteralValuesMatchTheOutcomes() :void
    {
        $this->assertSame( 'inProgress' , IdempotencyOutcome::IN_PROGRESS ) ;
        $this->assertSame( 'invalid'    , IdempotencyOutcome::INVALID     ) ;
        $this->assertSame( 'mismatch'   , IdempotencyOutcome::MISMATCH    ) ;
        $this->assertSame( 'missing'    , IdempotencyOutcome::MISSING     ) ;
        $this->assertSame( 'proceed'    , IdempotencyOutcome::PROCEED     ) ;
        $this->assertSame( 'replay'     , IdempotencyOutcome::REPLAY      ) ;
        $this->assertSame( 'skip'       , IdempotencyOutcome::SKIP        ) ;
    }

    public function testGetAllExposesEveryConstantOnce() :void
    {
        $all = IdempotencyOutcome::getAll() ;

        $this->assertCount( 7 , $all ) ;
        $this->assertSame( array_unique( $all ) , $all , 'No duplicate value' ) ;
    }
}
