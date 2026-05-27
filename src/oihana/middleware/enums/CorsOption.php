<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Option keys accepted by {@see \oihana\middleware\helpers\cors\applyCorsHeaders()}.
 *
 * Exposed as typed constants so consumers never need to spell the option
 * strings by hand — matching the "zero magic strings" convention of the
 * `oihana/php-*` ecosystem.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class CorsOption
{
    use ConstantsTrait ;

    /**
     * `allowedOrigins` — `list<string>` of explicit origins, or the wildcard string `'*'`. When omitted, the helper leaves the response untouched (no CORS headers emitted).
     */
    public const string ALLOWED_ORIGINS = 'allowedOrigins' ;

    /**
     * `allowedMethods` — `list<string>` of HTTP methods allowed in the preflight response (e.g. `['GET', 'POST', 'DELETE']`). Only emitted on the preflight (`OPTIONS` + `Access-Control-Request-Method`).
     */
    public const string ALLOWED_METHODS = 'allowedMethods' ;

    /**
     * `allowedHeaders` — `list<string>` of request headers allowed in the preflight. When omitted, the helper echoes back the contents of `Access-Control-Request-Headers` (if any).
     */
    public const string ALLOWED_HEADERS = 'allowedHeaders' ;

    /**
     * `exposedHeaders` — `list<string>` of response headers exposed to the browser JS via `Access-Control-Expose-Headers`. Empty / omitted ⇒ no header.
     */
    public const string EXPOSED_HEADERS = 'exposedHeaders' ;

    /**
     * `allowCredentials` — when `true`, emits `Access-Control-Allow-Credentials: true`. Incompatible with `allowedOrigins: '*'` (the helper throws). `bool`, default `false`.
     */
    public const string ALLOW_CREDENTIALS = 'allowCredentials' ;

    /**
     * `maxAge` — preflight cache lifetime in seconds (`int`). Only emitted on the preflight.
     */
    public const string MAX_AGE = 'maxAge' ;
}
