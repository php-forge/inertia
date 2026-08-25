<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Resolution;

use PHPForge\Inertia\PageMetadata;
use PHPForge\Inertia\Result\RescuedPropFailure;

/**
 * Carries resolved props and protocol metadata from prop resolution to page construction.
 */
final readonly class ResolvedPageData
{
    /**
     * @param array<string, mixed> $props Resolved page props passed to the front-end component.
     * @param PageMetadata $metadata Client-side merge, loading, recovery, sharing, and caching metadata.
     * @param list<RescuedPropFailure> $rescuedFailures Prop failures captured during resolution for adapter reporting.
     * @param array<string, mixed> $flash Flash data passed alongside the page response.
     */
    public function __construct(
        public array $props,
        public PageMetadata $metadata,
        public array $rescuedFailures,
        public array $flash,
    ) {}
}
