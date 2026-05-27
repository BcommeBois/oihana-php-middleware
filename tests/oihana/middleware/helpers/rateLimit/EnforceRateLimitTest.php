<?php

namespace tests\oihana\middleware\helpers\rateLimit ;

use PHPUnit\Framework\TestCase ;

use Psr\Http\Message\ServerRequestInterface ;
use Slim\Psr7\Factory\ServerRequestFactory ;

use oihana\middleware\enums\RateLimitOption ;
use oihana\middleware\rateLimit\InMemoryRateLimitStore ;

use function oihana\middleware\helpers\rateLimit\enforceRateLimit ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\rateLimit\enforceRateLimit()}.
 */
class EnforceRateLimitTest extends TestCase
{
    private function newRequest( array $serverParams = [] ) :ServerRequestInterface
    {
        return ( new ServerRequestFactory() )->createServerRequest( 'GET' , '/api/anything' , $serverParams ) ;
    }

    public function testUnderLimitIsAllowedAndDecrementsRemaining() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $config =
        [
            RateLimitOption::LIMIT  => 3   ,
            RateLimitOption::WINDOW => 60  ,
            RateLimitOption::KEY    => 'k' ,
            RateLimitOption::NOW    => 1_000_000 ,
        ] ;

        $first  = enforceRateLimit( $this->newRequest() , $store , $config ) ;
        $second = enforceRateLimit( $this->newRequest() , $store , $config ) ;
        $third  = enforceRateLimit( $this->newRequest() , $store , $config ) ;

        $this->assertTrue( $first->allowed ) ;
        $this->assertSame( 3 , $first->limit ) ;
        $this->assertSame( 2 , $first->remaining ) ;
        $this->assertSame( 0 , $first->retryAfter ) ;

        $this->assertSame( 1 , $second->remaining ) ;
        $this->assertSame( 0 , $third->remaining ) ;

        $this->assertTrue( $third->allowed ) ;
    }

    public function testOverLimitProducesADisallowedDecisionWithRetryAfter() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $config =
        [
            RateLimitOption::LIMIT  => 2   ,
            RateLimitOption::WINDOW => 60  ,
            RateLimitOption::KEY    => 'k' ,
            RateLimitOption::NOW    => 1_000_000 ,
        ] ;

        enforceRateLimit( $this->newRequest() , $store , $config ) ;
        enforceRateLimit( $this->newRequest() , $store , $config ) ;

        $decision = enforceRateLimit( $this->newRequest() , $store , $config ) ;

        $this->assertFalse( $decision->allowed ) ;
        $this->assertSame( 2 , $decision->limit ) ;
        $this->assertSame( 0 , $decision->remaining ) ;
        $this->assertGreaterThan( 0 , $decision->retryAfter ) ;
    }

    public function testWindowAnchorAndResetAreDeterministic() :void
    {
        $store = new InMemoryRateLimitStore() ;

        // At t = 1_000_000 with window = 60, windowStart = floor(1_000_000 / 60) * 60 = 999_960, reset = 1_000_020.
        $decision = enforceRateLimit
        (
            $this->newRequest() ,
            $store ,
            [
                RateLimitOption::WINDOW => 60        ,
                RateLimitOption::KEY    => 'k'       ,
                RateLimitOption::NOW    => 1_000_000 ,
            ]
        ) ;

        $this->assertSame( 1_000_020 , $decision->reset ) ;
    }

    public function testRetryAfterIsSecondsUntilReset() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $config =
        [
            RateLimitOption::LIMIT  => 1         ,
            RateLimitOption::WINDOW => 60        ,
            RateLimitOption::KEY    => 'k'       ,
            RateLimitOption::NOW    => 1_000_000 ,
        ] ;

        enforceRateLimit( $this->newRequest() , $store , $config ) ;

        $blocked = enforceRateLimit( $this->newRequest() , $store , $config ) ;

        // reset = 1_000_020, now = 1_000_000 ⇒ retryAfter = 20.
        $this->assertSame( 20 , $blocked->retryAfter ) ;
    }

    public function testDistinctKeysAreSegregated() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $base = [ RateLimitOption::LIMIT => 1 , RateLimitOption::WINDOW => 60 , RateLimitOption::NOW => 1_000_000 ] ;

        $alice = enforceRateLimit( $this->newRequest() , $store , $base + [ RateLimitOption::KEY => 'alice' ] ) ;
        $bob   = enforceRateLimit( $this->newRequest() , $store , $base + [ RateLimitOption::KEY => 'bob'   ] ) ;

        $this->assertTrue( $alice->allowed ) ;
        $this->assertTrue( $bob->allowed   ) ;
    }

    public function testScopeIsolatesCountersForTheSameKey() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $base = [ RateLimitOption::LIMIT => 1 , RateLimitOption::WINDOW => 60 , RateLimitOption::NOW => 1_000_000 , RateLimitOption::KEY => 'k' ] ;

        $auth  = enforceRateLimit( $this->newRequest() , $store , $base + [ RateLimitOption::SCOPE => 'auth'  ] ) ;
        $read  = enforceRateLimit( $this->newRequest() , $store , $base + [ RateLimitOption::SCOPE => 'read'  ] ) ;
        $write = enforceRateLimit( $this->newRequest() , $store , $base + [ RateLimitOption::SCOPE => 'write' ] ) ;

        // Each scope keeps its own counter — all three are still allowed despite limit = 1.
        $this->assertTrue( $auth->allowed  ) ;
        $this->assertTrue( $read->allowed  ) ;
        $this->assertTrue( $write->allowed ) ;
    }

    public function testKeyPrefixSegregatesCountersAcrossLimiters() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $base = [ RateLimitOption::LIMIT => 1 , RateLimitOption::WINDOW => 60 , RateLimitOption::NOW => 1_000_000 , RateLimitOption::KEY => 'k' ] ;

        $a = enforceRateLimit( $this->newRequest() , $store , $base + [ RateLimitOption::KEY_PREFIX => 'api'   ] ) ;
        $b = enforceRateLimit( $this->newRequest() , $store , $base + [ RateLimitOption::KEY_PREFIX => 'admin' ] ) ;

        $this->assertTrue( $a->allowed ) ;
        $this->assertTrue( $b->allowed ) ;
    }

    public function testCallableKeyIsResolvedFromTheRequest() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $config =
        [
            RateLimitOption::LIMIT  => 1         ,
            RateLimitOption::WINDOW => 60        ,
            RateLimitOption::NOW    => 1_000_000 ,
            RateLimitOption::KEY    => fn( ServerRequestInterface $r ) => $r->getHeaderLine( 'X-User' ) ,
        ] ;

        $alice = $this->newRequest()->withHeader( 'X-User' , 'alice' ) ;
        $bob   = $this->newRequest()->withHeader( 'X-User' , 'bob'   ) ;

        $this->assertTrue( enforceRateLimit( $alice , $store , $config )->allowed ) ;
        $this->assertTrue( enforceRateLimit( $bob   , $store , $config )->allowed ) ;
        $this->assertFalse( enforceRateLimit( $alice , $store , $config )->allowed ) ;
    }

    public function testCallableKeyEmptyValueFallsBackToUnknown() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $config =
        [
            RateLimitOption::LIMIT  => 1         ,
            RateLimitOption::WINDOW => 60        ,
            RateLimitOption::NOW    => 1_000_000 ,
            RateLimitOption::KEY    => fn() => '' ,
        ] ;

        $first  = enforceRateLimit( $this->newRequest() , $store , $config ) ;
        $second = enforceRateLimit( $this->newRequest() , $store , $config ) ;

        // Both requests fall on the 'unknown' bucket — second one is blocked.
        $this->assertTrue ( $first->allowed  ) ;
        $this->assertFalse( $second->allowed ) ;
    }

    public function testDefaultKeyFallsBackToClientIp() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $a = $this->newRequest( [ 'REMOTE_ADDR' => '203.0.113.10' ] ) ;
        $b = $this->newRequest( [ 'REMOTE_ADDR' => '203.0.113.20' ] ) ;

        $config = [ RateLimitOption::LIMIT => 1 , RateLimitOption::WINDOW => 60 , RateLimitOption::NOW => 1_000_000 ] ;

        $this->assertTrue( enforceRateLimit( $a , $store , $config )->allowed ) ;
        $this->assertTrue( enforceRateLimit( $b , $store , $config )->allowed ) ;

        // Same IP a second time → blocked.
        $this->assertFalse( enforceRateLimit( $a , $store , $config )->allowed ) ;
    }

    public function testDefaultKeyWithNoResolvableIpUsesUnknownSentinel() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $config = [ RateLimitOption::LIMIT => 1 , RateLimitOption::WINDOW => 60 , RateLimitOption::NOW => 1_000_000 ] ;

        // No REMOTE_ADDR and no proxy headers — both requests land on 'unknown'.
        $this->assertTrue ( enforceRateLimit( $this->newRequest() , $store , $config )->allowed ) ;
        $this->assertFalse( enforceRateLimit( $this->newRequest() , $store , $config )->allowed ) ;
    }

    public function testInvalidLimitAndWindowFallBackToDefaults() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $decision = enforceRateLimit
        (
            $this->newRequest() ,
            $store ,
            [
                RateLimitOption::LIMIT  => -1        ,
                RateLimitOption::WINDOW => 0         ,
                RateLimitOption::KEY    => 'k'       ,
                RateLimitOption::NOW    => 1_000_000 ,
            ]
        ) ;

        $this->assertSame( 100 , $decision->limit ) ;
        // window defaulted to 60 ⇒ reset is on the next 60s boundary.
        $this->assertSame( 1_000_020 , $decision->reset ) ;
    }

    public function testInvalidPrefixFallsBackToRatelimit() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $configEmptyPrefix =
        [
            RateLimitOption::LIMIT      => 1         ,
            RateLimitOption::WINDOW     => 60        ,
            RateLimitOption::KEY        => 'k'       ,
            RateLimitOption::NOW        => 1_000_000 ,
            RateLimitOption::KEY_PREFIX => ''        ,
        ] ;

        $configDefaultPrefix =
        [
            RateLimitOption::LIMIT      => 1         ,
            RateLimitOption::WINDOW     => 60        ,
            RateLimitOption::KEY        => 'k'       ,
            RateLimitOption::NOW        => 1_000_000 ,
        ] ;

        // Both configs key on the same final string ⇒ second call must be blocked.
        $this->assertTrue ( enforceRateLimit( $this->newRequest() , $store , $configEmptyPrefix   )->allowed ) ;
        $this->assertFalse( enforceRateLimit( $this->newRequest() , $store , $configDefaultPrefix )->allowed ) ;
    }

    public function testWindowAdvancesResetCounter() :void
    {
        $store = new InMemoryRateLimitStore() ;

        $config =
        [
            RateLimitOption::LIMIT  => 1         ,
            RateLimitOption::WINDOW => 60        ,
            RateLimitOption::KEY    => 'k'       ,
            RateLimitOption::NOW    => 1_000_000 ,
        ] ;

        // First call fills the quota.
        $this->assertTrue( enforceRateLimit( $this->newRequest() , $store , $config )->allowed ) ;
        $this->assertFalse( enforceRateLimit( $this->newRequest() , $store , $config )->allowed ) ;

        // Advance NOW past the window boundary — must clear the counter.
        $next = $config ;
        $next[ RateLimitOption::NOW ] = 1_000_061 ;

        $this->assertTrue( enforceRateLimit( $this->newRequest() , $store , $next )->allowed ) ;
    }
}
