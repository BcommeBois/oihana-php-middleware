<?php

declare( strict_types = 1 );

namespace oihana\middleware\pagination ;

/**
 * Immutable value object carrying the four standard pagination URIs
 * and an optional total count.
 *
 * Consumed by
 * {@see \oihana\middleware\helpers\pagination\withPaginationHeaders()}
 * which serialises it to an RFC 5988 / RFC 8288 `Link` response
 * header (`<uri>; rel="next"`, etc.) and an `X-Total-Count` header.
 *
 * Every property is optional — `null` fields are omitted from the
 * emitted headers. Pagination state with no relevant links (the
 * single-page case) produces an empty `Link` header and the helper
 * skips it.
 *
 * The four URIs follow the GitHub-style convention :
 *
 * - `$first` — page 1 of the result set.
 * - `$prev` — the page immediately before the current one. `null` on
 *   the first page.
 * - `$next` — the page immediately after the current one. `null` on
 *   the last page.
 * - `$last` — last page of the result set. `null` when the total is
 *   unknown (cursor-style pagination, infinite-scroll feeds).
 *
 * The helper is **format-agnostic** : it does NOT construct the
 * URIs for you. The caller already knows how to build them
 * (`?page=N`, `?offset=N`, `?cursor=...`, etc.) — the helper just
 * stamps them.
 *
 * @author  Marc Alcaraz
 * @package oihana\middleware\pagination
 */
final readonly class PaginationLinks
{
    public function __construct
    (
        public ?string $first      = null ,
        public ?string $prev       = null ,
        public ?string $next       = null ,
        public ?string $last       = null ,
        public ?int    $totalCount = null ,
    ) {}
}
