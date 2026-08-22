<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Clock;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
