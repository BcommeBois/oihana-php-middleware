<?php

namespace tests\oihana\middleware\tracing ;

use PHPUnit\Framework\TestCase ;

use function oihana\middleware\tracing\parseTraceparent ;

/**
 * Unit coverage for {@see parseTraceparent}.
 */
class ParseTraceparentTest extends TestCase
{
    public function testValidVersion00Parses() :void
    {
        $parsed = parseTraceparent( '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01' ) ;

        $this->assertSame
        ( [
            'traceId'      => '4bf92f3577b34da6a3ce929d0e0e4736' ,
            'parentSpanId' => '00f067aa0ba902b7' ,
            'sampled'      => true ,
          ] ,
          $parsed ,
        ) ;
    }

    public function testFlagsZeroIsNotSampled() :void
    {
        $parsed = parseTraceparent( '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-00' ) ;

        $this->assertFalse( $parsed[ 'sampled' ] ) ;
    }

    public function testReservedFlagBitsAreMaskedOff() :void
    {
        // Only bit 0 of the flags byte is meaningful — `ff` should be treated as sampled.
        $parsed = parseTraceparent( '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-ff' ) ;

        $this->assertTrue( $parsed[ 'sampled' ] ) ;
    }

    public function testWrongLengthReturnsNull() :void
    {
        $this->assertNull( parseTraceparent( '' ) ) ;
        $this->assertNull( parseTraceparent( '00-too-short' ) ) ;
        $this->assertNull( parseTraceparent( '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01-extra' ) ) ;
    }

    public function testUppercaseHexIsRejected() :void
    {
        // W3C requires lowercase hex.
        $this->assertNull
        (
            parseTraceparent( '00-4BF92F3577B34DA6A3CE929D0E0E4736-00f067aa0ba902b7-01' ) ,
        ) ;
    }

    public function testNonHexCharsRejected() :void
    {
        $this->assertNull
        (
            parseTraceparent( '00-4bf92f3577b34da6a3ce929d0e0e473g-00f067aa0ba902b7-01' ) ,
        ) ;
    }

    public function testWrongDelimiterRejected() :void
    {
        // Dots instead of dashes.
        $this->assertNull
        (
            parseTraceparent( '00.4bf92f3577b34da6a3ce929d0e0e4736.00f067aa0ba902b7.01' ) ,
        ) ;
    }

    public function testFutureVersionReturnsNull() :void
    {
        // Even though the rest of the format is well-formed, version != 00
        // is treated as unparseable so the caller regenerates a context.
        $this->assertNull
        (
            parseTraceparent( '01-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01' ) ,
        ) ;
    }

    public function testAllZeroTraceIdRejected() :void
    {
        // Spec sentinel for "invalid".
        $this->assertNull
        (
            parseTraceparent( '00-00000000000000000000000000000000-00f067aa0ba902b7-01' ) ,
        ) ;
    }

    public function testAllZeroSpanIdRejected() :void
    {
        $this->assertNull
        (
            parseTraceparent( '00-4bf92f3577b34da6a3ce929d0e0e4736-0000000000000000-01' ) ,
        ) ;
    }
}
