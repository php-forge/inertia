<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Resolution;

/**
 * Represents whether a single resolved prop is included and, when included, its value.
 */
final readonly class ResolvedProp
{
    /**
     * @param bool  $included Whether this prop is included in the response.
     * @param mixed $value The resolved value, or `null` when `$included` is `false`.
     */
    private function __construct(public bool $included, public mixed $value = null) {}

    /**
     * Returns an included resolved prop carrying the given value.
     *
     * @param mixed $value The normalized prop value to include in the response.
     *
     * @return ResolvedProp An included resolved prop carrying the given value.
     */
    public static function include(mixed $value): self
    {
        return new self(
            true,
            $value,
        );
    }

    /**
     * Returns an omitted resolved prop that is excluded from the response.
     *
     * @return ResolvedProp An omitted resolved prop excluded from the response.
     */
    public static function omit(): self
    {
        return new self(
            false,
        );
    }
}
