<?php

namespace tests\oihana\middleware\enums ;

use PHPUnit\Framework\TestCase ;
use oihana\middleware\enums\IdempotencyField ;

/**
 * Unit coverage for {@see IdempotencyField}.
 */
class IdempotencyFieldTest extends TestCase
{
    public function testLiteralValuesMatchTheRecordKeys() :void
    {
        $this->assertSame( 'body'        , IdempotencyField::BODY         ) ;
        $this->assertSame( 'fingerprint' , IdempotencyField::FINGERPRINT  ) ;
        $this->assertSame( 'headers'     , IdempotencyField::HEADERS      ) ;
        $this->assertSame( 'lockedUntil' , IdempotencyField::LOCKED_UNTIL ) ;
        $this->assertSame( 'status'      , IdempotencyField::STATUS       ) ;
    }

    public function testGetAllExposesEveryConstantOnce() :void
    {
        $all = IdempotencyField::getAll() ;

        $this->assertCount( 5 , $all ) ;
        $this->assertSame( array_unique( $all ) , $all , 'No duplicate value' ) ;
    }
}
