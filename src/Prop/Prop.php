<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use Closure;

/**
 * Creates framework-neutral, composable Inertia prop wrappers.
 */
final class Prop
{
    /**
     * Prevents direct instantiation.
     */
    private function __construct() {}

    /**
     * Returns a new {@see AlwaysProp} that always includes its value in every response.
     *
     * @param mixed $value The value to include in every response.
     *
     * @return AlwaysProp A new {@see AlwaysProp} that always includes its value in every response.
     */
    public static function always(mixed $value): AlwaysProp
    {
        return new AlwaysProp($value);
    }

    /**
     * Returns a new {@see DeferredProp} wrapping the callback under the given group.
     *
     * @param Closure(): mixed $callback The callback to evaluate when the client requests this deferred group.
     *
     * @return DeferredProp A new {@see DeferredProp} wrapping the callback under the given group.
     */
    public static function defer(Closure $callback, string $group = 'default', bool $rescue = false): DeferredProp
    {
        return new DeferredProp($callback, $group, $rescue);
    }

    /**
     * Returns a new {@see MergeProp} that merges its value into the client's existing prop state.
     *
     * @param mixed $value The value to merge into the client's existing prop state.
     *
     * @return MergeProp A new {@see MergeProp} that merges its value into the client's existing prop state.
     */
    public static function merge(mixed $value): MergeProp
    {
        return new MergeProp($value);
    }

    /**
     * Returns a new {@see OnceProp} wrapping the callback.
     *
     * @param Closure(): mixed $callback The callback to evaluate once and cache on the client.
     *
     * @return OnceProp A new {@see OnceProp} wrapping the callback.
     */
    public static function once(Closure $callback): OnceProp
    {
        return new OnceProp($callback);
    }

    /**
     * Returns a new {@see OptionalProp} wrapping the callback.
     *
     * @param Closure(): mixed $callback The callback to evaluate only when the prop is explicitly requested.
     *
     * @return OptionalProp A new {@see OptionalProp} wrapping the callback.
     */
    public static function optional(Closure $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    /**
     * Returns a new {@see ScrollProp} wrapping the value with the given scroll metadata.
     *
     * @param (Closure(mixed): mixed)|ScrollMetadata $metadata Static metadata or a closure receiving the resolved
     * value.
     *
     * @return ScrollProp A new {@see ScrollProp} wrapping the value with the given scroll metadata.
     */
    public static function scroll(mixed $value, ScrollMetadata|Closure $metadata, string $wrapper = 'data'): ScrollProp
    {
        return new ScrollProp($value, $metadata, $wrapper);
    }
}
