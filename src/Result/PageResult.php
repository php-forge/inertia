<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

use PHPForge\Inertia\Page;

interface PageResult extends ProtocolResult
{
    public function page(): Page;

    /**
     * @return list<RescuedPropFailure>
     */
    public function rescuedFailures(): array;
}
