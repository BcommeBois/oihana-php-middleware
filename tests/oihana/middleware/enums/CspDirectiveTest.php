<?php

namespace tests\oihana\middleware\enums ;

use PHPUnit\Framework\TestCase ;
use oihana\middleware\enums\CspDirective ;

/**
 * Unit coverage for {@see CspDirective}.
 */
class CspDirectiveTest extends TestCase
{
    public function testLiteralValuesMatchTheSpec() :void
    {
        $this->assertSame( 'default-src'               , CspDirective::DEFAULT_SRC               ) ;
        $this->assertSame( 'script-src'                , CspDirective::SCRIPT_SRC                ) ;
        $this->assertSame( 'style-src'                 , CspDirective::STYLE_SRC                 ) ;
        $this->assertSame( 'img-src'                   , CspDirective::IMG_SRC                   ) ;
        $this->assertSame( 'font-src'                  , CspDirective::FONT_SRC                  ) ;
        $this->assertSame( 'connect-src'               , CspDirective::CONNECT_SRC               ) ;
        $this->assertSame( 'media-src'                 , CspDirective::MEDIA_SRC                 ) ;
        $this->assertSame( 'object-src'                , CspDirective::OBJECT_SRC                ) ;
        $this->assertSame( 'frame-src'                 , CspDirective::FRAME_SRC                 ) ;
        $this->assertSame( 'worker-src'                , CspDirective::WORKER_SRC                ) ;
        $this->assertSame( 'manifest-src'              , CspDirective::MANIFEST_SRC              ) ;
        $this->assertSame( 'base-uri'                  , CspDirective::BASE_URI                  ) ;
        $this->assertSame( 'form-action'               , CspDirective::FORM_ACTION               ) ;
        $this->assertSame( 'frame-ancestors'           , CspDirective::FRAME_ANCESTORS           ) ;
        $this->assertSame( 'report-uri'                , CspDirective::REPORT_URI                ) ;
        $this->assertSame( 'report-to'                 , CspDirective::REPORT_TO                 ) ;
        $this->assertSame( 'upgrade-insecure-requests' , CspDirective::UPGRADE_INSECURE_REQUESTS ) ;
    }

    public function testGetAllExposesEveryConstantOnce() :void
    {
        $all = CspDirective::getAll() ;

        $this->assertCount( 17 , $all ) ;
        $this->assertContains( CspDirective::DEFAULT_SRC               , $all ) ;
        $this->assertContains( CspDirective::UPGRADE_INSECURE_REQUESTS , $all ) ;
        $this->assertSame( array_unique( $all ) , $all , 'No duplicate value' ) ;
    }
}
