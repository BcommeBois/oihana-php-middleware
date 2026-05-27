<?php

namespace tests\oihana\middleware\enums ;

use PHPUnit\Framework\TestCase ;
use oihana\middleware\enums\RequestIdField ;

/**
 * Unit coverage for {@see RequestIdField}.
 */
class RequestIdFieldTest extends TestCase
{
    public function testLiteralValuesMatchTheConventionalNames() :void
    {
        $this->assertSame( 'X-Request-Id' , RequestIdField::HEADER_NAME    ) ;
        $this->assertSame( 'requestId'    , RequestIdField::ATTRIBUTE_NAME ) ;
    }

    public function testGetAllExposesEveryConstantOnce() :void
    {
        $all = RequestIdField::getAll() ;

        $this->assertCount( 2 , $all ) ;
        $this->assertContains( RequestIdField::HEADER_NAME    , $all ) ;
        $this->assertContains( RequestIdField::ATTRIBUTE_NAME , $all ) ;
        $this->assertSame( array_unique( $all ) , $all , 'No duplicate value' ) ;
    }
}
