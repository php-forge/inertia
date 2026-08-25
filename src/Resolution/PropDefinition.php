<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Resolution;

use PHPForge\Inertia\Exception\{InvalidPropException, Message};
use PHPForge\Inertia\Prop\{AlwaysProp, DeferredProp, MergeProp, OnceProp, OptionalProp, PropValue, ScrollProp};

/**
 * Flattens a composable prop-wrapper chain into its base value and protocol semantics.
 */
final class PropDefinition
{
    /**
     * Whether the prop is always included in every response.
     */
    private bool $always = false;

    /**
     * Deferred prop modifier, or `null` if not deferred.
     */
    private DeferredProp|null $deferred = null;

    /**
     * Merge prop modifier, or `null` if not merging.
     */
    private MergeProp|null $merge = null;

    /**
     * Once prop modifier, or `null` if not cached.
     */
    private OnceProp|null $once = null;

    /**
     * Whether the prop is excluded from the initial non-partial response.
     */
    private bool $optional = false;

    /**
     * Scroll prop modifier, or `null` if not an infinite-scroll prop.
     */
    private ScrollProp|null $scroll = null;

    /**
     * @param mixed $base Unwrapped base value after stripping all prop modifier wrappers.
     */
    private function __construct(public readonly mixed $base) {}

    /**
     * Returns whether the prop is always included in every response.
     *
     * @return bool Whether the prop is always included in every response.
     */
    public function always(): bool
    {
        return $this->always;
    }

    /**
     * Returns the deferred prop modifier.
     *
     * @return DeferredProp|null Deferred prop modifier, or `null` if not deferred.
     */
    public function deferred(): DeferredProp|null
    {
        return $this->deferred;
    }

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

        $definition = new self($value);
        $definition->always = $always;
        $definition->deferred = $deferred;
        $definition->merge = $merge;
        $definition->once = $once;
        $definition->optional = $optional;
        $definition->scroll = $scroll;

        return $definition;
    }

    /**
     * Returns the merge prop modifier.
     *
     * @return MergeProp|null Merge prop modifier, or `null` if not merging.
     */
    public function merge(): MergeProp|null
    {
        return $this->merge;
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
        $definition = clone $other;
        $definition->always = $this->always || $other->always;
        $definition->deferred = $other->deferred ?? $this->deferred;
        $definition->merge = $other->merge ?? $this->merge;
        $definition->once = $other->once ?? $this->once;
        $definition->optional = $this->optional || $other->optional;
        $definition->scroll = $other->scroll ?? $this->scroll;

        return $definition;
    }

    /**
     * Returns the once prop modifier.
     *
     * @return OnceProp|null Once prop modifier, or `null` if not cached.
     */
    public function once(): OnceProp|null
    {
        return $this->once;
    }

    /**
     * Returns whether the prop is excluded from the initial non-partial response.
     *
     * @return bool Whether the prop is excluded from the initial non-partial response.
     */
    public function optional(): bool
    {
        return $this->optional;
    }

    /**
     * Returns the infinite-scroll prop modifier.
     *
     * @return ScrollProp|null Scroll prop modifier, or `null` if not an infinite-scroll prop.
     */
    public function scroll(): ScrollProp|null
    {
        return $this->scroll;
    }
}
