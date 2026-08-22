<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Provider;

use PHPForge\Inertia\Exception\Message;

/**
 * Data provider for {@see \PHPForge\Inertia\Tests\ProtocolResultTest} test cases.
 */
final class ProtocolResultProvider
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function inertiaMutationMethods(): array
    {
        return [
            'DELETE redirect' => ['DELETE', 'A DELETE redirect must use status 303.'],
            'PATCH redirect' => ['PATCH', 'A PATCH redirect must use status 303.'],
            'PUT redirect' => ['PUT', 'A PUT redirect must use status 303.'],
        ];
    }

    /**
     * @return array<string, array{'location'|'redirect', string, int, Message}>
     */
    public static function invalidResponses(): array
    {
        return [
            'external location with credentials' => [
                'location',
                'https://user:secret@example.com/users',
                302,
                Message::LOCATION_URL_INVALID,
            ],
            'external location with uppercase scheme' => [
                'location',
                'FTP://example.com/users',
                302,
                Message::LOCATION_URL_INVALID,
            ],
            'empty external location' => [
                'location',
                '',
                302,
                Message::LOCATION_URL_INVALID,
            ],
            'external location containing whitespace' => [
                'location',
                'https://example.com/user profile',
                302,
                Message::LOCATION_URL_INVALID,
            ],
            'relative redirect without root' => [
                'redirect',
                'users',
                302,
                Message::REDIRECT_URL_INVALID,
            ],
            'unsupported redirect status' => [
                'redirect',
                '/users',
                200,
                Message::REDIRECT_STATUS_INVALID,
            ],
        ];
    }

    /**
     * @return array<string, array{int}>
     */
    public static function redirectStatuses(): array
    {
        return [
            'permanent redirect' => [301],
            'found redirect' => [302],
            'see-other redirect' => [303],
            'temporary redirect' => [307],
            'permanent method-preserving redirect' => [308],
        ];
    }

    /**
     * @return array<string, array{string, array<string, string>, int}>
     */
    public static function redirectsWithoutSeeOtherConversion(): array
    {
        return [
            'mutation outside Inertia' => ['PUT', [], 302],
            'Inertia read' => ['GET', ['X-Inertia' => 'true'], 302],
            'Inertia mutation with non-found status' => ['PUT', ['X-Inertia' => 'true'], 301],
        ];
    }
}
