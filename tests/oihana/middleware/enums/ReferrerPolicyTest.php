<?php

namespace tests\oihana\middleware\enums ;

use PHPUnit\Framework\TestCase ;
use oihana\middleware\enums\ReferrerPolicy ;

/**
 * Unit coverage for {@see ReferrerPolicy}.
 */
class ReferrerPolicyTest extends TestCase
{
    public function testLiteralValuesMatchTheSpec() :void
    {
        $this->assertSame( 'no-referrer'                     , ReferrerPolicy::NO_REFERRER                     ) ;
        $this->assertSame( 'no-referrer-when-downgrade'      , ReferrerPolicy::NO_REFERRER_WHEN_DOWNGRADE      ) ;
        $this->assertSame( 'origin'                          , ReferrerPolicy::ORIGIN                          ) ;
        $this->assertSame( 'origin-when-cross-origin'        , ReferrerPolicy::ORIGIN_WHEN_CROSS_ORIGIN        ) ;
        $this->assertSame( 'same-origin'                     , ReferrerPolicy::SAME_ORIGIN                     ) ;
        $this->assertSame( 'strict-origin'                   , ReferrerPolicy::STRICT_ORIGIN                   ) ;
        $this->assertSame( 'strict-origin-when-cross-origin' , ReferrerPolicy::STRICT_ORIGIN_WHEN_CROSS_ORIGIN ) ;
        $this->assertSame( 'unsafe-url'                      , ReferrerPolicy::UNSAFE_URL                      ) ;
    }

    public function testGetAllExposesEveryConstantOnce() :void
    {
        $all = ReferrerPolicy::getAll() ;

        $this->assertCount( 8 , $all ) ;
        $this->assertContains( ReferrerPolicy::NO_REFERRER                     , $all ) ;
        $this->assertContains( ReferrerPolicy::STRICT_ORIGIN_WHEN_CROSS_ORIGIN , $all ) ;
        $this->assertSame( array_unique( $all ) , $all , 'No duplicate value' ) ;
    }
}
