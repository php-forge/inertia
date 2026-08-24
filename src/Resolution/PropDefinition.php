<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Resolution;

use PHPForge\Inertia\Exception\{InvalidPropException, Message};
use PHPForge\Inertia\Prop\{AlwaysProp, DeferredProp, MergeProp, OnceProp, OptionalProp, PropValue, ScrollProp};

/**
 * Flattens a composable prop-wrapper chain into its base value and protocol semantics.
 */
final readonly class PropDefinition
{
    /**
     * @param mixed $base Unwrapped base value after stripping all prop modifier wrappers.
     * @param bool $always Whether the prop is always included in every response.
     * @param DeferredProp|null $deferred Deferred prop modifier, or `null` if not deferred.
     * @param bool $optional Whether the prop is excluded from the initial non-partial response.
     * @param MergeProp|null $merge Merge prop modifier, or `null` if not merging.
     * @param OnceProp|null $once Once prop modifier, or `null` if not cached.
     * @param ScrollProp|null $scroll Scroll prop modifier, or `null` if not an infinite-scroll prop.
     */
    private function __construct(
        public mixed $base,
        public bool $always,
        public DeferredProp|null $deferred,
        public bool $optional,
        public MergeProp|null $merge,
        public OnceProp|null $once,
        public ScrollProp|null $scroll,
    ) {}

    /**
     * Unwraps a composable prop-wrapper chain and returns a flattened definition.
     *
     * @param mixed $value Raw prop value, which may be a {@see PropValue} wrapper chain or a plain value.
     *
     * @throws InvalidPropException When the wrapper chain exceeds 64 levels.
     *
     * @return PropDefinition Flattened definition of the prop value and its semantics.
     */
    public static function from(mixed $value): PropDefinition
    {
        $always = false;
        $deferred = null;
        $optional = false;
        $merge = null;
        $once = null;
        $scroll = null;
        $depth = 0;

        while ($value instanceof PropValue) {
            if (++$depth > 64) {
                throw new InvalidPropException(
                    Message::PROP_WRAPPER_DEPTH_EXCEEDED->getMessage(),
                );
            }

            match (true) {
                $value instanceof AlwaysProp => $always = true,
                $value instanceof DeferredProp => $deferred = $value,
                $value instanceof OptionalProp => $optional = true,
                $value instanceof MergeProp => $merge = $value,
                $value instanceof OnceProp => $once = $value,
                $value instanceof ScrollProp => $scroll = $value,
                default => null,
            };

            $value = $value->value();
        }

        return new self(
            $value,
            $always,
            $deferred,
            $optional,
            $merge,
            $once,
            $scroll,
        );
    }

    /**
     * Returns a new definition that combines the semantics of this definition with `$other`.
     *
     * The other definition's concrete modifiers take precedence; boolean flags are OR-ed.
     *
     * @param PropDefinition $other Definition resolved from a callback's return value.
     *
     * @return PropDefinition New definition that merges the semantics of both definitions.
     */
    public function mergeWith(self $other): PropDefinition
    {
        return new self(
            $other->base,
            $this->always || $other->always,
            $other->deferred ?? $this->deferred,
            $this->optional || $other->optional,
            $other->merge ?? $this->merge,
            $other->once ?? $this->once,
            $other->scroll ?? $this->scroll,
        );
    }
}
