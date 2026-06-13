<?php

namespace tests\oihana\middleware\helpers\host ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ServerRequestInterface ;
use Slim\Psr7\Factory\ServerRequestFactory ;

use function oihana\middleware\helpers\host\enforceTrustedHosts ;
use function oihana\middleware\helpers\host\matchTrustedHost ;
use function oihana\middleware\helpers\host\stripHostPort ;

/**
 * Unit coverage for the trusted-hosts helpers in
 * {@see \oihana\middleware\helpers\host\}.
 */
class EnforceTrustedHostsTest extends TestCase
{
    private function newRequest( ?string $host = null ) :ServerRequestInterface
    {
        $request = ( new ServerRequestFactory() )->createServerRequest( 'GET' , '/' ) ;

        return $host !== null
             ? $request->withHeader( 'Host' , $host )
             : $request->withoutHeader( 'Host' ) ;
    }

    // -------------------------------------------------------------------------
    // enforceTrustedHosts
    // -------------------------------------------------------------------------

    public function testEmptyAllowlistIsNoOpReturnsTrue() :void
    {
        // Safety net : an empty allowlist means "guard disabled", not
        // "block everything".
        $this->assertTrue( enforceTrustedHosts( $this->newRequest( 'anywhere.com' ) , [] ) ) ;
    }

    public function testExactHostMatch() :void
    {
        $request = $this->newRequest( 'example.com' ) ;

        $this->assertTrue ( enforceTrustedHosts( $request , [ 'example.com' ] ) ) ;
        $this->assertFalse( enforceTrustedHosts( $request , [ 'other.com'   ] ) ) ;
    }

    public function testCaseInsensitiveComparison() :void
    {
        $this->assertTrue( enforceTrustedHosts( $this->newRequest( 'EXAMPLE.COM'  ) , [ 'example.com' ] ) ) ;
        $this->assertTrue( enforceTrustedHosts( $this->newRequest( 'Example.Com'  ) , [ 'EXAMPLE.COM' ] ) ) ;
    }

    public function testPortIsStrippedFromHostHeader() :void
    {
        // example.com:8080 should match `example.com` in the allowlist.
        $this->assertTrue( enforceTrustedHosts( $this->newRequest( 'example.com:8080' ) , [ 'example.com' ] ) ) ;
    }

    public function testMissingHostHeaderReturnsFalse() :void
    {
        // HTTP/1.1 requires Host — its absence is suspicious.
        $this->assertFalse( enforceTrustedHosts( $this->newRequest( null ) , [ 'example.com' ] ) ) ;
    }

    public function testMalformedHostHeaderReturnsFalse() :void
    {
        // A present-but-malformed Host (multiple unbracketed colons) strips to
        // empty — can't be trusted, so deny.
        $this->assertFalse( enforceTrustedHosts( $this->newRequest( 'a:b:c:1234' ) , [ 'example.com' ] ) ) ;
    }

    public function testWildcardMatchesSubdomain() :void
    {
        $this->assertTrue( enforceTrustedHosts( $this->newRequest( 'api.example.com' )    , [ '*.example.com' ] ) ) ;
        $this->assertTrue( enforceTrustedHosts( $this->newRequest( 'staging.api.example.com' ) , [ '*.example.com' ] ) ) ;
    }

    public function testWildcardDoesNotMatchApex() :void
    {
        // `*.example.com` does NOT cover `example.com` itself — caller must
        // list the apex explicitly to accept it.
        $this->assertFalse( enforceTrustedHosts( $this->newRequest( 'example.com' ) , [ '*.example.com' ] ) ) ;
    }

    public function testWildcardCombinedWithExactApex() :void
    {
        $allowlist = [ 'example.com' , '*.example.com' ] ;

        $this->assertTrue( enforceTrustedHosts( $this->newRequest( 'example.com'     ) , $allowlist ) ) ;
        $this->assertTrue( enforceTrustedHosts( $this->newRequest( 'api.example.com' ) , $allowlist ) ) ;
    }

    public function testWildcardDoesNotMatchUnrelatedDomain() :void
    {
        $this->assertFalse( enforceTrustedHosts( $this->newRequest( 'attacker.com'         ) , [ '*.example.com' ] ) ) ;
        $this->assertFalse( enforceTrustedHosts( $this->newRequest( 'example.com.attacker.com' ) , [ '*.example.com' ] ) ) ;
    }

    public function testInvalidWildcardPatternIsRejected() :void
    {
        // Nested or mid-string wildcards are rejected — no agreed semantics.
        $this->assertFalse( enforceTrustedHosts( $this->newRequest( 'a.b.example.com' ) , [ '*.*.example.com' ] ) ) ;
        $this->assertFalse( enforceTrustedHosts( $this->newRequest( 'api.test.com'    ) , [ 'api.*.com' ] ) ) ;
    }

    public function testAllowlistEntriesThatAreNotStringsAreSkipped() :void
    {
        // Defensive : a bogus entry in the array doesn't kill the whole check.
        /** @phpstan-ignore-next-line — runtime defense */
        $allowlist = [ 'example.com' , 42 , null , '' ] ;

        $this->assertTrue ( enforceTrustedHosts( $this->newRequest( 'example.com'  ) , $allowlist ) ) ;
        $this->assertFalse( enforceTrustedHosts( $this->newRequest( 'attacker.com' ) , $allowlist ) ) ;
    }

    // -------------------------------------------------------------------------
    // stripHostPort
    // -------------------------------------------------------------------------

    public function testStripHostPortHandlesPlainHostname() :void
    {
        $this->assertSame( 'example.com' , stripHostPort( 'example.com'      ) ) ;
        $this->assertSame( 'example.com' , stripHostPort( 'example.com:8080' ) ) ;
        $this->assertSame( 'example.com' , stripHostPort( 'EXAMPLE.COM:8080' ) ) ;
    }

    public function testStripHostPortHandlesIpv6Literal() :void
    {
        $this->assertSame( '[::1]' , stripHostPort( '[::1]' ) ) ;
        $this->assertSame( '[::1]' , stripHostPort( '[::1]:8080' ) ) ;
    }

    public function testStripHostPortReturnsEmptyOnMalformed() :void
    {
        // Multiple colons in a non-bracketed value is not a legal Host.
        $this->assertSame( '' , stripHostPort( 'a:b:c:1234' ) ) ;
        $this->assertSame( '' , stripHostPort( '' ) ) ;
        $this->assertSame( '' , stripHostPort( '[unclosed-bracket' ) ) ;
    }

    // -------------------------------------------------------------------------
    // matchTrustedHost
    // -------------------------------------------------------------------------

    public function testMatchTrustedHostExactCaseInsensitive() :void
    {
        $this->assertTrue ( matchTrustedHost( 'example.com' , 'EXAMPLE.COM' ) ) ;
        $this->assertFalse( matchTrustedHost( 'example.com' , 'other.com'   ) ) ;
    }

    public function testMatchTrustedHostWildcardSemantics() :void
    {
        $this->assertTrue ( matchTrustedHost( 'api.example.com' , '*.example.com' ) ) ;
        $this->assertFalse( matchTrustedHost( 'example.com'     , '*.example.com' ) ) ;
        $this->assertFalse( matchTrustedHost( 'attacker.com'    , '*.example.com' ) ) ;
    }
}
