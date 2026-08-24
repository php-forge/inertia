<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Clock;

use DateTimeImmutable;

/**
 * Supplies the current time used to calculate expiring Inertia prop metadata.
 */
interface Clock
{
    /**
     * Returns the current date and time.
     *
     * @return DateTimeImmutable The current date and time.
     */
    public function now(): DateTimeImmutable;
}
