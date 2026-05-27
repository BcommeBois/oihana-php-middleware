<?php

namespace tests\oihana\middleware\enums ;

use PHPUnit\Framework\TestCase ;
use oihana\middleware\enums\CsrfField ;

/**
 * Unit coverage for {@see CsrfField}.
 */
class CsrfFieldTest extends TestCase
{
    public function testLiteralValuesMatchTheConventionalNames() :void
    {
        $this->assertSame( 'csrf'         , CsrfField::COOKIE_NAME ) ;
        $this->assertSame( 'X-CSRF-Token' , CsrfField::HEADER_NAME ) ;
    }

    public function testGetAllExposesEveryConstantOnce() :void
    {
        $all = CsrfField::getAll() ;

        $this->assertCount( 2 , $all ) ;
        $this->assertContains( CsrfField::COOKIE_NAME , $all ) ;
        $this->assertContains( CsrfField::HEADER_NAME , $all ) ;
        $this->assertSame( array_unique( $all ) , $all , 'No duplicate value' ) ;
    }
}
