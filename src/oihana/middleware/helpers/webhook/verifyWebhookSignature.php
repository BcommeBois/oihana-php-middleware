<?php

declare( strict_types = 1 );

namespace oihana\middleware\helpers\webhook ;

use oihana\middleware\enums\WebhookSignatureOption ;

/**
 * Verifies an HMAC webhook signature using the standard "shared
 * secret + HMAC of the raw payload" pattern.
 *
 * Covers the providers that follow this simple pattern : **GitHub**
 * (`X-Hub-Signature-256: sha256=…`), **Slack** (`X-Slack-Signature:
 * v0=…`), **Shopify** (`X-Shopify-Hmac-Sha256:` base64), **Twilio**
 * (`X-Twilio-Signature:` base64), **SendGrid Event Webhook** (the
 * non-signed-timestamp variant), and any in-house webhook that picks
 * up the same convention.
 *
 * **Out of scope** : providers whose signature scheme blends in a
 * timestamp or a version selector that must be parsed and freshness-
 * checked (Stripe : `t=…,v1=…` ; SendGrid signed-timestamp variant).
 * Use the official provider SDK in that case (`stripe/stripe-php`
 * etc.).
 *
 * Verification is performed in **constant time** via
 * {@see hash_equals()} — required to avoid timing-attack leaks of
 * the expected signature bytes.
 *
 * Behaviour :
 *
 * - Unknown / unsupported `ALGORITHM` ⇒ falls back to `'sha256'` (the
 *   modern default — and the most common production algorithm).
 * - Unknown `ENCODING` ⇒ falls back to `'hex'`.
 * - `PREFIX` is stripped from the head of the incoming `$signature`
 *   when present ; absent or non-matching prefix ⇒ the signature is
 *   compared as-is.
 *
 * @example Verifying a GitHub webhook
 * ```php
 * use function oihana\middleware\helpers\webhook\verifyWebhookSignature ;
 * use oihana\middleware\enums\WebhookSignatureOption ;
 *
 * $payload   = (string) $request->getBody() ;
 * $signature = $request->getHeaderLine( 'X-Hub-Signature-256' ) ;
 *
 * if ( !verifyWebhookSignature( $payload , $signature , $secret ,
 * [
 *     WebhookSignatureOption::PREFIX => 'sha256=' ,
 * ] ) )
 * {
 *     return $responseFactory->createResponse( 401 ) ;
 * }
 * ```
 *
 * @example Verifying a Shopify webhook (base64-encoded HMAC)
 * ```php
 * verifyWebhookSignature( $payload , $signature , $secret ,
 * [
 *     WebhookSignatureOption::ENCODING => 'base64' ,
 * ] ) ;
 * ```
 *
 * @param string               $payload   Raw request body. Caller is expected to read `(string) $request->getBody()` themselves.
 * @param string               $signature Value of the signature header.
 * @param string               $secret    Shared secret agreed between the provider and your application.
 * @param array<string, mixed> $options   Map of options keyed by {@see WebhookSignatureOption} constants.
 *
 * @return bool `true` when the signature is valid for the given payload and secret, `false` otherwise.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\helpers\webhook
 */
function verifyWebhookSignature
(
    string $payload ,
    string $signature ,
    string $secret ,
    array  $options = []
)
: bool
{
    if ( $secret === '' || $signature === '' )
    {
        // Defensive : an empty secret or an empty incoming signature can
        // never produce a meaningful match. Short-circuit to false before
        // even computing the HMAC — avoids the (already constant-time)
        // hash_equals against a zero-length string masking a misconfig.
        return false ;
    }

    $algorithm = $options[ WebhookSignatureOption::ALGORITHM ] ?? 'sha256' ;

    if ( !is_string( $algorithm ) || !in_array( $algorithm , hash_hmac_algos() , true ) )
    {
        $algorithm = 'sha256' ;
    }

    $prefix = $options[ WebhookSignatureOption::PREFIX ] ?? null ;

    if ( is_string( $prefix ) && $prefix !== '' && str_starts_with( $signature , $prefix ) )
    {
        $signature = substr( $signature , strlen( $prefix ) ) ;
    }

    $encoding = $options[ WebhookSignatureOption::ENCODING ] ?? 'hex' ;

    if ( $encoding !== 'base64' )
    {
        $encoding = 'hex' ;
    }

    $expectedRaw = hash_hmac( $algorithm , $payload , $secret , true ) ;
    $expected    = $encoding === 'base64' ? base64_encode( $expectedRaw ) : bin2hex( $expectedRaw ) ;

    return hash_equals( $expected , $signature ) ;
}
