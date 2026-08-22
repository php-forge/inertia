<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

final readonly class AlwaysProp implements PropValue
{
    public function __construct(private mixed $value) {}

    public function value(): mixed
    {
        return $this->value;
    }
}
