<?php

namespace tests\oihana\middleware\helpers\idempotency ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ResponseInterface ;
use Slim\Psr7\Factory\ResponseFactory ;
use Slim\Psr7\NonBufferedBody ;

use oihana\enums\http\HttpHeader ;
use oihana\middleware\enums\IdempotencyOption ;
use oihana\middleware\enums\IdempotencyOutcome ;
use oihana\middleware\idempotency\IdempotencyDecision ;
use oihana\middleware\idempotency\InMemoryIdempotencyStore ;

use function oihana\middleware\helpers\idempotency\completeIdempotency ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\idempotency\completeIdempotency()}.
 */
class CompleteIdempotencyTest extends TestCase
{
    private int $now = 1_000_000 ;

    private InMemoryIdempotencyStore $store ;

    protected function setUp() :void
    {
        $this->store = new InMemoryIdempotencyStore( fn() :int => $this->now ) ;
        $this->store->begin( 'k' , 'fp' , 120 ) ;
    }

    private function proceed() :IdempotencyDecision
    {
        return new IdempotencyDecision( IdempotencyOutcome::PROCEED , 'k' , 'fp' ) ;
    }

    private function newResponse( int $status , string $body = '{"id":42}' ) :ResponseInterface
    {
        $response = new ResponseFactory()->createResponse( $status )
                                         ->withHeader( HttpHeader::CONTENT_TYPE , 'application/json' )
                                         ->withHeader( HttpHeader::LOCATION     , '/orders/42' )
                                         ->withHeader( 'X-Request-Id'           , 'abc' ) ;

        $response->getBody()->write( $body ) ;

        return $response ;
    }

    public function testAKeptResponseIsStoredForSevenDaysByDefault() :void
    {
        $response = $this->newResponse( 201 ) ;

        $returned = completeIdempotency( $this->proceed() , $response , $this->store ) ;

        $this->assertSame( $response , $returned ) ;
        $this->assertSame( 0 , $returned->getBody()->tell() , 'The body is rewound for the emitter' ) ;

        $record = $this->store->find( 'k' ) ;

        $this->assertNotNull( $record ) ;
        $this->assertSame( 'fp' , $record->fingerprint ) ;
        $this->assertSame( 201 , $record->status ) ;
        $this->assertSame( 0 , $record->lockedUntil ) ;
        $this->assertSame( '{"id":42}' , $record->body ) ;
        $this->assertSame
        (
            [
                HttpHeader::CONTENT_TYPE => [ 'application/json' ] ,
                HttpHeader::LOCATION     => [ '/orders/42' ] ,
            ] ,
            $record->headers ,
            'Only the listed headers are kept'
        ) ;

        $this->now += 604799 ;
        $this->assertNotNull( $this->store->find( 'k' ) ) ;

        $this->now += 1 ;
        $this->assertNull( $this->store->find( 'k' ) ) ;
    }

    public function testTheTtlAndTheHeadersCanBeChosen() :void
    {
        completeIdempotency( $this->proceed() , $this->newResponse( 201 ) , $this->store , [ IdempotencyOption::TTL => 60 , IdempotencyOption::HEADERS => [ 'X-Request-Id' ] ] ) ;

        $this->assertSame( [ 'X-Request-Id' => [ 'abc' ] ] , $this->store->find( 'k' )?->headers ) ;

        $this->now += 60 ;
        $this->assertNull( $this->store->find( 'k' ) ) ;
    }

    public function testADeterministicClientErrorIsKept() :void
    {
        completeIdempotency( $this->proceed() , $this->newResponse( 422 , '{"reason":"unit_already_consumed"}' ) , $this->store ) ;

        $this->assertSame( 422 , $this->store->find( 'k' )?->status ) ;
    }

    public function testAServerErrorReleasesTheKey() :void
    {
        completeIdempotency( $this->proceed() , $this->newResponse( 503 ) , $this->store ) ;

        $this->assertNull( $this->store->find( 'k' ) ) ;
    }

    public function testATooManyRequestsReleasesTheKey() :void
    {
        completeIdempotency( $this->proceed() , $this->newResponse( 429 ) , $this->store ) ;

        $this->assertNull( $this->store->find( 'k' ) ) ;
    }

    public function testANonSeekableBodyIsNeverKept() :void
    {
        $response = new ResponseFactory()->createResponse( 201 )->withBody( new NonBufferedBody() ) ;

        completeIdempotency( $this->proceed() , $response , $this->store ) ;

        $this->assertNull( $this->store->find( 'k' ) ) ;
    }

    public function testAnyOtherDecisionLeavesTheStoreAlone() :void
    {
        foreach ( [ IdempotencyOutcome::SKIP , IdempotencyOutcome::REPLAY , IdempotencyOutcome::IN_PROGRESS ] as $outcome )
        {
            $response = $this->newResponse( 201 ) ;

            $this->assertSame( $response , completeIdempotency( new IdempotencyDecision( $outcome , 'k' , 'fp' ) , $response , $this->store ) ) ;
            $this->assertFalse( $this->store->find( 'k' )?->isCompleted() , $outcome ) ;
        }
    }
}
