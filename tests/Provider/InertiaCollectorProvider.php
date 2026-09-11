<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Provider;

/**
 * Data provider for {@see \PHPForge\Inertia\Tests\Debug\InertiaCollectorTest} test cases.
 */
final class InertiaCollectorProvider
{
    /**
     * @return array<string, array{string, bool, string, int, string|null, string}>
     */
    public static function protocolOperations(): array
    {
        return [
            'external location' => ['location', true, 'v1', 409, 'https://example.com/target', 'location'],
            'fragment visit' => ['fragment', true, 'v1', 409, 'https://example.com/target#section', 'fragment-redirect'],
            'inertia mutation redirect' => ['redirect', true, 'v1', 303, '/target', 'redirect'],
            'inertia page' => ['page', true, 'v1', 200, null, 'page'],
            'initial page' => ['page', false, 'v1', 200, null, 'page'],
            'ordinary location' => ['location', false, 'v1', 302, 'https://example.com/target', 'redirect'],
            'ordinary redirect' => ['redirect', false, 'v1', 302, '/target', 'redirect'],
            'version mismatch' => ['page', true, 'old', 409, 'https://example.com/page', 'version-conflict'],
        ];
    }
}
