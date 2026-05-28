<?php

namespace tests\oihana\middleware\helpers\problem ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ResponseInterface ;
use Slim\Psr7\Factory\ResponseFactory ;

use oihana\middleware\problem\Problem ;

use function oihana\middleware\helpers\problem\respondProblemDetails ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\problem\respondProblemDetails()}.
 */
class RespondProblemDetailsTest extends TestCase
{
    private function newResponse() :ResponseInterface
    {
        return ( new ResponseFactory() )->createResponse() ;
    }

    public function testStatusIsTakenFromProblem() :void
    {
        $response = respondProblemDetails
        (
            $this->newResponse() ,
            new Problem( status : 422 ) ,
        ) ;

        $this->assertSame( 422 , $response->getStatusCode() ) ;
    }

    public function testStatusDefaultsTo400WhenProblemStatusIsNull() :void
    {
        $response = respondProblemDetails
        (
            $this->newResponse() ,
            new Problem( title : 'No status declared' ) ,
        ) ;

        $this->assertSame( 400 , $response->getStatusCode() ) ;
    }

    public function testContentTypeIsApplicationProblemJson() :void
    {
        $response = respondProblemDetails( $this->newResponse() , new Problem() ) ;

        $this->assertSame
        (
            'application/problem+json' ,
            $response->getHeaderLine( 'Content-Type' ) ,
        ) ;
    }

    public function testBodyCarriesTheSerialisedProblem() :void
    {
        $problem = new Problem
        (
            type     : 'https://example.com/probs/validation' ,
            title    : 'Validation failed' ,
            status   : 422 ,
            detail   : 'Email must be unique.' ,
            instance : '/users' ,
        ) ;

        $response = respondProblemDetails( $this->newResponse() , $problem ) ;

        $decoded = json_decode( (string) $response->getBody() , true ) ;

        $this->assertSame
        ( [
            'type'     => 'https://example.com/probs/validation' ,
            'title'    => 'Validation failed' ,
            'status'   => 422 ,
            'detail'   => 'Email must be unique.' ,
            'instance' => '/users' ,
          ] ,
          $decoded ,
        ) ;
    }

    public function testExtensionsLandAtTopLevel() :void
    {
        $problem = new Problem
        (
            title      : 'Out of credit' ,
            status     : 403 ,
            extensions :
            [
                'balance'  => 30 ,
                'accounts' => [ '/account/12345' , '/account/67890' ] ,
            ] ,
        ) ;

        $decoded = json_decode( (string) respondProblemDetails( $this->newResponse() , $problem )->getBody() , true ) ;

        $this->assertSame( 30                                            , $decoded[ 'balance'  ] ) ;
        $this->assertSame( [ '/account/12345' , '/account/67890' ]       , $decoded[ 'accounts' ] ) ;
        $this->assertSame( 'Out of credit'                               , $decoded[ 'title'    ] ) ;
    }

    public function testJsonDoesNotEscapeUriSlashes() :void
    {
        $response = respondProblemDetails
        (
            $this->newResponse() ,
            new Problem( type : 'https://example.com/probs/x' ) ,
        ) ;

        // JSON_UNESCAPED_SLASHES keeps `https://...` readable instead of
        // emitting `https:\/\/...`.
        $this->assertStringContainsString
        (
            'https://example.com/probs/x' ,
            (string) $response->getBody() ,
        ) ;
    }

    public function testJsonKeepsNonAsciiCharactersReadable() :void
    {
        $response = respondProblemDetails
        (
            $this->newResponse() ,
            new Problem( title : 'Échec — paiement refusé' ) ,
        ) ;

        // JSON_UNESCAPED_UNICODE keeps the title literal instead of
        // exploding it into \u00xx escapes.
        $this->assertStringContainsString
        (
            'Échec — paiement refusé' ,
            (string) $response->getBody() ,
        ) ;
    }

    public function testInputResponseIsNotMutated() :void
    {
        $original  = $this->newResponse() ;
        $augmented = respondProblemDetails( $original , new Problem( status : 422 ) ) ;

        $this->assertSame( 200 , $original->getStatusCode() ) ;
        $this->assertFalse( $original->hasHeader( 'Content-Type' ) ) ;
        $this->assertNotSame( $original , $augmented ) ;
    }
}
