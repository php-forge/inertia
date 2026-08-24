<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Clock;

use DateTimeImmutable;

/**
 * Reads the current time from the system clock.
 */
final class SystemClock implements Clock
{
    /**
     * Returns the current date and time from the system clock.
     *
     * @return DateTimeImmutable The current date and time.
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
