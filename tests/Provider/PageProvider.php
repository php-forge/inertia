<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Provider;

use Closure;
use PHPForge\Inertia\Exception\Message;
use PHPForge\Inertia\PageInput;

/**
 * Data provider for {@see \PHPForge\Inertia\Tests\PageTest} test cases.
 */
final class PageProvider
{
    /**
     * @return array<string, array{Closure(): void, Message, list<int|string>}>
     */
    public static function invalidAuxiliaryInputs(): array
    {
        return [
            'invalid shared prop key' => [
                static function (): void {
                    new PageInput(
                        'Dashboard',
                        [],
                        'v1',
                        sharedProps: ['shared..value' => true],
                    );
                },
                Message::PAGE_KEY_INVALID,
                ['shared props'],
            ],
            'invalid flash key' => [
                static function (): void {
                    new PageInput(
                        'Dashboard',
                        [],
                        'v1',
                        flash: ['message,level' => 'success'],
                    );
                },
                Message::PAGE_KEY_INVALID,
                ['flash data'],
            ],
            'invalid error field' => [
                static function (): void {
                    new PageInput('Dashboard', [], 'v1', errors: ['' => 'Invalid']);
                },
                Message::PAGE_KEY_INVALID,
                ['error fields'],
            ],
            'invalid error after string error' => [
                static function (): void {
                    new PageInput(
                        'Dashboard',
                        [],
                        'v1',
                        errors: ['name' => 'Invalid', 'email' => ['first' => 'Invalid']],
                    );
                },
                Message::ERROR_FIELD_INVALID,
                [],
            ],
        ];
    }

    /**
     * @return array<
     *   string,
     *   array{string, array<string, mixed>, array<array-key, mixed>, Message, list<int|string>}
     * >
     */
    public static function invalidInputs(): array
    {
        return [
            'empty component' => [
                ' ',
                [],
                [],
                Message::COMPONENT_NAME_INVALID,
                [],
            ],
            'component with control character' => [
                "Dash\0board",
                [],
                [],
                Message::COMPONENT_NAME_INVALID,
                [],
            ],
            'empty prop key' => [
                'Dashboard',
                ['' => 'Ada'],
                [],
                Message::PAGE_KEY_INVALID,
                ['page props'],
            ],
            'malformed dot notation key' => [
                'Dashboard',
                ['user..name' => 'Ada'],
                [],
                Message::PAGE_KEY_INVALID,
                ['page props'],
            ],
            'non-list error messages' => [
                'Dashboard',
                [],
                ['email' => ['first' => 'Invalid']],
                Message::ERROR_FIELD_INVALID,
                [],
            ],
            'non-string error message' => [
                'Dashboard',
                [],
                ['email' => ['Invalid', 42]],
                Message::VALIDATION_ERROR_MESSAGE_INVALID,
                [],
            ],
            'reserved errors prop' => [
                'Dashboard',
                ['errors' => []],
                [],
                Message::RESERVED_ERRORS_PROP,
                [],
            ],
        ];
    }
}
