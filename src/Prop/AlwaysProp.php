<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

/**
 * Wraps a value that is always included in every response, including partial reloads.
 */
final readonly class AlwaysProp implements PropValue
{
    /**
     * @param mixed $value The value to wrap.
     */
    public function __construct(private mixed $value) {}

    /**
     * Returns the wrapped value.
     *
     * @return mixed The wrapped value.
     */
    public function value(): mixed
    {
        return $this->value;
    }
}
