<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Resolution;

use PHPForge\Inertia\Result\RescuedPropFailure;

/**
 * Carries resolved props and protocol metadata from prop resolution to page construction.
 */
final readonly class ResolvedPageData
{
    /**
     * @param array<string, mixed> $props Resolved page props passed to the front-end component.
     * @param list<string> $mergeProps Prop paths the client should merge into its existing state.
     * @param list<string> $prependProps Prop paths the client should prepend to its existing state.
     * @param list<string> $deepMergeProps Prop paths the client should deep-merge recursively.
     * @param list<string> $matchPropsOn Prop paths used as match keys during client-side merging.
     * @param array<string, list<string>> $deferredProps Groups of prop paths loaded lazily, keyed by group name.
     * @param list<string> $rescuedProps Prop paths whose callbacks failed but were rescued by a deferred loader.
     * @param list<string> $sharedProps Top-level keys sourced from shared props, exposed for client awareness.
     * @param array<string, array{prop: string, expiresAt: int|null}> $onceProps Once-prop cache metadata keyed by cache
     * key.
     * @param list<RescuedPropFailure> $rescuedFailures Prop failures captured during resolution for adapter reporting.
     * @param array<string, mixed> $flash Flash data passed alongside the page response.
     * @param array<
     *   string,
     *   array{
     *     pageName: string,
     *     previousPage: int|string|null,
     *     nextPage: int|string|null,
     *     currentPage: int|string|null,
     *     reset: bool
     *   }
     * > $scrollProps Per-prop infinite-scroll pagination metadata keyed by prop path.
     */
    public function __construct(
        public array $props,
        public array $mergeProps,
        public array $prependProps,
        public array $deepMergeProps,
        public array $matchPropsOn,
        public array $scrollProps,
        public array $deferredProps,
        public array $rescuedProps,
        public array $sharedProps,
        public array $onceProps,
        public array $rescuedFailures,
        public array $flash,
    ) {}
}
