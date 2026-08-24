<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Support;

use PHPForge\Inertia\Exception\{InvalidPageInputException, Message};

use function array_key_exists;
use function array_pop;
use function array_reverse;
use function array_shift;
use function is_array;
use function str_contains;

/**
 * Expands dot-notated page keys into nested arrays while detecting conflicting paths.
 */
final class DotArray
{
    private function __construct() {}

    /**
     * Expands dot-notated keys in `$values` into a nested associative array.
     *
     * @param array<string, mixed> $values The flat, dot-notated key-value map to expand.
     *
     * @return array<string, mixed> The expanded nested associative array.
     */
    public static function expand(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (!str_contains($key, '.')) {
                $result[$key] = $value;

                continue;
            }

            $result = self::assign($result, explode('.', $key), $value, $key);
        }

        return $result;
    }

    /**
     * Assigns a value into `$values` at the path described by `$segments`, handling nested creation.
     *
     * @param array<string, mixed> $values The current result array to write into.
     * @param non-empty-list<string> $segments Remaining path segments to traverse.
     *
     * @return array<string, mixed> The updated result array with the value written at the given dot-notated path.
     */
    private static function assign(array $values, array $segments, mixed $value, string $path): array
    {
        $rootSegment = array_shift($segments);

        if ($segments === []) {
            $values[$rootSegment] = $value;

            return $values;
        }

        $child = $values[$rootSegment] ?? [];

        if (!is_array($child)) {
            throw new InvalidPageInputException(
                Message::PROP_PATH_CONFLICT->getMessage($path, $rootSegment),
            );
        }

        $values[$rootSegment] = self::assignNested($child, $segments, $value, $path);

        return $values;
    }

    /**
     * Assigns a value into a nested array at the path described by `$segments`, rebuilding parent nodes.
     *
     * @param array<array-key, mixed> $values The current nested array level.
     * @param non-empty-list<string> $segments Remaining path segments below the root.
     *
     * @return array<array-key, mixed> The updated nested array with the value inserted at the final segment.
     */
    private static function assignNested(array $values, array $segments, mixed $value, string $path): array
    {
        $lastSegment = array_pop($segments);

        $current = $values;
        $parents = [];

        foreach ($segments as $segment) {
            if (!array_key_exists($segment, $current)) {
                $child = [];
            } else {
                $child = $current[$segment];

                if (!is_array($child)) {
                    throw new InvalidPageInputException(
                        Message::PROP_PATH_CONFLICT->getMessage($path, $segment),
                    );
                }
            }

            $parents[] = [$current, $segment];
            $current = $child;
        }

        $current[$lastSegment] = $value;

        foreach (array_reverse($parents) as [$parent, $segment]) {
            $parent[$segment] = $current;
            $current = $parent;
        }

        return $current;
    }
}
