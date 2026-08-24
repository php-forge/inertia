<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

/**
 * Defines a composable Inertia prop wrapper and exposes its wrapped value.
 */
interface PropValue
{
    /**
     * Returns the wrapped value or callback.
     *
     * @return mixed The wrapped value or callback.
     */
    public function value(): mixed;
}
