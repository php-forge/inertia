<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use Closure;

final readonly class OptionalProp implements PropValue
{
    /**
     * @param Closure(): mixed $value
     */
    public function __construct(private Closure $value) {}

    public function once(): OnceProp
    {
        return new OnceProp($this);
    }

    /**
     * @return Closure(): mixed
     */
    public function value(): Closure
    {
        return $this->value;
    }
}
