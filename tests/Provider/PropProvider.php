<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Provider;

use Closure;
use PHPForge\Inertia\Exception\Message;
use PHPForge\Inertia\Prop\{OnceProp, Prop, ScrollMetadata};

/**
 * Data provider for {@see \PHPForge\Inertia\Tests\PropTest} test cases.
 */
final class PropProvider
{
    /**
     * @return array<string, array{Closure(): void, Message}>
     */
    public static function invalidDefinitions(): array
    {
        return [
            'empty deferred group' => [
                static function (): void {
                    Prop::defer(static fn(): null => null, '');
                },
                Message::DEFERRED_PROP_GROUP_INVALID,
            ],
            'malformed merge path' => [
                static function (): void {
                    Prop::merge([])->append('data..items');
                },
                Message::MERGE_PATH_INVALID,
            ],
            'malformed trailing append path' => [
                static function (): void {
                    Prop::merge([])->append('data.');
                },
                Message::MERGE_PATH_INVALID,
            ],
            'malformed append match path' => [
                static function (): void {
                    Prop::merge([])->append('data', 'items..id');
                },
                Message::MERGE_PATH_INVALID,
            ],
            'malformed prepend path' => [
                static function (): void {
                    Prop::merge([])->prepend('data..items');
                },
                Message::MERGE_PATH_INVALID,
            ],
            'malformed prepend match path' => [
                static function (): void {
                    Prop::merge([])->prepend('data', 'items..id');
                },
                Message::MERGE_PATH_INVALID,
            ],
            'malformed explicit match path' => [
                static function (): void {
                    Prop::merge([])->matchOn('items..id');
                },
                Message::MERGE_PATH_INVALID,
            ],
            'non-string explicit match path' => [
                static function (): void {
                    Prop::merge([])->matchOn(['items.id', 42]);
                },
                Message::MERGE_MATCH_PATH_INVALID,
            ],
            'once key with comma' => [
                static function (): void {
                    Prop::once(static fn(): null => null)->as('roles,permissions');
                },
                Message::ONCE_PROP_KEY_INVALID,
            ],
            'empty once constructor key' => [
                static function (): void {
                    new OnceProp(static fn(): null => null, '');
                },
                Message::ONCE_PROP_KEY_INVALID,
            ],
            'malformed scroll wrapper' => [
                static function (): void {
                    Prop::scroll([], new ScrollMetadata('page', null, null, 1), 'data..items');
                },
                Message::SCROLL_WRAPPER_INVALID,
            ],
            'empty scroll wrapper' => [
                static function (): void {
                    Prop::scroll([], new ScrollMetadata('page', null, null, 1), '');
                },
                Message::SCROLL_WRAPPER_INVALID,
            ],
            'empty scroll page name' => [
                static function (): void {
                    new ScrollMetadata('', null, null, null);
                },
                Message::SCROLL_PAGE_NAME_INVALID,
            ],
            'negative once expiration' => [
                static function (): void {
                    Prop::once(static fn(): null => null)->until(-1);
                },
                Message::ONCE_PROP_EXPIRATION_INVALID,
            ],
        ];
    }
}
