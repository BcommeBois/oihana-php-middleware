<?php

namespace tests\oihana\middleware\helpers\idempotency ;

use InvalidArgumentException ;

use PHPUnit\Framework\TestCase ;

use Slim\Psr7\Factory\ResponseFactory ;

use oihana\enums\http\HttpHeader ;
use oihana\middleware\idempotency\IdempotencyRecord ;

use function oihana\middleware\helpers\idempotency\respondIdempotentReplay ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\idempotency\respondIdempotentReplay()}.
 */
class RespondIdempotentReplayTest extends TestCase
{
    public function testTheKeptResponseIsServedAgainAndMarkedAsAReplay() :void
    {
        $record = new IdempotencyRecord
        (
            fingerprint : 'fp' ,
            status      : 201 ,
            headers     :
            [
                HttpHeader::CONTENT_TYPE => [ 'application/json' ] ,
                HttpHeader::LOCATION     => [ '/orders/42' ] ,
            ] ,
            body        : '{"id":42}' ,
        ) ;

        $response = respondIdempotentReplay( new ResponseFactory()->createResponse()->withHeader( HttpHeader::CONTENT_TYPE , 'text/html' ) , $record ) ;

        $this->assertSame( 201 , $response->getStatusCode() ) ;
        $this->assertSame( 'application/json' , $response->getHeaderLine( HttpHeader::CONTENT_TYPE ) , 'A kept header replaces the value already set' ) ;
        $this->assertSame( '/orders/42' , $response->getHeaderLine( HttpHeader::LOCATION ) ) ;
        $this->assertSame( 'true' , $response->getHeaderLine( HttpHeader::IDEMPOTENT_REPLAYED ) ) ;
        $this->assertSame( '{"id":42}' , (string) $response->getBody() ) ;
    }

    public function testAnEmptyKeptBodyStaysEmpty() :void
    {
        $response = respondIdempotentReplay( new ResponseFactory()->createResponse() , new IdempotencyRecord( 'fp' , status : 204 , body : null ) ) ;

        $this->assertSame( 204 , $response->getStatusCode() ) ;
        $this->assertSame( '' , (string) $response->getBody() ) ;
    }

    public function testARunningRecordCannotBeReplayed() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        respondIdempotentReplay( new ResponseFactory()->createResponse() , new IdempotencyRecord( 'fp' , 1_000_120 ) ) ;
    }
}
