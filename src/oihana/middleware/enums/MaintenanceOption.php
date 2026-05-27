<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Option keys accepted by {@see \oihana\middleware\helpers\maintenance\respondMaintenanceMode()}.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class MaintenanceOption
{
    use ConstantsTrait ;

    /**
     * `retryAfter` — `Retry-After` header value. Accepts `int` (delta seconds), `DateTimeInterface` (formatted as IMF-fixdate), or non-empty `string` (passed through verbatim). Omitted or `null` ⇒ no header.
     */
    public const string RETRY_AFTER = 'retryAfter' ;

    /**
     * `message` — body message written to the response. Omitted, `null` or empty string ⇒ no body.
     */
    public const string MESSAGE = 'message' ;

    /**
     * `contentType` — `Content-Type` header value emitted when a `message` is supplied. Defaults to `text/plain; charset=utf-8`.
     */
    public const string CONTENT_TYPE = 'contentType' ;
}
