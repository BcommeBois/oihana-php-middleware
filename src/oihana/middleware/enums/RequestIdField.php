<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Default field names for the request-id helpers.
 *
 * Provides typed constants for the header name carrying the request ID
 * and the conventional PSR-7 request attribute used to propagate it
 * through the middleware chain.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class RequestIdField
{
    use ConstantsTrait ;

    /**
     * `X-Request-Id` — default request/response header name carrying the request ID.
     */
    public const string HEADER_NAME = 'X-Request-Id' ;

    /**
     * `requestId` — conventional PSR-7 request attribute name to propagate the request ID through the middleware chain.
     */
    public const string ATTRIBUTE_NAME = 'requestId' ;
}
