<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Default field names for the stateless double-submit CSRF helpers.
 *
 * Provides typed constants for the cookie name and request header name
 * commonly used to carry the CSRF token, so consumers can wire the
 * helpers without spelling the names by hand. Callers are free to use
 * their own names — these are conventions, not requirements.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class CsrfField
{
    use ConstantsTrait ;

    /**
     * `csrf` — default cookie name carrying the CSRF token (server → client).
     */
    public const string COOKIE_NAME = 'csrf' ;

    /**
     * `X-CSRF-Token` — default request header name carrying the CSRF token (client → server).
     */
    public const string HEADER_NAME = 'X-CSRF-Token' ;
}
