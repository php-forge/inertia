<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Resolution;

use PHPForge\Inertia\Result\RescuedPropFailure;

final readonly class ResolvedPageData
{
    /**
     * @param array<string, mixed> $props
     * @param list<string> $mergeProps
     * @param list<string> $prependProps
     * @param list<string> $deepMergeProps
     * @param list<string>  $matchPropsOn
     * @param array<
     *   string,
     *   array{
     *     pageName: string,
     *     previousPage: int|string|null,
     *     nextPage: int|string|null,
     *     currentPage: int|string|null,
     *     reset: bool
     *   }
     * > $scrollProps
     * @param array<string, list<string>> $deferredProps
     * @param list<string> $rescuedProps
     * @param list<string> $sharedProps
     * @param array<string, array{prop: string, expiresAt: int|null}> $onceProps
     * @param list<RescuedPropFailure> $rescuedFailures
     * @param array<string, mixed> $flash
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
