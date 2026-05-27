<?php

namespace tests\oihana\middleware\helpers\csrf ;

use InvalidArgumentException ;
use PHPUnit\Framework\TestCase ;

use function oihana\middleware\helpers\csrf\generateCsrfToken ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\csrf\generateCsrfToken()}.
 */
class GenerateCsrfTokenTest extends TestCase
{
    private const string SECRET = 'super-secret-key-for-testing' ;

    public function testTokenHasThreeDotSeparatedParts() :void
    {
        $token = generateCsrfToken( self::SECRET ) ;

        $this->assertCount( 3 , explode( '.' , $token ) ) ;
    }

    public function testTokenPartsUseUrlSafeAlphabet() :void
    {
        $token = generateCsrfToken( self::SECRET , 3600 ) ;

        $this->assertMatchesRegularExpression( '/^[A-Za-z0-9_\-]+\.[0-9]+\.[A-Za-z0-9_\-]+$/' , $token ) ;
    }

    public function testNoTtlEmitsZeroExp() :void
    {
        $token = generateCsrfToken( self::SECRET ) ;

        [ , $exp , ] = explode( '.' , $token ) ;

        $this->assertSame( '0' , $exp ) ;
    }

    public function testZeroOrNegativeTtlIsTreatedAsNoTtl() :void
    {
        $a = generateCsrfToken( self::SECRET , 0   ) ;
        $b = generateCsrfToken( self::SECRET , -1  ) ;

        [ , $expA , ] = explode( '.' , $a ) ;
        [ , $expB , ] = explode( '.' , $b ) ;

        $this->assertSame( '0' , $expA ) ;
        $this->assertSame( '0' , $expB ) ;
    }

    public function testPositiveTtlEmitsFutureUnixTimestamp() :void
    {
        $before = time() ;
        $token  = generateCsrfToken( self::SECRET , 3600 ) ;
        $after  = time() ;

        [ , $exp , ] = explode( '.' , $token ) ;
        $exp = (int) $exp ;

        $this->assertGreaterThanOrEqual( $before + 3600 , $exp ) ;
        $this->assertLessThanOrEqual   ( $after  + 3600 , $exp ) ;
    }

    public function testTwoConsecutiveTokensDifferOnTheRandomId() :void
    {
        // The id is the first part of the wire format and is sourced from
        // a CSPRNG; two consecutive tokens must not collide in practice.
        $a = generateCsrfToken( self::SECRET ) ;
        $b = generateCsrfToken( self::SECRET ) ;

        $this->assertNotSame( $a , $b ) ;

        [ $idA , , ] = explode( '.' , $a ) ;
        [ $idB , , ] = explode( '.' , $b ) ;

        $this->assertNotSame( $idA , $idB ) ;
    }

    public function testEmptySecretThrows() :void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( '$secret must be a non-empty string' ) ;

        generateCsrfToken( '' ) ;
    }
}
