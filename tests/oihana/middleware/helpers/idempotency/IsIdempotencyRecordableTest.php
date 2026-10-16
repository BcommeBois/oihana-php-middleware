<?php

namespace tests\oihana\middleware\helpers\idempotency ;

use PHPUnit\Framework\Attributes\DataProvider ;
use PHPUnit\Framework\TestCase ;

use function oihana\middleware\helpers\idempotency\isIdempotencyRecordable ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\idempotency\isIdempotencyRecordable()}.
 */
class IsIdempotencyRecordableTest extends TestCase
{
    /**
     * @return array<string, array{int, bool}>
     */
    public static function statuses() :array
    {
        return
        [
            'continue'              => [ 100 , false ] ,
            'ok'                    => [ 200 , true  ] ,
            'created'               => [ 201 , true  ] ,
            'no content'            => [ 204 , true  ] ,
            'see other'             => [ 303 , true  ] ,
            'bad request'           => [ 400 , true  ] ,
            'unauthorized'          => [ 401 , false ] ,
            'forbidden'             => [ 403 , false ] ,
            'not found'             => [ 404 , true  ] ,
            'request timeout'       => [ 408 , false ] ,
            'conflict'              => [ 409 , true  ] ,
            'precondition failed'   => [ 412 , true  ] ,
            'unprocessable'         => [ 422 , true  ] ,
            'too early'             => [ 425 , false ] ,
            'too many requests'     => [ 429 , false ] ,
            'last client error'     => [ 499 , true  ] ,
            'internal server error' => [ 500 , false ] ,
            'bad gateway'           => [ 502 , false ] ,
            'service unavailable'   => [ 503 , false ] ,
        ] ;
    }

    #[DataProvider( 'statuses' )]
    public function testOnlyTheAnswersAReplayWouldGetAgainAreKept( int $status , bool $kept ) :void
    {
        $this->assertSame( $kept , isIdempotencyRecordable( $status ) ) ;
    }
}
