<?php

declare(strict_types=1);

namespace PHPForge\Inertia;

/**
 * Defines every HTTP header name the Inertia protocol reads from a request or emits on a response.
 */
enum Header: string
{
    /**
     * Error bag nesting the validation errors of the response.
     */
    case ERROR_BAG = 'X-Inertia-Error-Bag';

    /**
     * Once-prop cache keys the client already holds and wants skipped.
     */
    case EXCEPT_ONCE_PROPS = 'X-Inertia-Except-Once-Props';

    /**
     * Marks an Inertia request, and the JSON page response answering it.
     */
    case INERTIA = 'X-Inertia';

    /**
     * Merge direction requested for an infinite-scroll prop.
     */
    case INFINITE_SCROLL_MERGE_INTENT = 'X-Inertia-Infinite-Scroll-Merge-Intent';

    /**
     * Absolute URL of an external location visit.
     */
    case LOCATION = 'X-Inertia-Location';

    /**
     * Component a partial reload targets.
     */
    case PARTIAL_COMPONENT = 'X-Inertia-Partial-Component';

    /**
     * Prop paths a partial reload includes.
     */
    case PARTIAL_DATA = 'X-Inertia-Partial-Data';

    /**
     * Prop paths a partial reload excludes.
     */
    case PARTIAL_EXCEPT = 'X-Inertia-Partial-Except';

    /**
     * Standard request purpose, carrying `prefetch` for speculative visits.
     */
    case PURPOSE = 'Purpose';

    /**
     * Target URL of a redirect that preserves a URL fragment.
     */
    case REDIRECT = 'X-Inertia-Redirect';

    /**
     * Prop paths whose merge metadata the client wants reset.
     */
    case RESET = 'X-Inertia-Reset';

    /**
     * Standard cache-variance header, always naming {@see self::INERTIA}.
     */
    case VARY = 'Vary';

    /**
     * Client asset version negotiated against the server version.
     */
    case VERSION = 'X-Inertia-Version';
}
