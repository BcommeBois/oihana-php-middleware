<?php

namespace tests\oihana\middleware\enums ;

use PHPUnit\Framework\TestCase ;
use oihana\middleware\enums\MaintenanceOption ;

/**
 * Unit coverage for {@see MaintenanceOption}.
 */
class MaintenanceOptionTest extends TestCase
{
    public function testLiteralValuesMatchTheOptionKeys() :void
    {
        $this->assertSame( 'retryAfter'  , MaintenanceOption::RETRY_AFTER  ) ;
        $this->assertSame( 'message'     , MaintenanceOption::MESSAGE      ) ;
        $this->assertSame( 'contentType' , MaintenanceOption::CONTENT_TYPE ) ;
    }

    public function testGetAllExposesEveryConstantOnce() :void
    {
        $all = MaintenanceOption::getAll() ;

        $this->assertCount( 3 , $all ) ;
        $this->assertContains( MaintenanceOption::RETRY_AFTER  , $all ) ;
        $this->assertContains( MaintenanceOption::MESSAGE      , $all ) ;
        $this->assertContains( MaintenanceOption::CONTENT_TYPE , $all ) ;
        $this->assertSame( array_unique( $all ) , $all , 'No duplicate value' ) ;
    }
}
