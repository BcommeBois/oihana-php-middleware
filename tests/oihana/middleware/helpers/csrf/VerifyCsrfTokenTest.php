<?php

namespace tests\oihana\middleware\helpers\csrf ;

use PHPUnit\Framework\TestCase ;

use function oihana\middleware\helpers\csrf\generateCsrfToken ;
use function oihana\middleware\helpers\csrf\verifyCsrfToken ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\csrf\verifyCsrfToken()}.
 */
class VerifyCsrfTokenTest extends TestCase
{
    private const string SECRET = 'super-secret-key-for-testing' ;

    public function testRoundTripWithoutTtl() :void
    {
        $token = generateCsrfToken( self::SECRET ) ;

        $this->assertTrue( verifyCsrfToken( $token , $token , self::SECRET ) ) ;
    }

    public function testRoundTripWithTtl() :void
    {
        $token = generateCsrfToken( self::SECRET , 3600 ) ;

        $this->assertTrue( verifyCsrfToken( $token , $token , self::SECRET ) ) ;
    }

    public function testCookieAndSubmittedTokenMustMatch() :void
    {
        $cookieToken    = generateCsrfToken( self::SECRET ) ;
        $submittedToken = generateCsrfToken( self::SECRET ) ;

        // Two valid tokens, individually verifiable, but distinct ⇒ reject.
        $this->assertFalse( verifyCsrfToken( $cookieToken , $submittedToken , self::SECRET ) ) ;
    }

    public function testWrongSecretRejects() :void
    {
        $token = generateCsrfToken( self::SECRET ) ;

        $this->assertFalse( verifyCsrfToken( $token , $token , 'another-secret' ) ) ;
    }

    public function testTamperedIdRejects() :void
    {
        $token = generateCsrfToken( self::SECRET ) ;

        [ $id , $exp , $sig ] = explode( '.' , $token ) ;

        // Flip a character of the id — signature won't match anymore.
        $tampered = ( $id[ 0 ] === 'A' ? 'B' : 'A' ) . substr( $id , 1 ) . '.' . $exp . '.' . $sig ;

        $this->assertFalse( verifyCsrfToken( $tampered , $tampered , self::SECRET ) ) ;
    }

    public function testTamperedExpRejects() :void
    {
        $token = generateCsrfToken( self::SECRET , 3600 ) ;

        [ $id , $exp , $sig ] = explode( '.' , $token ) ;

        // Push the expiry further into the future without re-signing.
        $tampered = $id . '.' . ( (int) $exp + 999999 ) . '.' . $sig ;

        $this->assertFalse( verifyCsrfToken( $tampered , $tampered , self::SECRET ) ) ;
    }

    public function testExpiredTokenRejects() :void
    {
        // Build a token with an expiry already in the past, with a correct
        // signature so only the TTL check rejects it.
        $id  = 'aaaabbbbccccddddeeeeff' ;
        $exp = (string) ( time() - 60 ) ;
        $payload = $id . '.' . $exp ;
        $sig = \oihana\core\encoding\base64UrlEncode( hash_hmac( 'sha256' , $payload , self::SECRET , true ) ) ;
        $token = $payload . '.' . $sig ;

        $this->assertFalse( verifyCsrfToken( $token , $token , self::SECRET ) ) ;
    }

    public function testNoTtlNeverExpires() :void
    {
        // exp = '0' means "no TTL" — the token does not expire by time
        // (caller must rotate by other means).
        $token = generateCsrfToken( self::SECRET ) ;

        [ , $exp , ] = explode( '.' , $token ) ;
        $this->assertSame( '0' , $exp ) ;

        $this->assertTrue( verifyCsrfToken( $token , $token , self::SECRET ) ) ;
    }

    public function testMalformedTokenRejects() :void
    {
        $this->assertFalse( verifyCsrfToken( 'not-a-csrf-token'        , 'not-a-csrf-token'        , self::SECRET ) ) ;
        $this->assertFalse( verifyCsrfToken( 'only.two-parts'          , 'only.two-parts'          , self::SECRET ) ) ;
        $this->assertFalse( verifyCsrfToken( 'four.parts.are.too-many' , 'four.parts.are.too-many' , self::SECRET ) ) ;
    }

    public function testEmptyPartRejects() :void
    {
        $this->assertFalse( verifyCsrfToken( '..sig'  , '..sig'  , self::SECRET ) ) ;
        $this->assertFalse( verifyCsrfToken( 'id..sig' , 'id..sig' , self::SECRET ) ) ;
        $this->assertFalse( verifyCsrfToken( 'id.exp.' , 'id.exp.' , self::SECRET ) ) ;
    }

    public function testEmptyInputsReject() :void
    {
        $token = generateCsrfToken( self::SECRET ) ;

        $this->assertFalse( verifyCsrfToken( ''     , $token , self::SECRET ) ) ;
        $this->assertFalse( verifyCsrfToken( $token , ''     , self::SECRET ) ) ;
        $this->assertFalse( verifyCsrfToken( $token , $token , ''           ) ) ;
        $this->assertFalse( verifyCsrfToken( ''     , ''     , self::SECRET ) ) ;
    }

    public function testNeverThrows() :void
    {
        // Defensive: even with absurd inputs, the helper must return false,
        // never raise. Callers use the boolean as the allow/deny gate.
        $this->assertFalse( verifyCsrfToken( "\x00\x01\x02" , "\x00\x01\x02" , self::SECRET ) ) ;
        $this->assertFalse( verifyCsrfToken( str_repeat( 'A' , 10000 ) , str_repeat( 'A' , 10000 ) , self::SECRET ) ) ;
    }
}
