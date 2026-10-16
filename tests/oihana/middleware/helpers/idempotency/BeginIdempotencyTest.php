<?php

namespace tests\oihana\middleware\helpers\idempotency ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ServerRequestInterface ;
use Slim\Psr7\Factory\ServerRequestFactory ;

use oihana\enums\http\HttpHeader ;
use oihana\middleware\enums\IdempotencyOption ;
use oihana\middleware\enums\IdempotencyOutcome ;
use oihana\middleware\idempotency\IdempotencyRecord ;
use oihana\middleware\idempotency\InMemoryIdempotencyStore ;

use function oihana\middleware\helpers\idempotency\beginIdempotency ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\idempotency\beginIdempotency()}.
 */
class BeginIdempotencyTest extends TestCase
{
    private const string KEY = '0b9f6c1e-4a7d-4c2a-9e51-3f8d2b7a6c10' ;

    private InMemoryIdempotencyStore $store ;

    protected function setUp() :void
    {
        $this->store = new InMemoryIdempotencyStore( fn() :int => 1_000_000 ) ;
    }

    private function newRequest( string $method = 'POST' , string $path = '/orders' , ?string $key = self::KEY , array $body = [ 'quantity' => 2 ] ) :ServerRequestInterface
    {
        $request = new ServerRequestFactory()->createServerRequest( $method , $path )->withParsedBody( $body ) ;

        return $key === null ? $request : $request->withHeader( HttpHeader::IDEMPOTENCY_KEY , $key ) ;
    }

    private function complete( string $storeKey , string $fingerprint ) :void
    {
        $this->store->complete( $storeKey , new IdempotencyRecord( $fingerprint , status : 201 , body : '{"id":42}' ) , 3600 ) ;
    }

    public function testASafeMethodIsSkipped() :void
    {
        $decision = beginIdempotency( $this->newRequest( 'GET' ) , $this->store ) ;

        $this->assertSame( IdempotencyOutcome::SKIP , $decision->outcome ) ;
        $this->assertNull( $decision->key ) ;
    }

    public function testTheGuardedMethodsCanBeChosen() :void
    {
        $options = [ IdempotencyOption::METHODS => [ 'post' ] ] ;

        $this->assertSame( IdempotencyOutcome::SKIP    , beginIdempotency( $this->newRequest( 'PUT'  ) , $this->store , $options )->outcome ) ;
        $this->assertSame( IdempotencyOutcome::PROCEED , beginIdempotency( $this->newRequest( 'POST' ) , $this->store , $options )->outcome ) ;
    }

    public function testEveryUnsafeMethodIsGuardedByDefault() :void
    {
        foreach ( [ 'POST' , 'PUT' , 'PATCH' , 'DELETE' ] as $method )
        {
            $this->assertSame( IdempotencyOutcome::PROCEED , beginIdempotency( $this->newRequest( $method ) , $this->store )->outcome , $method ) ;
        }
    }

    public function testNoKeyIsSkippedWhenTheKeyIsOptional() :void
    {
        $this->assertSame( IdempotencyOutcome::SKIP , beginIdempotency( $this->newRequest( key : null ) , $this->store )->outcome ) ;
    }

    public function testNoKeyIsMissingWhenTheKeyIsRequired() :void
    {
        $decision = beginIdempotency( $this->newRequest( key : null ) , $this->store , [ IdempotencyOption::REQUIRED => true ] ) ;

        $this->assertSame( IdempotencyOutcome::MISSING , $decision->outcome ) ;
    }

    public function testAnEmptyKeyIsInvalid() :void
    {
        $this->assertSame( IdempotencyOutcome::INVALID , beginIdempotency( $this->newRequest( key : '  ' ) , $this->store )->outcome ) ;
    }

    public function testAKeyLongerThanTheLimitIsInvalid() :void
    {
        $this->assertSame( IdempotencyOutcome::PROCEED , beginIdempotency( $this->newRequest( key : str_repeat( 'a' , 255 ) ) , $this->store )->outcome ) ;
        $this->assertSame( IdempotencyOutcome::INVALID , beginIdempotency( $this->newRequest( key : str_repeat( 'b' , 256 ) ) , $this->store )->outcome ) ;

        $options = [ IdempotencyOption::MAX_KEY_LENGTH => 8 ] ;

        $this->assertSame( IdempotencyOutcome::INVALID , beginIdempotency( $this->newRequest( key : str_repeat( 'c' , 9 ) ) , $this->store , $options )->outcome ) ;
    }

    public function testAFreeKeyIsClaimed() :void
    {
        $decision = beginIdempotency( $this->newRequest() , $this->store ) ;

        $this->assertSame( IdempotencyOutcome::PROCEED , $decision->outcome ) ;
        $this->assertNotNull( $decision->key ) ;
        $this->assertStringStartsWith( 'idempotency:' , $decision->key ) ;
        $this->assertNotNull( $decision->fingerprint ) ;
        $this->assertNull( $decision->record ) ;
        $this->assertSame( 0 , $decision->retryAfter ) ;
        $this->assertSame( 1_000_120 , $this->store->find( $decision->key )?->lockedUntil ) ;
    }

    public function testTheLeaseAndThePrefixCanBeChosen() :void
    {
        $decision = beginIdempotency( $this->newRequest() , $this->store , [ IdempotencyOption::LEASE => 30 , IdempotencyOption::KEY_PREFIX => 'offline' ] ) ;

        $this->assertStringStartsWith( 'offline:' , (string) $decision->key ) ;
        $this->assertSame( 1_000_030 , $this->store->find( (string) $decision->key )?->lockedUntil ) ;
    }

    public function testTheSameKeyStillRunningIsInProgress() :void
    {
        beginIdempotency( $this->newRequest() , $this->store ) ;

        $decision = beginIdempotency( $this->newRequest() , $this->store ) ;

        $this->assertSame( IdempotencyOutcome::IN_PROGRESS , $decision->outcome ) ;
        $this->assertSame( 2 , $decision->retryAfter ) ;
        $this->assertNotNull( $decision->record ) ;

        $other = beginIdempotency( $this->newRequest() , $this->store , [ IdempotencyOption::RETRY_AFTER => 5 ] ) ;

        $this->assertSame( 5 , $other->retryAfter ) ;
    }

    public function testTheSameKeyWithAKeptResponseIsReplayed() :void
    {
        $first = beginIdempotency( $this->newRequest() , $this->store ) ;

        $this->complete( (string) $first->key , (string) $first->fingerprint ) ;

        $decision = beginIdempotency( $this->newRequest() , $this->store ) ;

        $this->assertSame( IdempotencyOutcome::REPLAY , $decision->outcome ) ;
        $this->assertSame( 201 , $decision->record?->status ) ;
        $this->assertSame( 0 , $decision->retryAfter ) ;
    }

    public function testTheSameKeyWithAnotherBodyIsAMismatch() :void
    {
        $first = beginIdempotency( $this->newRequest() , $this->store ) ;

        $this->complete( (string) $first->key , (string) $first->fingerprint ) ;

        $decision = beginIdempotency( $this->newRequest( body : [ 'quantity' => 3 ] ) , $this->store ) ;

        $this->assertSame( IdempotencyOutcome::MISMATCH , $decision->outcome ) ;
    }

    public function testTwoScopesNeverShareAKey() :void
    {
        $first  = beginIdempotency( $this->newRequest() , $this->store , [ IdempotencyOption::SCOPE => 'user-1' ] ) ;
        $second = beginIdempotency( $this->newRequest() , $this->store , [ IdempotencyOption::SCOPE => fn( ServerRequestInterface $request ) :string => 'user-2' ] ) ;

        $this->assertSame( IdempotencyOutcome::PROCEED , $first->outcome ) ;
        $this->assertSame( IdempotencyOutcome::PROCEED , $second->outcome ) ;
        $this->assertNotSame( $first->key , $second->key ) ;
    }

    public function testAnotherPathOrMethodIsAnotherKey() :void
    {
        $orders = beginIdempotency( $this->newRequest( 'POST' , '/orders' ) , $this->store ) ;
        $quotes = beginIdempotency( $this->newRequest( 'POST' , '/quotes' ) , $this->store ) ;
        $put    = beginIdempotency( $this->newRequest( 'PUT'  , '/orders' ) , $this->store ) ;

        $this->assertSame( IdempotencyOutcome::PROCEED , $quotes->outcome ) ;
        $this->assertSame( IdempotencyOutcome::PROCEED , $put->outcome ) ;
        $this->assertCount( 3 , array_unique( [ $orders->key , $quotes->key , $put->key ] ) ) ;
    }

    public function testMistypedOptionsFallBackOnTheDefaults() :void
    {
        $options =
        [
            IdempotencyOption::METHODS        => 'POST' ,
            IdempotencyOption::MAX_KEY_LENGTH => 0 ,
            IdempotencyOption::KEY_PREFIX     => '' ,
            IdempotencyOption::LEASE          => -1 ,
            IdempotencyOption::SCOPE          => 42 ,
        ] ;

        $decision = beginIdempotency( $this->newRequest() , $this->store , $options ) ;

        $this->assertSame( IdempotencyOutcome::PROCEED , $decision->outcome ) ;
        $this->assertStringStartsWith( 'idempotency:' , (string) $decision->key ) ;
        $this->assertSame( 1_000_120 , $this->store->find( (string) $decision->key )?->lockedUntil ) ;
    }
}
