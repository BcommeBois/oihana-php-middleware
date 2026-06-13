<?php

namespace tests\oihana\middleware\helpers\maintenance ;

use DateTimeImmutable ;
use DateTimeZone ;
use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ResponseInterface ;
use Slim\Psr7\Factory\ResponseFactory ;

use oihana\middleware\enums\MaintenanceOption ;

use function oihana\middleware\helpers\maintenance\respondMaintenanceMode ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\maintenance\respondMaintenanceMode()}.
 */
class RespondMaintenanceModeTest extends TestCase
{
    private function newResponse() :ResponseInterface
    {
        return ( new ResponseFactory() )->createResponse() ;
    }

    public function testEmptyOptionsProducesA503WithNoExtraHeaders() :void
    {
        $response = respondMaintenanceMode( $this->newResponse() ) ;

        $this->assertSame( 503 , $response->getStatusCode() ) ;
        $this->assertSame( 'Service Unavailable' , $response->getReasonPhrase() ) ;
        $this->assertFalse( $response->hasHeader( 'Retry-After'  ) ) ;
        $this->assertFalse( $response->hasHeader( 'Content-Type' ) ) ;
        $this->assertSame( '' , (string) $response->getBody() ) ;
    }

    public function testIntRetryAfterEmitsDeltaSeconds() :void
    {
        $response = respondMaintenanceMode( $this->newResponse() ,
        [
            MaintenanceOption::RETRY_AFTER => 120 ,
        ]) ;

        $this->assertSame( '120' , $response->getHeaderLine( 'Retry-After' ) ) ;
    }

    public function testZeroOrNegativeRetryAfterIsIgnored() :void
    {
        $r0  = respondMaintenanceMode( $this->newResponse() , [ MaintenanceOption::RETRY_AFTER =>  0 ] ) ;
        $rN  = respondMaintenanceMode( $this->newResponse() , [ MaintenanceOption::RETRY_AFTER => -5 ] ) ;

        $this->assertFalse( $r0->hasHeader( 'Retry-After' ) ) ;
        $this->assertFalse( $rN->hasHeader( 'Retry-After' ) ) ;
    }

    public function testDateTimeRetryAfterEmitsImfFixdate() :void
    {
        $when = new DateTimeImmutable( '2026-10-21 07:28:00' , new DateTimeZone( 'UTC' ) ) ;

        $response = respondMaintenanceMode( $this->newResponse() ,
        [
            MaintenanceOption::RETRY_AFTER => $when ,
        ]) ;

        $this->assertSame( 'Wed, 21 Oct 2026 07:28:00 GMT' , $response->getHeaderLine( 'Retry-After' ) ) ;
    }

    public function testStringRetryAfterIsPassedThrough() :void
    {
        // Caller-managed format — passed verbatim.
        $response = respondMaintenanceMode( $this->newResponse() ,
        [
            MaintenanceOption::RETRY_AFTER => 'Wed, 21 Oct 2026 07:28:00 GMT' ,
        ]) ;

        $this->assertSame( 'Wed, 21 Oct 2026 07:28:00 GMT' , $response->getHeaderLine( 'Retry-After' ) ) ;
    }

    public function testEmptyStringRetryAfterIsIgnored() :void
    {
        $response = respondMaintenanceMode( $this->newResponse() ,
        [
            MaintenanceOption::RETRY_AFTER => '' ,
        ]) ;

        $this->assertFalse( $response->hasHeader( 'Retry-After' ) ) ;
    }

    public function testMessageEmitsBodyWithDefaultContentType() :void
    {
        $response = respondMaintenanceMode( $this->newResponse() ,
        [
            MaintenanceOption::MESSAGE => 'Service is undergoing scheduled maintenance.' ,
        ]) ;

        $this->assertSame( 'Service is undergoing scheduled maintenance.' , (string) $response->getBody() ) ;
        $this->assertSame( 'text/plain; charset=utf-8'                    , $response->getHeaderLine( 'Content-Type' ) ) ;
    }

    public function testMessageWithCustomContentType() :void
    {
        $response = respondMaintenanceMode( $this->newResponse() ,
        [
            MaintenanceOption::MESSAGE      => '{"status":"maintenance","eta":120}' ,
            MaintenanceOption::CONTENT_TYPE => 'application/json' ,
        ]) ;

        $this->assertSame( '{"status":"maintenance","eta":120}' , (string) $response->getBody() ) ;
        $this->assertSame( 'application/json' , $response->getHeaderLine( 'Content-Type' ) ) ;
    }

    public function testMessageWithEmptyContentTypeFallsBackToDefault() :void
    {
        // A message is present but the supplied Content-Type is blank — the
        // helper falls back to the text/plain default rather than emitting an
        // empty header.
        $response = respondMaintenanceMode( $this->newResponse() ,
        [
            MaintenanceOption::MESSAGE      => 'Down for maintenance.' ,
            MaintenanceOption::CONTENT_TYPE => '' ,
        ]) ;

        $this->assertSame( 'Down for maintenance.'     , (string) $response->getBody() ) ;
        $this->assertSame( 'text/plain; charset=utf-8' , $response->getHeaderLine( 'Content-Type' ) ) ;
    }

    public function testEmptyMessageIsIgnored() :void
    {
        $response = respondMaintenanceMode( $this->newResponse() ,
        [
            MaintenanceOption::MESSAGE      => '' ,
            MaintenanceOption::CONTENT_TYPE => 'application/json' ,
        ]) ;

        $this->assertSame( '' , (string) $response->getBody() ) ;
        $this->assertFalse( $response->hasHeader( 'Content-Type' ) ) ;
    }

    public function testCombinedOptions() :void
    {
        $response = respondMaintenanceMode( $this->newResponse() ,
        [
            MaintenanceOption::RETRY_AFTER  => 600 ,
            MaintenanceOption::MESSAGE      => '{"status":"maintenance","retry_after_seconds":600}' ,
            MaintenanceOption::CONTENT_TYPE => 'application/json; charset=utf-8' ,
        ]) ;

        $this->assertSame( 503                                                , $response->getStatusCode() ) ;
        $this->assertSame( '600'                                              , $response->getHeaderLine( 'Retry-After'  ) ) ;
        $this->assertSame( 'application/json; charset=utf-8'                  , $response->getHeaderLine( 'Content-Type' ) ) ;
        $this->assertSame( '{"status":"maintenance","retry_after_seconds":600}' , (string) $response->getBody() ) ;
    }

    public function testPreservesPreExistingUnrelatedHeaders() :void
    {
        $response = $this->newResponse()->withHeader( 'X-Request-Id' , 'abc123' ) ;

        $augmented = respondMaintenanceMode( $response ,
        [
            MaintenanceOption::RETRY_AFTER => 60 ,
        ]) ;

        $this->assertSame( 'abc123' , $augmented->getHeaderLine( 'X-Request-Id' ) ) ;
        $this->assertSame( '60'     , $augmented->getHeaderLine( 'Retry-After'  ) ) ;
    }
}
