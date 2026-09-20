<?php

namespace tests\oihana\middleware\helpers\negotiation ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ServerRequestInterface ;
use Slim\Psr7\Factory\ServerRequestFactory ;

use oihana\enums\Param ;

use function oihana\middleware\helpers\negotiation\negotiateFormat ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\negotiation\negotiateFormat()}.
 */
class NegotiateFormatTest extends TestCase
{
    /**
     * @var array<string,string> Media type => short token, in server preference order.
     */
    private const array FORMATS =
    [
        'application/json' => 'json' ,
        'text/csv'         => 'csv'  ,
        'application/xml'  => 'xml'  ,
    ] ;

    private function newRequest( ?string $accept = null , ?string $format = null ) :ServerRequestInterface
    {
        $request = ( new ServerRequestFactory() )->createServerRequest( 'GET' , '/' ) ;

        if( $accept !== null )
        {
            $request = $request->withHeader( 'Accept' , $accept ) ;
        }

        if( $format !== null )
        {
            $request = $request->withQueryParams( [ Param::FORMAT => $format ] ) ;
        }

        return $request ;
    }

    public function testAcceptedMediaTypeResolvesToItsToken() :void
    {
        $this->assertSame( 'json' , negotiateFormat( $this->newRequest( 'application/json' ) , self::FORMATS ) ) ;
    }

    public function testHighestQValueWins() :void
    {
        // CSV defaults to q=1.0, JSON is explicitly lowered.
        $this->assertSame( 'csv' , negotiateFormat( $this->newRequest( 'application/json;q=0.9, text/csv' ) , self::FORMATS ) ) ;
    }

    public function testWildcardPicksTheServerPreference() :void
    {
        $this->assertSame( 'json' , negotiateFormat( $this->newRequest( '*/*' ) , self::FORMATS ) ) ;
    }

    public function testUnacceptableMediaTypeFallsBackToTheDefault() :void
    {
        $this->assertNull( negotiateFormat( $this->newRequest( 'text/html' ) , self::FORMATS ) ) ;

        $this->assertSame( 'json' , negotiateFormat( $this->newRequest( 'text/html' ) , self::FORMATS , Param::FORMAT , 'json' ) ) ;
    }

    public function testMissingAcceptHeaderFallsBackToTheDefault() :void
    {
        $this->assertNull( negotiateFormat( $this->newRequest() , self::FORMATS ) ) ;

        $this->assertSame( 'json' , negotiateFormat( $this->newRequest() , self::FORMATS , Param::FORMAT , 'json' ) ) ;
    }

    public function testQueryOverrideWinsOverTheAcceptHeader() :void
    {
        $this->assertSame( 'xml' , negotiateFormat( $this->newRequest( 'application/json' , 'xml' ) , self::FORMATS ) ) ;
    }

    public function testQueryOverrideWinsWithoutAnyAcceptHeader() :void
    {
        $this->assertSame( 'csv' , negotiateFormat( $this->newRequest( null , 'csv' ) , self::FORMATS ) ) ;
    }

    public function testUnknownQueryOverrideIsIgnored() :void
    {
        $this->assertSame( 'json' , negotiateFormat( $this->newRequest( 'application/json' , 'yaml' ) , self::FORMATS ) ) ;
    }

    public function testUnknownQueryOverrideAloneFallsBackToTheDefault() :void
    {
        $this->assertSame( 'json' , negotiateFormat( $this->newRequest( null , 'yaml' ) , self::FORMATS , Param::FORMAT , 'json' ) ) ;
    }

    public function testNonStringQueryOverrideIsIgnored() :void
    {
        $request = ( new ServerRequestFactory() )->createServerRequest( 'GET' , '/' )
                                                ->withHeader( 'Accept' , 'application/json' )
                                                ->withQueryParams( [ Param::FORMAT => [ 'csv' ] ] ) ;

        $this->assertSame( 'json' , negotiateFormat( $request , self::FORMATS ) ) ;
    }

    public function testCustomQueryKeyIsHonoured() :void
    {
        $request = ( new ServerRequestFactory() )->createServerRequest( 'GET' , '/' )
                                                ->withQueryParams( [ 'output' => 'csv' ] ) ;

        $this->assertSame( 'csv' , negotiateFormat( $request , self::FORMATS , 'output' ) ) ;
    }

    public function testNullQueryKeyDisablesTheOverride() :void
    {
        $this->assertSame( 'json' , negotiateFormat( $this->newRequest( 'application/json' , 'xml' ) , self::FORMATS , null ) ) ;
    }

    public function testSeveralMediaTypesMayShareOneToken() :void
    {
        $formats =
        [
            'application/xml' => 'xml' ,
            'text/xml'        => 'xml' ,
        ] ;

        $this->assertSame( 'xml' , negotiateFormat( $this->newRequest( 'text/xml' ) , $formats ) ) ;
    }
}
