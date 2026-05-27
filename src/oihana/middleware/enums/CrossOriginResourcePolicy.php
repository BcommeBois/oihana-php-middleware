<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Vocabulary for the `Cross-Origin-Resource-Policy` (CORP) response
 * header.
 *
 * CORP declares which origins are allowed to embed *this* resource as
 * a subresource (`<img>`, `<script>`, `<link>`, `fetch`, etc.). It is
 * the resource-side opt-in required by a `require-corp`
 * `Cross-Origin-Embedder-Policy` document to embed cross-origin
 * resources. Without CORP, a resource is implicitly available to
 * everyone (subject to CORS) — set the strictest value compatible with
 * your embedding requirements.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class CrossOriginResourcePolicy
{
    use ConstantsTrait ;

    /**
     * `same-site` — only documents from the same site (registrable
     * domain) may embed the resource. Strictest meaningful value for
     * resources that should not leak to third-party origins.
     */
    public const string SAME_SITE = 'same-site' ;

    /**
     * `same-origin` — only documents from the exact same origin
     * (scheme + host + port) may embed the resource. The strictest
     * value — typically used for private API endpoints.
     */
    public const string SAME_ORIGIN = 'same-origin' ;

    /**
     * `cross-origin` — any origin may embed the resource. Use for
     * resources designed to be publicly embedded (public images, CDN
     * scripts, font files) when they need to remain reachable from
     * `require-corp` documents.
     */
    public const string CROSS_ORIGIN = 'cross-origin' ;
}
