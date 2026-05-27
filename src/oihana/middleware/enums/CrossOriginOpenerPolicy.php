<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Vocabulary for the `Cross-Origin-Opener-Policy` (COOP) response header.
 *
 * COOP controls whether a top-level document is allowed to share its
 * browsing context group with cross-origin documents — typically those
 * opened via `window.open()` or that opened the current document. The
 * stricter values isolate the browsing context group to same-origin
 * documents and break the `window.opener` reference for cross-origin
 * openers, mitigating cross-origin attacks (XS-Leaks, Spectre) and
 * unlocking cross-origin isolation when paired with a `require-corp`
 * Cross-Origin-Embedder-Policy.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class CrossOriginOpenerPolicy
{
    use ConstantsTrait ;

    /**
     * `unsafe-none` — opt out of COOP entirely. The document keeps its
     * browsing context group with cross-origin openers / openees. Default
     * browser behaviour when the header is absent.
     */
    public const string UNSAFE_NONE = 'unsafe-none' ;

    /**
     * `same-origin-allow-popups` — keeps the browsing context group
     * isolated to same-origin documents, but allows newly opened popups
     * that themselves do not set COOP to remain in the group. Convenient
     * for sites that integrate same-origin top-level navigation with
     * cross-origin OAuth / payment popups.
     */
    public const string SAME_ORIGIN_ALLOW_POPUPS = 'same-origin-allow-popups' ;

    /**
     * `same-origin` — strictest mainstream value. Isolates the document
     * to a same-origin browsing context group. Required (together with
     * `Cross-Origin-Embedder-Policy: require-corp`) to enable cross-origin
     * isolation and unlock APIs like `SharedArrayBuffer` and
     * high-resolution timers.
     */
    public const string SAME_ORIGIN = 'same-origin' ;

    /**
     * `noopener-allow-popups` — variant of `same-origin-allow-popups` that
     * always breaks the `window.opener` reference for popups (effectively
     * `rel="noopener"` everywhere). Shipped in recent Chromium versions.
     */
    public const string NOOPENER_ALLOW_POPUPS = 'noopener-allow-popups' ;

    /**
     * `restrict-properties` — isolates the cross-origin window reference
     * surface (only safe properties like `closed`, `postMessage` remain
     * accessible) without breaking the opener relationship. Shipped in
     * recent Chromium versions as an alternative to `same-origin` when
     * cross-origin isolation is not required.
     */
    public const string RESTRICT_PROPERTIES = 'restrict-properties' ;
}
