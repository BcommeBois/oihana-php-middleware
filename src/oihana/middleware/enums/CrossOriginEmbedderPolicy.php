<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Vocabulary for the `Cross-Origin-Embedder-Policy` (COEP) response
 * header.
 *
 * COEP controls whether a document is allowed to load cross-origin
 * resources that do not explicitly opt in. Together with a `same-origin`
 * `Cross-Origin-Opener-Policy`, it enables cross-origin isolation —
 * which unlocks powerful APIs like `SharedArrayBuffer`,
 * `performance.now()` high-resolution timestamps, and
 * `performance.measureUserAgentSpecificMemory()`.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class CrossOriginEmbedderPolicy
{
    use ConstantsTrait ;

    /**
     * `unsafe-none` — opt out of COEP entirely. Cross-origin resources
     * may be embedded without an explicit opt-in. Default browser
     * behaviour when the header is absent.
     */
    public const string UNSAFE_NONE = 'unsafe-none' ;

    /**
     * `require-corp` — every cross-origin subresource MUST either carry
     * a `Cross-Origin-Resource-Policy` header explicitly allowing the
     * embedding origin, or be loaded with a CORS request. Required (with
     * COOP `same-origin`) to enable cross-origin isolation.
     */
    public const string REQUIRE_CORP = 'require-corp' ;

    /**
     * `credentialless` — alternative to `require-corp` : the browser
     * loads cross-origin subresources without sending credentials
     * (cookies, client certificates, HTTP auth), so they cannot leak
     * authenticated state into the isolated context. Also enables
     * cross-origin isolation, but without requiring third-party servers
     * to ship CORP headers.
     */
    public const string CREDENTIALLESS = 'credentialless' ;
}
