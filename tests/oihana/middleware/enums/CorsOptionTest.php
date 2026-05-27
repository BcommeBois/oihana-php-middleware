<?php

namespace tests\oihana\middleware\enums ;

use PHPUnit\Framework\TestCase ;
use oihana\middleware\enums\CorsOption ;

/**
 * Unit coverage for {@see CorsOption}.
 */
class CorsOptionTest extends TestCase
{
    public function testLiteralValuesMatchTheOptionKeys() :void
    {
        $this->assertSame( 'allowedOrigins'   , CorsOption::ALLOWED_ORIGINS   ) ;
        $this->assertSame( 'allowedMethods'   , CorsOption::ALLOWED_METHODS   ) ;
        $this->assertSame( 'allowedHeaders'   , CorsOption::ALLOWED_HEADERS   ) ;
        $this->assertSame( 'exposedHeaders'   , CorsOption::EXPOSED_HEADERS   ) ;
        $this->assertSame( 'allowCredentials' , CorsOption::ALLOW_CREDENTIALS ) ;
        $this->assertSame( 'maxAge'           , CorsOption::MAX_AGE           ) ;
    }

    public function testGetAllExposesEveryConstantOnce() :void
    {
        $all = CorsOption::getAll() ;

        $this->assertCount( 6 , $all ) ;
        $this->assertContains( CorsOption::ALLOWED_ORIGINS   , $all ) ;
        $this->assertContains( CorsOption::ALLOW_CREDENTIALS , $all ) ;
        $this->assertSame( array_unique( $all ) , $all , 'No duplicate value' ) ;
    }
}
