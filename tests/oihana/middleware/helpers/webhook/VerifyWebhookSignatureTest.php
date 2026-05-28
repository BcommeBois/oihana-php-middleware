<?php

namespace tests\oihana\middleware\helpers\webhook ;

use PHPUnit\Framework\TestCase ;

use oihana\middleware\enums\WebhookSignatureOption ;

use function oihana\middleware\helpers\webhook\verifyWebhookSignature ;

/**
 * Unit coverage for {@see \oihana\middleware\helpers\webhook\verifyWebhookSignature()}.
 */
class VerifyWebhookSignatureTest extends TestCase
{
    private const string SECRET  = 'super-secret-key' ;
    private const string PAYLOAD = '{"event":"push","repo":"acme/api"}' ;

    private function hexSignature( string $payload , string $secret , string $algo = 'sha256' ) :string
    {
        return hash_hmac( $algo , $payload , $secret ) ;
    }

    private function base64Signature( string $payload , string $secret , string $algo = 'sha256' ) :string
    {
        return base64_encode( hash_hmac( $algo , $payload , $secret , true ) ) ;
    }

    public function testValidHexSignaturePasses() :void
    {
        $signature = $this->hexSignature( self::PAYLOAD , self::SECRET ) ;

        $this->assertTrue( verifyWebhookSignature( self::PAYLOAD , $signature , self::SECRET ) ) ;
    }

    public function testWrongSecretFails() :void
    {
        $signature = $this->hexSignature( self::PAYLOAD , self::SECRET ) ;

        $this->assertFalse( verifyWebhookSignature( self::PAYLOAD , $signature , 'wrong-secret' ) ) ;
    }

    public function testTamperedPayloadFails() :void
    {
        $signature = $this->hexSignature( self::PAYLOAD , self::SECRET ) ;

        $this->assertFalse
        (
            verifyWebhookSignature( self::PAYLOAD . 'tampered' , $signature , self::SECRET ) ,
        ) ;
    }

    public function testGithubStylePrefixIsStripped() :void
    {
        // GitHub sends `X-Hub-Signature-256: sha256=<hex>`.
        $signature = 'sha256=' . $this->hexSignature( self::PAYLOAD , self::SECRET ) ;

        $this->assertTrue
        (
            verifyWebhookSignature
            (
                self::PAYLOAD ,
                $signature ,
                self::SECRET ,
                [ WebhookSignatureOption::PREFIX => 'sha256=' ] ,
            ) ,
        ) ;
    }

    public function testSlackStylePrefixIsStripped() :void
    {
        $signature = 'v0=' . $this->hexSignature( self::PAYLOAD , self::SECRET ) ;

        $this->assertTrue
        (
            verifyWebhookSignature
            (
                self::PAYLOAD ,
                $signature ,
                self::SECRET ,
                [ WebhookSignatureOption::PREFIX => 'v0=' ] ,
            ) ,
        ) ;
    }

    public function testMissingPrefixIsCompactedAsIs() :void
    {
        $signature = $this->hexSignature( self::PAYLOAD , self::SECRET ) ;

        // The option declares a prefix, but the incoming signature has none —
        // it must not be silently mis-stripped.
        $this->assertTrue
        (
            verifyWebhookSignature
            (
                self::PAYLOAD ,
                $signature ,
                self::SECRET ,
                [ WebhookSignatureOption::PREFIX => 'sha256=' ] ,
            ) ,
        ) ;
    }

    public function testBase64EncodingWorksForShopifyStyle() :void
    {
        $signature = $this->base64Signature( self::PAYLOAD , self::SECRET ) ;

        $this->assertTrue
        (
            verifyWebhookSignature
            (
                self::PAYLOAD ,
                $signature ,
                self::SECRET ,
                [ WebhookSignatureOption::ENCODING => 'base64' ] ,
            ) ,
        ) ;
    }

    public function testHexEncodingIsRejectedAgainstBase64Signature() :void
    {
        // Default encoding is hex — a base64 signature must fail.
        $signature = $this->base64Signature( self::PAYLOAD , self::SECRET ) ;

        $this->assertFalse( verifyWebhookSignature( self::PAYLOAD , $signature , self::SECRET ) ) ;
    }

    public function testCustomAlgorithmIsHonoured() :void
    {
        $signature = $this->hexSignature( self::PAYLOAD , self::SECRET , 'sha512' ) ;

        $this->assertTrue
        (
            verifyWebhookSignature
            (
                self::PAYLOAD ,
                $signature ,
                self::SECRET ,
                [ WebhookSignatureOption::ALGORITHM => 'sha512' ] ,
            ) ,
        ) ;
    }

    public function testUnknownAlgorithmFallsBackToSha256() :void
    {
        // 'foo-bar' is not a valid HMAC algorithm ⇒ helper falls back to sha256.
        $signature = $this->hexSignature( self::PAYLOAD , self::SECRET , 'sha256' ) ;

        $this->assertTrue
        (
            verifyWebhookSignature
            (
                self::PAYLOAD ,
                $signature ,
                self::SECRET ,
                [ WebhookSignatureOption::ALGORITHM => 'foo-bar' ] ,
            ) ,
        ) ;
    }

    public function testEmptySecretShortCircuitsToFalse() :void
    {
        $signature = $this->hexSignature( self::PAYLOAD , '' ) ;

        $this->assertFalse( verifyWebhookSignature( self::PAYLOAD , $signature , '' ) ) ;
    }

    public function testEmptySignatureShortCircuitsToFalse() :void
    {
        $this->assertFalse( verifyWebhookSignature( self::PAYLOAD , '' , self::SECRET ) ) ;
    }
}
