<?php

namespace tests\oihana\middleware\enums ;

use PHPUnit\Framework\TestCase ;
use oihana\middleware\enums\SecurityHeadersOption ;

/**
 * Unit coverage for {@see SecurityHeadersOption}.
 */
class SecurityHeadersOptionTest extends TestCase
{
    public function testLiteralValuesMatchTheOptionKeys() :void
    {
        $this->assertSame( 'hsts'                  , SecurityHeadersOption::HSTS                    ) ;
        $this->assertSame( 'hstsIncludeSubdomains' , SecurityHeadersOption::HSTS_INCLUDE_SUBDOMAINS ) ;
        $this->assertSame( 'hstsPreload'           , SecurityHeadersOption::HSTS_PRELOAD            ) ;
        $this->assertSame( 'frameOptions'          , SecurityHeadersOption::FRAME_OPTIONS           ) ;
        $this->assertSame( 'contentTypeNosniff'    , SecurityHeadersOption::CONTENT_TYPE_NOSNIFF    ) ;
        $this->assertSame( 'referrerPolicy'        , SecurityHeadersOption::REFERRER_POLICY         ) ;
        $this->assertSame( 'csp'                   , SecurityHeadersOption::CSP                     ) ;
        $this->assertSame( 'cspReportOnly'         , SecurityHeadersOption::CSP_REPORT_ONLY         ) ;
    }

    public function testGetAllExposesEveryConstantOnce() :void
    {
        $all = SecurityHeadersOption::getAll() ;

        $this->assertCount( 8 , $all ) ;
        $this->assertContains( SecurityHeadersOption::HSTS            , $all ) ;
        $this->assertContains( SecurityHeadersOption::CSP_REPORT_ONLY , $all ) ;
        $this->assertSame( array_unique( $all ) , $all , 'No duplicate value' ) ;
    }
}
