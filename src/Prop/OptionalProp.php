<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use Closure;

/**
 * Wraps a callback that resolves only when a matching partial reload explicitly requests the prop.
 */
final readonly class OptionalProp implements PropValue
{
    /**
     * @param Closure(): mixed $value The callback resolved only when the prop is explicitly requested.
     */
    public function __construct(private Closure $value) {}

    /**
     * Returns a new {@see OnceProp} wrapping this optional callback.
     *
     * @return OnceProp A new {@see OnceProp} wrapping this optional callback.
     */
    public function once(): OnceProp
    {
        return new OnceProp($this);
    }

    /**
     * Returns the wrapped callback.
     *
     * @return Closure(): mixed The wrapped callback.
     */
    public function value(): Closure
    {
        return $this->value;
    }
}
