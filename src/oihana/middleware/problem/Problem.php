<?php

declare( strict_types = 1 );

namespace oihana\middleware\problem ;

use oihana\middleware\enums\ProblemField ;

/**
 * Immutable value object describing a Problem Details response payload
 * per [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457.html) (formerly
 * RFC 7807).
 *
 * Produced by application code, consumed by
 * {@see \oihana\middleware\helpers\problem\respondProblemDetails()}
 * which serialises it to `application/problem+json`.
 *
 * Semantics — every standard field is **optional** :
 *
 * - `$type` — URI reference identifying the problem type. Conceptually
 *   the "error code" of the response. Should resolve to a human-readable
 *   documentation page. When `null`, the field is omitted from the
 *   serialised JSON (the RFC's `'about:blank'` default is left implicit
 *   on the consumer side).
 * - `$title` — short summary of the problem type ("Validation failed",
 *   "Out of credit", "Account suspended"). Should NOT change from
 *   occurrence to occurrence — `$detail` is the per-occurrence variant.
 * - `$status` — HTTP status code (`400`, `403`, `409`, etc.).
 *   Convenience for consumers that don't want to read the response
 *   status line.
 * - `$detail` — per-occurrence explanation: variable values, field
 *   names, identifiers, etc.
 * - `$instance` — URI reference identifying the specific occurrence
 *   (useful when paired with a logged correlation id, an issue tracker
 *   reference, etc.).
 * - `$extensions` — bag of application-specific keys/values, merged at
 *   the top level of the JSON alongside the standard fields. Names
 *   colliding with a standard field name are not allowed (per RFC §3.2)
 *   and are silently dropped during serialisation — the standard field
 *   wins.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\problem
 */
final readonly class Problem
{
    public function __construct
    (
        public ?string $type       = null ,
        public ?string $title      = null ,
        public ?int    $status     = null ,
        public ?string $detail     = null ,
        public ?string $instance   = null ,
        public ?array  $extensions = null ,
    ) {}

    /**
     * Serialises the Problem to a JSON-ready associative array.
     *
     * Output ordering follows the RFC 9457 canonical sequence : `type`,
     * `title`, `status`, `detail`, `instance`, then any extension
     * entries. Standard fields whose value is `null` are omitted (the
     * JSON simply does not carry the key) — callers wanting to expose
     * `'about:blank'` as the type should pass it explicitly.
     *
     * Extension entries whose key collides with a standard field name
     * are dropped per RFC 9457 §3.2.
     *
     * @return array<string, mixed> JSON-ready map.
     */
    public function toArray() : array
    {
        $out = [] ;

        if ( $this->type !== null )
        {
            $out[ ProblemField::TYPE ] = $this->type ;
        }

        if ( $this->title !== null )
        {
            $out[ ProblemField::TITLE ] = $this->title ;
        }

        if ( $this->status !== null )
        {
            $out[ ProblemField::STATUS ] = $this->status ;
        }

        if ( $this->detail !== null )
        {
            $out[ ProblemField::DETAIL ] = $this->detail ;
        }

        if ( $this->instance !== null )
        {
            $out[ ProblemField::INSTANCE ] = $this->instance ;
        }

        if ( $this->extensions !== null && $this->extensions !== [] )
        {
            $standard =
            [
                ProblemField::TYPE     => true ,
                ProblemField::TITLE    => true ,
                ProblemField::STATUS   => true ,
                ProblemField::DETAIL   => true ,
                ProblemField::INSTANCE => true ,
            ] ;

            foreach ( $this->extensions as $key => $value )
            {
                // RFC 9457 §3.2 : extensions MUST NOT shadow standard fields.
                // Defensive — silently drop colliding keys rather than letting
                // an extension overwrite a standard value via array merge.
                if ( !isset( $standard[ $key ] ) )
                {
                    $out[ $key ] = $value ;
                }
            }
        }

        return $out ;
    }
}
