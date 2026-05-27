<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Valid values for the `X-Frame-Options` HTTP response header.
 *
 * Controls whether the page is allowed to be embedded in a `<frame>`,
 * `<iframe>`, `<embed>` or `<object>`. Mitigates clickjacking attacks.
 *
 * The `ALLOW-FROM uri` form is deprecated and not supported in modern
 * browsers; for that use case prefer the `Content-Security-Policy:
 * frame-ancestors` directive (cf. {@see CspDirective::FRAME_ANCESTORS}).
 *
 * See RFC 7034.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class FrameOptions
{
    use ConstantsTrait ;

    /**
     * `DENY` — the page cannot be displayed in a frame, regardless of the framing site.
     */
    public const string DENY = 'DENY' ;

    /**
     * `SAMEORIGIN` — the page can only be framed by pages on the same origin.
     */
    public const string SAME_ORIGIN = 'SAMEORIGIN' ;
}
