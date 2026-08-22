<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

use Throwable;

final readonly class RescuedPropFailure
{
    public function __construct(
        public string $propPath,
        public Throwable $failure,
    ) {}
}
