<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Option keys accepted by
 * {@see \oihana\middleware\helpers\webhook\verifyWebhookSignature()}.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class WebhookSignatureOption
{
    use ConstantsTrait ;

    /**
     * `algorithm` — HMAC algorithm name (`string`, default `'sha256'`). Validated against `hash_hmac_algos()` — unknown algorithms fall back to the default. Common values : `'sha256'` (GitHub, Slack, Shopify), `'sha1'` (legacy GitHub), `'sha512'`.
     */
    public const string ALGORITHM = 'algorithm' ;

    /**
     * `prefix` — string prefix on the incoming signature header that must be stripped before comparison (`string|null`, default `null`). Examples: `'sha256='` (GitHub `X-Hub-Signature-256`), `'v0='` (Slack `X-Slack-Signature`). `null` ⇒ the signature header is compared as-is.
     */
    public const string PREFIX = 'prefix' ;

    /**
     * `encoding` — encoding of the signature bytes (`string`, default `'hex'`). Either `'hex'` (GitHub, Slack — lowercase hexadecimal) or `'base64'` (Twilio, Shopify — standard base64). Unknown values fall back to the default.
     */
    public const string ENCODING = 'encoding' ;
}
