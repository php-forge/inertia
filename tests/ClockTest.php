<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests;

use DateTimeImmutable;
use PHPForge\Inertia\Clock\SystemClock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the default {@see SystemClock} implementation.
 */
#[Group('clock')]
final class ClockTest extends TestCase
{
    public function testReturnsCurrentImmutableTime(): void
    {
        $before = new DateTimeImmutable();
        $now = (new SystemClock())->now();
        $after = new DateTimeImmutable();

        self::assertGreaterThanOrEqual(
            $before,
            $now,
            'Current time must not precede the lower bound.',
        );
        self::assertLessThanOrEqual(
            $after,
            $now,
            'Current time must not exceed the upper bound.',
        );
    }
}
