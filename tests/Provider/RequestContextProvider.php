<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Provider;

use PHPForge\Inertia\Exception\Message;

/**
 * Data provider for {@see \PHPForge\Inertia\Tests\RequestContextTest} test cases.
 */
final class RequestContextProvider
{
    /**
     * @return array<string, array{string, string, string, array<array-key, mixed>, Message}>
     */
    public static function invalidContexts(): array
    {
        return [
            'absolute URL with credentials' => [
                'GET',
                '/users',
                'https://user:secret@example.com/users',
                [],
                Message::ABSOLUTE_REQUEST_URL_INVALID,
            ],
            'absolute URL with user only' => [
                'GET',
                '/users',
                'https://user@example.com/users',
                [],
                Message::ABSOLUTE_REQUEST_URL_INVALID,
            ],
            'absolute URL with fragment' => [
                'GET',
                '/users',
                'https://example.com/users#profile',
                [],
                Message::ABSOLUTE_REQUEST_URL_INVALID,
            ],
            'absolute URL with unsupported scheme' => [
                'GET',
                '/users',
                'ftp://example.com/users',
                [],
                Message::ABSOLUTE_REQUEST_URL_INVALID,
            ],
            'empty absolute URL' => [
                'GET',
                '/users',
                '',
                [],
                Message::ABSOLUTE_REQUEST_URL_INVALID,
            ],
            'absolute URL containing whitespace' => [
                'GET',
                '/users',
                'https://example.com/user profile',
                [],
                Message::ABSOLUTE_REQUEST_URL_INVALID,
            ],
            'header injection' => [
                'GET',
                '/users',
                'https://example.com/users',
                ['X-Inertia-Version' => "version\r\nInjected: value"],
                Message::REQUEST_HEADER_LINE_BREAK_INVALID,
            ],
            'malformed method' => [
                'GET /',
                '/users',
                'https://example.com/users',
                [],
                Message::REQUEST_METHOD_INVALID,
            ],
            'non-string protocol header' => [
                'GET',
                '/users',
                'https://example.com/users',
                ['X-Inertia' => true],
                Message::REQUEST_HEADERS_INVALID,
            ],
            'protocol-relative request URL' => [
                'GET',
                '//attacker.example/path',
                'https://example.com/path',
                [],
                Message::REQUEST_URL_INVALID,
            ],
            'empty request URL' => [
                'GET',
                '',
                'https://example.com/users',
                [],
                Message::REQUEST_URL_INVALID,
            ],
            'non-rooted request URL' => [
                'GET',
                'users',
                'https://example.com/users',
                [],
                Message::REQUEST_URL_INVALID,
            ],
            'request URL with fragment' => [
                'GET',
                '/users#profile',
                'https://example.com/users',
                [],
                Message::REQUEST_URL_INVALID,
            ],
            'request URL containing whitespace' => [
                'GET',
                '/user profile',
                'https://example.com/users',
                [],
                Message::REQUEST_URL_INVALID,
            ],
        ];
    }
}
