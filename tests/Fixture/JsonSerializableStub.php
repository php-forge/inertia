<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Fixture;

use JsonSerializable;

final readonly class JsonSerializableStub implements JsonSerializable
{
    public function __construct(private mixed $value) {}

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }
}
