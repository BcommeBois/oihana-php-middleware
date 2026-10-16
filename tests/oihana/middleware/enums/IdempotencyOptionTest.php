<?php

namespace tests\oihana\middleware\enums ;

use PHPUnit\Framework\TestCase ;
use oihana\middleware\enums\IdempotencyOption ;

/**
 * Unit coverage for {@see IdempotencyOption}.
 */
class IdempotencyOptionTest extends TestCase
{
    public function testLiteralValuesMatchTheOptionKeys() :void
    {
        $this->assertSame( 'headers'      , IdempotencyOption::HEADERS        ) ;
        $this->assertSame( 'keyPrefix'    , IdempotencyOption::KEY_PREFIX     ) ;
        $this->assertSame( 'lease'        , IdempotencyOption::LEASE          ) ;
        $this->assertSame( 'maxKeyLength' , IdempotencyOption::MAX_KEY_LENGTH ) ;
        $this->assertSame( 'methods'      , IdempotencyOption::METHODS        ) ;
        $this->assertSame( 'required'     , IdempotencyOption::REQUIRED       ) ;
        $this->assertSame( 'retryAfter'   , IdempotencyOption::RETRY_AFTER    ) ;
        $this->assertSame( 'scope'        , IdempotencyOption::SCOPE          ) ;
        $this->assertSame( 'ttl'          , IdempotencyOption::TTL            ) ;
    }

    public function testGetAllExposesEveryConstantOnce() :void
    {
        $all = IdempotencyOption::getAll() ;

        $this->assertCount( 9 , $all ) ;
        $this->assertSame( array_unique( $all ) , $all , 'No duplicate value' ) ;
    }
}
