<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use Closure;

final class Prop
{
    private function __construct() {}

    public static function always(mixed $value): AlwaysProp
    {
        return new AlwaysProp($value);
    }

    /**
     * @param Closure(): mixed $callback
     */
    public static function defer(
        Closure $callback,
        string $group = 'default',
        bool $rescue = false,
    ): DeferredProp {
        return new DeferredProp($callback, $group, $rescue);
    }

    public static function merge(mixed $value): MergeProp
    {
        return new MergeProp($value);
    }

    /**
     * @param Closure(): mixed $callback
     */
    public static function once(Closure $callback): OnceProp
    {
        return new OnceProp($callback);
    }

    /**
     * @param Closure(): mixed $callback
     */
    public static function optional(Closure $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    /**
     * @param ScrollMetadata|(Closure(mixed): mixed) $metadata
     */
    public static function scroll(
        mixed $value,
        ScrollMetadata|Closure $metadata,
        string $wrapper = 'data',
    ): ScrollProp {
        return new ScrollProp($value, $metadata, $wrapper);
    }
}
