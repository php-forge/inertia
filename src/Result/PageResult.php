<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

use PHPForge\Inertia\Page;

/**
 * Defines a protocol result containing a resolved Inertia page and any rescued prop failures.
 */
interface PageResult extends ProtocolResult
{
    /**
     * Returns the resolved Inertia page payload.
     *
     * @return Page The resolved Inertia page.
     */
    public function page(): Page;

    /**
     * Returns prop failures rescued during page resolution.
     *
     * @return list<RescuedPropFailure> Rescued prop failures, one per failed-and-rescued callback.
     */
    public function rescuedFailures(): array;
}
