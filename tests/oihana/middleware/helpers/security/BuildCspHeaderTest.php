<?php

namespace tests\oihana\middleware\helpers\security ;

use InvalidArgumentException ;
use PHPUnit\Framework\TestCase ;

use oihana\middleware\enums\CspDirective ;

use function oihana\middleware\helpers\security\buildCspHeader ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\security\buildCspHeader()}.
 */
class BuildCspHeaderTest extends TestCase
{
    public function testEmptyInputReturnsEmptyString() :void
    {
        $this->assertSame( '' , buildCspHeader( [] ) ) ;
    }

    public function testSingleStringValueDirective() :void
    {
        $this->assertSame
        (
            "default-src 'self'" ,
            buildCspHeader( [ 'default-src' => "'self'" ] ) ,
        ) ;
    }

    public function testStringValueWithMultipleSources() :void
    {
        $this->assertSame
        (
            "script-src 'self' https://cdn.example.com" ,
            buildCspHeader( [ 'script-src' => "'self' https://cdn.example.com" ] ) ,
        ) ;
    }

    public function testListValueJoinedWithSpaces() :void
    {
        $this->assertSame
        (
            "script-src 'self' https://cdn.example.com https://other.example.com" ,
            buildCspHeader
            ( [
                'script-src' =>
                [
                    "'self'" ,
                    'https://cdn.example.com' ,
                    'https://other.example.com' ,
                ] ,
            ] ) ,
        ) ;
    }

    public function testFlagDirectiveViaBooleanTrue() :void
    {
        $this->assertSame
        (
            'upgrade-insecure-requests' ,
            buildCspHeader( [ 'upgrade-insecure-requests' => true ] ) ,
        ) ;
    }

    public function testFlagDirectiveViaEmptyString() :void
    {
        $this->assertSame
        (
            'upgrade-insecure-requests' ,
            buildCspHeader( [ 'upgrade-insecure-requests' => '' ] ) ,
        ) ;
    }

    public function testEmptyListIsTreatedAsFlag() :void
    {
        $this->assertSame
        (
            'upgrade-insecure-requests' ,
            buildCspHeader( [ 'upgrade-insecure-requests' => [] ] ) ,
        ) ;
    }

    public function testCombinationOfDirectivesJoinedWithSemicolonSpace() :void
    {
        $this->assertSame
        (
            "default-src 'self'; script-src 'self' https://cdn.example.com; img-src 'self' data:; upgrade-insecure-requests" ,
            buildCspHeader
            ( [
                'default-src'               => "'self'" ,
                'script-src'                => [ "'self'" , 'https://cdn.example.com' ] ,
                'img-src'                   => "'self' data:" ,
                'upgrade-insecure-requests' => true ,
            ] ) ,
        ) ;
    }

    public function testInsertionOrderIsPreserved() :void
    {
        $this->assertSame
        (
            "script-src 'self'; default-src 'self'" ,
            buildCspHeader
            ( [
                'script-src'  => "'self'" ,
                'default-src' => "'self'" ,
            ] ) ,
        ) ;
    }

    public function testWorksWithCspDirectiveEnumKeys() :void
    {
        $this->assertSame
        (
            "default-src 'self'; frame-ancestors 'none'" ,
            buildCspHeader
            ( [
                CspDirective::DEFAULT_SRC     => "'self'" ,
                CspDirective::FRAME_ANCESTORS => "'none'" ,
            ] ) ,
        ) ;
    }

    public function testFalseValueThrows() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( 'cannot have value `false`' ) ;

        buildCspHeader( [ 'default-src' => false ] ) ;
    }

    public function testEmptyDirectiveNameThrows() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( 'CSP directive name must be a non-empty string' ) ;

        buildCspHeader( [ '' => "'self'" ] ) ;
    }

    public function testEmptySourceInListThrows() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( "sources must be non-empty strings" ) ;

        buildCspHeader( [ 'script-src' => [ "'self'" , '' ] ] ) ;
    }

    public function testNonStringSourceInListThrows() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( "sources must be non-empty strings" ) ;

        /** @phpstan-ignore-next-line — testing runtime defense */
        buildCspHeader( [ 'script-src' => [ "'self'" , 42 ] ] ) ;
    }

    public function testUnsupportedValueTypeThrows() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( 'value must be string, array<string>, or boolean true' ) ;

        /** @phpstan-ignore-next-line — testing runtime defense */
        buildCspHeader( [ 'default-src' => 42 ] ) ;
    }
}
