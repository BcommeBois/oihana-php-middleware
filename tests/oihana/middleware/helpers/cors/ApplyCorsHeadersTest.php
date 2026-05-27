<?php

namespace tests\oihana\middleware\helpers\cors ;

use InvalidArgumentException ;
use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ResponseInterface ;
use Psr\Http\Message\ServerRequestInterface ;

use Slim\Psr7\Factory\ResponseFactory ;
use Slim\Psr7\Factory\ServerRequestFactory ;

use oihana\middleware\enums\CorsOption ;

use function oihana\middleware\helpers\cors\applyCorsHeaders ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\cors\applyCorsHeaders()}.
 */
class ApplyCorsHeadersTest extends TestCase
{
    private function newResponse() :ResponseInterface
    {
        return ( new ResponseFactory() )->createResponse() ;
    }

    private function newRequest
    (
        string $method = 'GET' ,
        ?string $origin = null ,
        array $extraHeaders = [] ,
    ) :ServerRequestInterface
    {
        $request = ( new ServerRequestFactory() )->createServerRequest( $method , '/api/anything' ) ;

        if ( $origin !== null )
        {
            $request = $request->withHeader( 'Origin' , $origin ) ;
        }

        foreach ( $extraHeaders as $name => $value )
        {
            $request = $request->withHeader( $name , $value ) ;
        }

        return $request ;
    }

    // ---- No-Origin / no-allowlist / not-allowed cases ----

    public function testRequestWithoutOriginLeavesResponseUntouched() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'GET' ) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS => [ 'https://app.example.com' ] ,
            ] ,
        ) ;

        $this->assertSame( [] , $response->getHeaders() ) ;
    }

    public function testNoAllowlistLeavesResponseUntouched() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'GET' , 'https://app.example.com' ) ,
            $this->newResponse() ,
            [] ,
        ) ;

        $this->assertSame( [] , $response->getHeaders() ) ;
    }

    public function testOriginNotInAllowlistLeavesResponseUntouched() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'GET' , 'https://attacker.example.com' ) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS => [ 'https://app.example.com' ] ,
            ] ,
        ) ;

        $this->assertSame( [] , $response->getHeaders() ) ;
    }

    // ---- Allowed origin (explicit list) ----

    public function testAllowedExplicitOriginEchoesAndAddsVary() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'GET' , 'https://app.example.com' ) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS => [ 'https://app.example.com' , 'https://admin.example.com' ] ,
            ] ,
        ) ;

        $this->assertSame( 'https://app.example.com' , $response->getHeaderLine( 'Access-Control-Allow-Origin' ) ) ;
        $this->assertSame( 'Origin'                  , $response->getHeaderLine( 'Vary' ) ) ;
    }

    public function testVaryNotDuplicatedWhenAlreadyPresent() :void
    {
        $response = $this->newResponse()->withHeader( 'Vary' , 'Origin' ) ;

        $augmented = applyCorsHeaders
        (
            $this->newRequest( 'GET' , 'https://app.example.com' ) ,
            $response ,
            [
                CorsOption::ALLOWED_ORIGINS => [ 'https://app.example.com' ] ,
            ] ,
        ) ;

        $this->assertSame( [ 'Origin' ] , $augmented->getHeader( 'Vary' ) ) ;
    }

    public function testVaryAppendedWhenAlreadyContainsOtherValues() :void
    {
        $response = $this->newResponse()->withHeader( 'Vary' , 'Accept-Encoding' ) ;

        $augmented = applyCorsHeaders
        (
            $this->newRequest( 'GET' , 'https://app.example.com' ) ,
            $response ,
            [
                CorsOption::ALLOWED_ORIGINS => [ 'https://app.example.com' ] ,
            ] ,
        ) ;

        $this->assertSame( [ 'Accept-Encoding' , 'Origin' ] , $augmented->getHeader( 'Vary' ) ) ;
    }

    // ---- Wildcard ----

    public function testWildcardOriginEmitsStar() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'GET' , 'https://anywhere.example.com' ) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS => '*' ,
            ] ,
        ) ;

        $this->assertSame( '*' , $response->getHeaderLine( 'Access-Control-Allow-Origin' ) ) ;
        $this->assertFalse( $response->hasHeader( 'Vary' ) ) ;
    }

    public function testWildcardWithCredentialsThrows() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( '`allowedOrigins: "*"`' ) ;

        applyCorsHeaders
        (
            $this->newRequest( 'GET' , 'https://anywhere.example.com' ) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS   => '*' ,
                CorsOption::ALLOW_CREDENTIALS => true ,
            ] ,
        ) ;
    }

    // ---- Credentials & exposed headers ----

    public function testCredentialsEmitsAllowCredentialsHeader() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'GET' , 'https://app.example.com' ) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS   => [ 'https://app.example.com' ] ,
                CorsOption::ALLOW_CREDENTIALS => true ,
            ] ,
        ) ;

        $this->assertSame( 'true' , $response->getHeaderLine( 'Access-Control-Allow-Credentials' ) ) ;
    }

    public function testExposedHeadersAreEmittedCommaSeparated() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'GET' , 'https://app.example.com' ) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS => [ 'https://app.example.com' ] ,
                CorsOption::EXPOSED_HEADERS => [ 'X-Request-Id' , 'X-RateLimit-Remaining' ] ,
            ] ,
        ) ;

        $this->assertSame
        (
            'X-Request-Id, X-RateLimit-Remaining' ,
            $response->getHeaderLine( 'Access-Control-Expose-Headers' ) ,
        ) ;
    }

    // ---- Preflight detection ----

    public function testOptionsWithoutRequestMethodIsNotAPreflight() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'OPTIONS' , 'https://app.example.com' ) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS => [ 'https://app.example.com' ] ,
                CorsOption::ALLOWED_METHODS => [ 'GET' , 'POST' ] ,
                CorsOption::MAX_AGE         => 600 ,
            ] ,
        ) ;

        $this->assertFalse( $response->hasHeader( 'Access-Control-Allow-Methods' ) ) ;
        $this->assertFalse( $response->hasHeader( 'Access-Control-Max-Age'       ) ) ;
    }

    public function testPreflightEmitsAllowMethods() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'OPTIONS' , 'https://app.example.com' ,
            [
                'Access-Control-Request-Method' => 'POST' ,
            ]) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS => [ 'https://app.example.com' ] ,
                CorsOption::ALLOWED_METHODS => [ 'GET' , 'POST' , 'DELETE' ] ,
            ] ,
        ) ;

        $this->assertSame
        (
            'GET, POST, DELETE' ,
            $response->getHeaderLine( 'Access-Control-Allow-Methods' ) ,
        ) ;
    }

    public function testPreflightEmitsExplicitAllowHeaders() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'OPTIONS' , 'https://app.example.com' ,
            [
                'Access-Control-Request-Method'  => 'POST' ,
                'Access-Control-Request-Headers' => 'Content-Type' ,
            ]) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS => [ 'https://app.example.com' ] ,
                CorsOption::ALLOWED_HEADERS => [ 'Authorization' , 'Content-Type' ] ,
            ] ,
        ) ;

        $this->assertSame
        (
            'Authorization, Content-Type' ,
            $response->getHeaderLine( 'Access-Control-Allow-Headers' ) ,
        ) ;
    }

    public function testPreflightEchoesRequestHeadersWhenAllowedHeadersOmitted() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'OPTIONS' , 'https://app.example.com' ,
            [
                'Access-Control-Request-Method'  => 'POST' ,
                'Access-Control-Request-Headers' => 'X-Custom, Authorization' ,
            ]) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS => [ 'https://app.example.com' ] ,
            ] ,
        ) ;

        $this->assertSame
        (
            'X-Custom, Authorization' ,
            $response->getHeaderLine( 'Access-Control-Allow-Headers' ) ,
        ) ;
    }

    public function testPreflightEmitsMaxAge() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'OPTIONS' , 'https://app.example.com' ,
            [
                'Access-Control-Request-Method' => 'POST' ,
            ]) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS => [ 'https://app.example.com' ] ,
                CorsOption::MAX_AGE         => 3600 ,
            ] ,
        ) ;

        $this->assertSame( '3600' , $response->getHeaderLine( 'Access-Control-Max-Age' ) ) ;
    }

    public function testNonPreflightDoesNotEmitMaxAgeOrMethods() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'GET' , 'https://app.example.com' ) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS => [ 'https://app.example.com' ] ,
                CorsOption::ALLOWED_METHODS => [ 'GET' , 'POST' ] ,
                CorsOption::MAX_AGE         => 3600 ,
            ] ,
        ) ;

        $this->assertFalse( $response->hasHeader( 'Access-Control-Allow-Methods' ) ) ;
        $this->assertFalse( $response->hasHeader( 'Access-Control-Max-Age'       ) ) ;
    }

    // ---- PSR-7 immutability ----

    public function testInputResponseIsNotMutated() :void
    {
        $original = $this->newResponse() ;

        applyCorsHeaders
        (
            $this->newRequest( 'GET' , 'https://app.example.com' ) ,
            $original ,
            [
                CorsOption::ALLOWED_ORIGINS => [ 'https://app.example.com' ] ,
            ] ,
        ) ;

        $this->assertFalse( $original->hasHeader( 'Access-Control-Allow-Origin' ) ) ;
    }

    public function testCombinedRealisticConfig() :void
    {
        $response = applyCorsHeaders
        (
            $this->newRequest( 'OPTIONS' , 'https://app.example.com' ,
            [
                'Access-Control-Request-Method'  => 'DELETE' ,
                'Access-Control-Request-Headers' => 'Content-Type' ,
            ]) ,
            $this->newResponse() ,
            [
                CorsOption::ALLOWED_ORIGINS   => [ 'https://app.example.com' , 'https://admin.example.com' ] ,
                CorsOption::ALLOWED_METHODS   => [ 'GET' , 'POST' , 'DELETE' ] ,
                CorsOption::ALLOWED_HEADERS   => [ 'Authorization' , 'Content-Type' ] ,
                CorsOption::EXPOSED_HEADERS   => [ 'X-Request-Id' ] ,
                CorsOption::ALLOW_CREDENTIALS => true ,
                CorsOption::MAX_AGE           => 600 ,
            ] ,
        ) ;

        $this->assertSame( 'https://app.example.com'      , $response->getHeaderLine( 'Access-Control-Allow-Origin'      ) ) ;
        $this->assertSame( 'Origin'                       , $response->getHeaderLine( 'Vary'                             ) ) ;
        $this->assertSame( 'true'                         , $response->getHeaderLine( 'Access-Control-Allow-Credentials' ) ) ;
        $this->assertSame( 'X-Request-Id'                 , $response->getHeaderLine( 'Access-Control-Expose-Headers'    ) ) ;
        $this->assertSame( 'GET, POST, DELETE'            , $response->getHeaderLine( 'Access-Control-Allow-Methods'     ) ) ;
        $this->assertSame( 'Authorization, Content-Type'  , $response->getHeaderLine( 'Access-Control-Allow-Headers'     ) ) ;
        $this->assertSame( '600'                          , $response->getHeaderLine( 'Access-Control-Max-Age'           ) ) ;
    }
}
