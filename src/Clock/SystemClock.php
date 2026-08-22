<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Clock;

use DateTimeImmutable;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
