<?php

namespace tests\oihana\middleware\enums ;

use PHPUnit\Framework\TestCase ;
use oihana\middleware\enums\FrameOptions ;

/**
 * Unit coverage for {@see FrameOptions}.
 */
class FrameOptionsTest extends TestCase
{
    public function testLiteralValuesMatchTheSpec() :void
    {
        $this->assertSame( 'DENY'       , FrameOptions::DENY       ) ;
        $this->assertSame( 'SAMEORIGIN' , FrameOptions::SAME_ORIGIN ) ;
    }

    public function testGetAllExposesEveryConstantOnce() :void
    {
        $all = FrameOptions::getAll() ;

        $this->assertCount( 2 , $all ) ;
        $this->assertContains( FrameOptions::DENY       , $all ) ;
        $this->assertContains( FrameOptions::SAME_ORIGIN , $all ) ;
        $this->assertSame( array_unique( $all ) , $all , 'No duplicate value' ) ;
    }
}
