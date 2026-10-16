<?php

namespace tests\oihana\middleware\helpers\idempotency ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ServerRequestInterface ;
use Slim\Psr7\Factory\ServerRequestFactory ;
use Slim\Psr7\Factory\StreamFactory ;
use Slim\Psr7\UploadedFile ;

use function oihana\middleware\helpers\idempotency\idempotencyFingerprint ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\idempotency\idempotencyFingerprint()}.
 */
class IdempotencyFingerprintTest extends TestCase
{
    private function newRequest() :ServerRequestInterface
    {
        return new ServerRequestFactory()->createServerRequest( 'POST' , '/orders' ) ;
    }

    private function newFile( string $content , string $name = 'photo.jpg' , string $type = 'image/jpeg' ) :UploadedFile
    {
        return new UploadedFile( new StreamFactory()->createStream( $content ) , $name , $type , strlen( $content ) ) ;
    }

    public function testTheFingerprintIsASha256() :void
    {
        $this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/' , idempotencyFingerprint( $this->newRequest() ) ) ;
    }

    public function testTheOrderOfTheMembersDoesNotMatter() :void
    {
        $first  = $this->newRequest()->withParsedBody( [ 'name' => 'Desk' , 'item' => [ 'color' => 'oak' , 'quantity' => 2 ] ] ) ;
        $second = $this->newRequest()->withParsedBody( [ 'item' => [ 'quantity' => 2 , 'color' => 'oak' ] , 'name' => 'Desk' ] ) ;

        $this->assertSame( idempotencyFingerprint( $first ) , idempotencyFingerprint( $second ) ) ;
    }

    public function testTheOrderOfAListMatters() :void
    {
        $first  = $this->newRequest()->withParsedBody( [ 'lines' => [ 'a' , 'b' ] ] ) ;
        $second = $this->newRequest()->withParsedBody( [ 'lines' => [ 'b' , 'a' ] ] ) ;

        $this->assertNotSame( idempotencyFingerprint( $first ) , idempotencyFingerprint( $second ) ) ;
    }

    public function testAnotherBodyGivesAnotherFingerprint() :void
    {
        $first  = $this->newRequest()->withParsedBody( [ 'quantity' => 2 ] ) ;
        $second = $this->newRequest()->withParsedBody( [ 'quantity' => 3 ] ) ;

        $this->assertNotSame( idempotencyFingerprint( $first ) , idempotencyFingerprint( $second ) ) ;
    }

    public function testAnObjectBodyReadsAsTheMapOfItsProperties() :void
    {
        $object = new \stdClass() ;
        $object->quantity = 2 ;
        $object->name     = 'Desk' ;

        $first  = $this->newRequest()->withParsedBody( $object ) ;
        $second = $this->newRequest()->withParsedBody( [ 'name' => 'Desk' , 'quantity' => 2 ] ) ;

        $this->assertSame( idempotencyFingerprint( $first ) , idempotencyFingerprint( $second ) ) ;
    }

    public function testWithoutAParsedBodyTheRawBodyIsReadAndRewound() :void
    {
        $body    = new StreamFactory()->createStream( 'raw-payload' ) ;
        $request = $this->newRequest()->withBody( $body ) ;
        $other   = $this->newRequest()->withBody( new StreamFactory()->createStream( 'other-payload' ) ) ;

        $fingerprint = idempotencyFingerprint( $request ) ;

        $this->assertSame( 0 , $body->tell() , 'The body is rewound for the next reader' ) ;
        $this->assertSame( $fingerprint , idempotencyFingerprint( $request ) ) ;
        $this->assertNotSame( $fingerprint , idempotencyFingerprint( $other ) ) ;
    }

    public function testTheSameFilesGiveTheSameFingerprintInAnyOrder() :void
    {
        $fields = [ 'payload' => '{"note":"visit"}' ] ;

        $first = $this->newRequest()
                      ->withParsedBody( $fields )
                      ->withUploadedFiles( [ 'photo' => $this->newFile( 'AAA' ) , 'voice' => $this->newFile( 'BBB' , 'voice.webm' , 'audio/webm' ) ] ) ;

        $second = $this->newRequest()
                       ->withParsedBody( $fields )
                       ->withUploadedFiles( [ 'voice' => $this->newFile( 'BBB' , 'voice.webm' , 'audio/webm' ) , 'photo' => $this->newFile( 'AAA' ) ] ) ;

        $this->assertSame( idempotencyFingerprint( $first ) , idempotencyFingerprint( $second ) ) ;
    }

    public function testAChangedFileGivesAnotherFingerprint() :void
    {
        $first  = $this->newRequest()->withUploadedFiles( [ 'photo' => $this->newFile( 'AAA' ) ] ) ;
        $second = $this->newRequest()->withUploadedFiles( [ 'photo' => $this->newFile( 'AAB' ) ] ) ;

        $this->assertNotSame( idempotencyFingerprint( $first ) , idempotencyFingerprint( $second ) ) ;
    }

    public function testTheFieldOfAFileCounts() :void
    {
        $first  = $this->newRequest()->withUploadedFiles( [ 'photo'   => $this->newFile( 'AAA' ) ] ) ;
        $second = $this->newRequest()->withUploadedFiles( [ 'picture' => $this->newFile( 'AAA' ) ] ) ;

        $this->assertNotSame( idempotencyFingerprint( $first ) , idempotencyFingerprint( $second ) ) ;
    }

    public function testNestedFileFieldsAreRead() :void
    {
        $first  = $this->newRequest()->withUploadedFiles( [ 'photos' => [ $this->newFile( 'AAA' ) , $this->newFile( 'BBB' ) ] ] ) ;
        $second = $this->newRequest()->withUploadedFiles( [ 'photos' => [ $this->newFile( 'AAA' ) , $this->newFile( 'CCC' ) ] ] ) ;

        $this->assertNotSame( idempotencyFingerprint( $first ) , idempotencyFingerprint( $second ) ) ;
    }

    public function testAFileStreamIsRewoundForTheHandler() :void
    {
        $file    = $this->newFile( 'AAA' ) ;
        $request = $this->newRequest()->withUploadedFiles( [ 'photo' => $file ] ) ;

        idempotencyFingerprint( $request ) ;

        $this->assertSame( 'AAA' , $file->getStream()->getContents() ) ;
    }
}
