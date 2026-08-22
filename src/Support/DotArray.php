<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Support;

use PHPForge\Inertia\Exception\{InvalidPageInputException, Message};

use function array_key_exists;
use function is_array;

final class DotArray
{
    private function __construct() {}

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
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
     * @param array<string, mixed> $values
     * @param non-empty-list<string> $segments
     *
     * @return array<string, mixed>
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
     * @param array<array-key, mixed> $values
     * @param non-empty-list<string> $segments
     *
     * @return array<array-key, mixed>
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
