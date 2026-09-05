<?php

declare(strict_types=1);

namespace PHPForge\Inertia;

use Closure;

/**
 * Forwards a resolved page payload and its shared-prop keys to an application callback.
 *
 * Framework adapters may implement their observer contracts by extending this class.
 */
readonly class ResolvedPageObserver
{
    /**
     * @param Closure(array<string, mixed>, list<string>): void $callback Receives the unmodified page diagnostics.
     */
    public function __construct(private Closure $callback) {}

    public function observe(Page $page): void
    {
        ($this->callback)($page->toArray(), $page->sharedProps());
    }
}
