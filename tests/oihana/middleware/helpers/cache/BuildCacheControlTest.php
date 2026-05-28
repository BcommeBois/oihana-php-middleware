<?php

namespace tests\oihana\middleware\helpers\cache ;

use InvalidArgumentException ;
use PHPUnit\Framework\TestCase ;

use oihana\middleware\enums\CacheDirective ;

use function oihana\middleware\helpers\cache\buildCacheControl ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\cache\buildCacheControl()}.
 */
class BuildCacheControlTest extends TestCase
{
    public function testEmptyInputReturnsEmptyString() :void
    {
        $this->assertSame( '' , buildCacheControl( [] ) ) ;
    }

    public function testSingleFlagDirective() :void
    {
        $this->assertSame
        (
            'public' ,
            buildCacheControl( [ CacheDirective::PUBLIC => true ] ) ,
        ) ;
    }

    public function testFalseValueSilentlyOmits() :void
    {
        // Canonical "off" semantics — different from buildCspHeader (which throws).
        $this->assertSame
        (
            'public' ,
            buildCacheControl
            ( [
                CacheDirective::PUBLIC   => true ,
                CacheDirective::NO_CACHE => false ,
            ] ) ,
        ) ;
    }

    public function testIntValueEmitsDeltaSeconds() :void
    {
        $this->assertSame
        (
            'max-age=3600' ,
            buildCacheControl( [ CacheDirective::MAX_AGE => 3600 ] ) ,
        ) ;
    }

    public function testZeroDeltaSecondsIsEmitted() :void
    {
        // max-age=0 is meaningful — "expired immediately, always revalidate".
        $this->assertSame
        (
            'max-age=0' ,
            buildCacheControl( [ CacheDirective::MAX_AGE => 0 ] ) ,
        ) ;
    }

    public function testNegativeDeltaSecondsIsOmitted() :void
    {
        // Negative values are nonsensical for Cache-Control deltas — silently skip.
        $this->assertSame
        (
            'public' ,
            buildCacheControl
            ( [
                CacheDirective::PUBLIC  => true ,
                CacheDirective::MAX_AGE => -1 ,
            ] ) ,
        ) ;
    }

    public function testStringValueIsEmittedVerbatim() :void
    {
        // Reserved for the rare quoted-string form (no-cache="Set-Cookie").
        $this->assertSame
        (
            'no-cache="Set-Cookie"' ,
            buildCacheControl( [ CacheDirective::NO_CACHE => '"Set-Cookie"' ] ) ,
        ) ;
    }

    public function testMultipleDirectivesJoinedWithCommaSpace() :void
    {
        $this->assertSame
        (
            'public, max-age=3600, s-maxage=86400, stale-while-revalidate=60' ,
            buildCacheControl
            ( [
                CacheDirective::PUBLIC                 => true ,
                CacheDirective::MAX_AGE                => 3600 ,
                CacheDirective::S_MAXAGE               => 86400 ,
                CacheDirective::STALE_WHILE_REVALIDATE => 60 ,
            ] ) ,
        ) ;
    }

    public function testInsertionOrderIsPreserved() :void
    {
        $this->assertSame
        (
            'max-age=3600, public' ,
            buildCacheControl
            ( [
                CacheDirective::MAX_AGE => 3600 ,
                CacheDirective::PUBLIC  => true ,
            ] ) ,
        ) ;
    }

    public function testRawStringKeyIsAccepted() :void
    {
        // Open vocabulary — emerging directives that aren't in the enum yet.
        $this->assertSame
        (
            'fictional-directive' ,
            buildCacheControl( [ 'fictional-directive' => true ] ) ,
        ) ;
    }

    public function testEmptyDirectiveNameThrows() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;

        buildCacheControl( [ '' => true ] ) ;
    }

    public function testUnsupportedValueTypeThrows() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( 'must be bool, int or string' ) ;

        /** @phpstan-ignore-next-line — testing runtime defense */
        buildCacheControl( [ CacheDirective::MAX_AGE => 3.14 ] ) ;
    }
}
