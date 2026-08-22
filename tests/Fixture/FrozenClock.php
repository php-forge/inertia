<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Fixture;

use DateTimeImmutable;
use PHPForge\Inertia\Clock\Clock;

final readonly class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
