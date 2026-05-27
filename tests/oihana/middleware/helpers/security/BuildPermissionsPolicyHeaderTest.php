<?php

namespace tests\oihana\middleware\helpers\security ;

use InvalidArgumentException ;
use PHPUnit\Framework\TestCase ;

use oihana\middleware\enums\PermissionsPolicyFeature ;

use function oihana\middleware\helpers\security\buildPermissionsPolicyHeader ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\security\buildPermissionsPolicyHeader()}.
 */
class BuildPermissionsPolicyHeaderTest extends TestCase
{
    public function testEmptyInputReturnsEmptyString() :void
    {
        $this->assertSame( '' , buildPermissionsPolicyHeader( [] ) ) ;
    }

    public function testFalseAllowlistEmitsParensDeny() :void
    {
        $this->assertSame
        (
            'geolocation=()' ,
            buildPermissionsPolicyHeader( [ 'geolocation' => false ] ) ,
        ) ;
    }

    public function testTrueAllowlistEmitsStar() :void
    {
        $this->assertSame
        (
            'fullscreen=*' ,
            buildPermissionsPolicyHeader( [ 'fullscreen' => true ] ) ,
        ) ;
    }

    public function testStringStarIsTreatedAsAllowAll() :void
    {
        $this->assertSame
        (
            'fullscreen=*' ,
            buildPermissionsPolicyHeader( [ 'fullscreen' => '*' ] ) ,
        ) ;
    }

    public function testSelfStringEmitsSelfToken() :void
    {
        $this->assertSame
        (
            'camera=(self)' ,
            buildPermissionsPolicyHeader( [ 'camera' => 'self' ] ) ,
        ) ;
    }

    public function testSingleOriginStringIsAutoQuoted() :void
    {
        $this->assertSame
        (
            'payment=("https://stripe.com")' ,
            buildPermissionsPolicyHeader( [ 'payment' => 'https://stripe.com' ] ) ,
        ) ;
    }

    public function testRawStringStartingWithParenIsPassedThrough() :void
    {
        $this->assertSame
        (
            'camera=(self "https://x.com")' ,
            buildPermissionsPolicyHeader( [ 'camera' => '(self "https://x.com")' ] ) ,
        ) ;
    }

    public function testArrayWithSelfAndOriginQuotesOrigin() :void
    {
        $this->assertSame
        (
            'payment=(self "https://stripe.com")' ,
            buildPermissionsPolicyHeader( [ 'payment' => [ 'self' , 'https://stripe.com' ] ] ) ,
        ) ;
    }

    public function testArrayOfOriginsAllAutoQuoted() :void
    {
        $this->assertSame
        (
            'payment=("https://stripe.com" "https://paypal.com")' ,
            buildPermissionsPolicyHeader
            ( [
                'payment' => [ 'https://stripe.com' , 'https://paypal.com' ] ,
            ] ) ,
        ) ;
    }

    public function testEmptyArrayIsDeny() :void
    {
        $this->assertSame
        (
            'geolocation=()' ,
            buildPermissionsPolicyHeader( [ 'geolocation' => [] ] ) ,
        ) ;
    }

    public function testMultipleFeaturesJoinedWithCommaSpace() :void
    {
        $this->assertSame
        (
            'geolocation=(), camera=(self), payment=(self "https://stripe.com"), fullscreen=*' ,
            buildPermissionsPolicyHeader
            ( [
                'geolocation' => false ,
                'camera'      => 'self' ,
                'payment'     => [ 'self' , 'https://stripe.com' ] ,
                'fullscreen'  => '*' ,
            ] ) ,
        ) ;
    }

    public function testInsertionOrderIsPreserved() :void
    {
        $this->assertSame
        (
            'camera=(), geolocation=()' ,
            buildPermissionsPolicyHeader
            ( [
                'camera'      => false ,
                'geolocation' => false ,
            ] ) ,
        ) ;
    }

    public function testWorksWithPermissionsPolicyFeatureEnumKeys() :void
    {
        $this->assertSame
        (
            'camera=(self), microphone=()' ,
            buildPermissionsPolicyHeader
            ( [
                PermissionsPolicyFeature::CAMERA     => 'self' ,
                PermissionsPolicyFeature::MICROPHONE => false ,
            ] ) ,
        ) ;
    }

    public function testEmptyFeatureNameThrows() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( 'feature name must be a non-empty string' ) ;

        buildPermissionsPolicyHeader( [ '' => false ] ) ;
    }

    public function testEmptyStringAllowlistThrows() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( 'allowlist string must be non-empty' ) ;

        buildPermissionsPolicyHeader( [ 'camera' => '' ] ) ;
    }

    public function testEmptyArrayItemThrows() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( 'allowlist items must be non-empty strings' ) ;

        buildPermissionsPolicyHeader( [ 'camera' => [ 'self' , '' ] ] ) ;
    }
}
