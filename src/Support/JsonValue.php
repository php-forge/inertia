<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Support;

use JsonSerializable;
use PHPForge\Inertia\Exception\{InvalidPropException, Message};
use stdClass;

use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;

final class JsonValue
{
    private const int MAX_DEPTH = 512;

    private function __construct() {}

    public static function normalize(mixed $value, string $path, int $depth = 0): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidPropException(
                Message::JSON_DEPTH_EXCEEDED->getMessage($path),
            );
        }

        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new InvalidPropException(
                    Message::JSON_NON_FINITE_NUMBER->getMessage($path),
                );
            }

            return $value;
        }

        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new InvalidPropException(
                    Message::JSON_UTF8_INVALID->getMessage($path),
                );
            }

            return $value;
        }

        if ($value instanceof JsonSerializable) {
            return self::normalize($value->jsonSerialize(), $path, $depth + 1);
        }

        if ($value instanceof stdClass) {
            return self::normalize(get_object_vars($value), $path, $depth + 1);
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $itemPath = "{$path}.{$key}";

                $normalized[$key] = self::normalize($item, $itemPath, $depth + 1);
            }

            return $normalized;
        }

        throw new InvalidPropException(
            Message::JSON_VALUE_INVALID->getMessage($path),
        );
    }
}
