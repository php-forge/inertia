<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Provider;

/**
 * Data provider for {@see \PHPForge\Inertia\Tests\SupportTest} test cases.
 */
final class SupportProvider
{
    /**
     * @return array<string, array{'array'|'json-serializable'|'object', positive-int}>
     */
    public static function containerKinds(): array
    {
        return [
            'array nesting' => ['array', 512],
            'JSON-serializable nesting' => ['json-serializable', 512],
            'object nesting' => ['object', 256],
        ];
    }
}
