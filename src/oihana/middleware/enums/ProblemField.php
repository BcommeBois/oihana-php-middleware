<?php

namespace oihana\middleware\enums ;

use oihana\reflect\traits\ConstantsTrait ;

/**
 * Standard field names of a Problem Details object as defined by
 * [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457.html) (formerly
 * RFC 7807).
 *
 * Used as keys when serialising a {@see \oihana\middleware\problem\Problem}
 * value object to its JSON representation, and exposed so consumers
 * parsing Problem Details responses (HTTP clients, error reporters)
 * can read the same constants without duplicating string literals.
 *
 * Field semantics — verbatim from the RFC :
 *
 * - `type` — URI reference identifying the problem type. Should resolve
 *   to a human-readable documentation page. Defaults to `'about:blank'`
 *   per the spec when omitted.
 * - `title` — short, human-readable summary of the problem type. SHOULD
 *   NOT change from occurrence to occurrence.
 * - `status` — HTTP status code of the response (RFC 9110). Convenience
 *   for consumers that don't want to read the response line.
 * - `detail` — human-readable explanation specific to this occurrence.
 * - `instance` — URI reference that identifies the specific occurrence.
 *
 * Extension fields (any other key) are application-specific and live
 * alongside the standard ones in the Problem object's `extensions`
 * bucket — see {@see \oihana\middleware\problem\Problem::$extensions}.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\enums
 */
class ProblemField
{
    use ConstantsTrait ;

    /**
     * `type` — URI reference identifying the problem type (RFC 9457 §3.1.1).
     */
    public const string TYPE = 'type' ;

    /**
     * `title` — short, human-readable summary of the problem type (RFC 9457 §3.1.2).
     */
    public const string TITLE = 'title' ;

    /**
     * `status` — HTTP status code originated by the origin server (RFC 9457 §3.1.3).
     */
    public const string STATUS = 'status' ;

    /**
     * `detail` — human-readable explanation specific to this occurrence (RFC 9457 §3.1.4).
     */
    public const string DETAIL = 'detail' ;

    /**
     * `instance` — URI reference that identifies the specific occurrence (RFC 9457 §3.1.5).
     */
    public const string INSTANCE = 'instance' ;
}
