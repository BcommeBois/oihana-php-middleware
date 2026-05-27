<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Canonical directive names for the `Content-Security-Policy` HTTP response header.
 *
 * Exposed for typed composition with {@see buildCspHeader()} so callers
 * can author CSP policies without scattering magic strings.
 *
 * Covers the most commonly used CSP Level 3 directives. The list is not
 * exhaustive — when a less common directive is needed, callers can pass
 * the raw directive name as a string to `buildCspHeader()`; the enum is
 * a convenience, not a closed vocabulary.
 *
 * See the W3C Content Security Policy Level 3 specification:
 * https://www.w3.org/TR/CSP3/
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class CspDirective
{
    use ConstantsTrait ;

    /**
     * `default-src` — fallback for the other fetch directives.
     */
    public const string DEFAULT_SRC = 'default-src' ;

    /**
     * `script-src` — sources for JavaScript.
     */
    public const string SCRIPT_SRC = 'script-src' ;

    /**
     * `style-src` — sources for stylesheets.
     */
    public const string STYLE_SRC = 'style-src' ;

    /**
     * `img-src` — sources for images.
     */
    public const string IMG_SRC = 'img-src' ;

    /**
     * `font-src` — sources for fonts.
     */
    public const string FONT_SRC = 'font-src' ;

    /**
     * `connect-src` — sources for XHR / fetch / WebSocket / EventSource.
     */
    public const string CONNECT_SRC = 'connect-src' ;

    /**
     * `media-src` — sources for `<audio>`, `<video>`, `<track>`.
     */
    public const string MEDIA_SRC = 'media-src' ;

    /**
     * `object-src` — sources for `<object>`, `<embed>`, `<applet>`. Setting this to `'none'` is strongly recommended.
     */
    public const string OBJECT_SRC = 'object-src' ;

    /**
     * `frame-src` — sources allowed in nested browsing contexts (`<iframe>`, `<frame>`).
     */
    public const string FRAME_SRC = 'frame-src' ;

    /**
     * `worker-src` — sources for Workers, SharedWorkers, ServiceWorkers.
     */
    public const string WORKER_SRC = 'worker-src' ;

    /**
     * `manifest-src` — sources for the application manifest.
     */
    public const string MANIFEST_SRC = 'manifest-src' ;

    /**
     * `base-uri` — restricts URLs the `<base>` element can use.
     */
    public const string BASE_URI = 'base-uri' ;

    /**
     * `form-action` — restricts URLs that can be used as the target of form submissions.
     */
    public const string FORM_ACTION = 'form-action' ;

    /**
     * `frame-ancestors` — restricts which parents may embed this page. Replaces `X-Frame-Options`.
     */
    public const string FRAME_ANCESTORS = 'frame-ancestors' ;

    /**
     * `report-uri` — endpoint URL for policy violation reports. Deprecated in CSP3 (use `REPORT_TO`), kept for compatibility.
     */
    public const string REPORT_URI = 'report-uri' ;

    /**
     * `report-to` — endpoint identifier (declared via the `Report-To` header) for policy violation reports.
     */
    public const string REPORT_TO = 'report-to' ;

    /**
     * `upgrade-insecure-requests` — instructs the browser to upgrade HTTP requests to HTTPS automatically.
     */
    public const string UPGRADE_INSECURE_REQUESTS = 'upgrade-insecure-requests' ;
}
