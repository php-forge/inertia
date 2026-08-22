<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Resolution;

final readonly class ResolvedProp
{
    private function __construct(
        public bool $included,
        public mixed $value = null,
    ) {}

    public static function include(mixed $value): self
    {
        return new self(
            true,
            $value,
        );
    }

    public static function omit(): self
    {
        return new self(
            false,
        );
    }
}
