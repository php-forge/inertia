<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests;

use Closure;
use PHPForge\Inertia\Exception\{InvalidPageInputException, Message};
use PHPForge\Inertia\{Page, PageInput};
use PHPForge\Inertia\Tests\Provider\PageProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PageInput} validation and {@see Page} serialization.
 *
 * {@see PageProvider} for test case data providers.
 */
#[Group('page')]
final class PageTest extends TestCase
{
    public function testPageInputUsesProtocolDefaults(): void
    {
        $input = new PageInput('Dashboard', [], 'v1');

        self::assertFalse(
            $input->encryptHistory,
            'Encrypt-history must default to `false`.',
        );
        self::assertFalse(
            $input->clearHistory,
            'Clear-history must default to `false`.',
        );
        self::assertFalse(
            $input->preserveFragment,
            'Preserve-fragment must default to `false`.',
        );
        self::assertTrue(
            $input->exposeSharedProps,
            'Shared-prop exposure must default to `true`.',
        );
    }

    public function testSerializesMinimalPageWithErrorsObject(): void
    {
        $page = new Page(
            'Dashboard',
            ['answer' => 42, 'errors' => []],
            '/dashboard',
            'v1',
        );

        self::assertSame(
            '{"component":"Dashboard","props":{"answer":42,"errors":{}},"url":"/dashboard","version":"v1"}',
            json_encode($page, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'Serialized page JSON must match the expected value.',
        );
    }

    public function testSerializesOnlyActiveOptionalFields(): void
    {
        $page = new Page(
            component: 'Feed',
            props: ['errors' => ['email' => 'Invalid']],
            url: '/feed',
            version: 7,
            encryptHistory: true,
            clearHistory: true,
            preserveFragment: true,
            mergeProps: ['feed.data'],
            sharedProps: ['auth'],
            flash: ['message' => 'Saved'],
        );

        $data = $page->toArray();

        self::assertSame(
            '{"component":"Feed","props":{"errors":{"email":"Invalid"}},"url":"/feed","version":7,"encryptHistory":true,"clearHistory":true,"preserveFragment":true,"mergeProps":["feed.data"],"sharedProps":["auth"],"flash":{"message":"Saved"}}',
            json_encode($page, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'Serialized page JSON must match the expected value.',
        );
        self::assertArrayNotHasKey(
            'deferredProps',
            $data,
            'Inactive deferred metadata must be omitted.',
        );
        self::assertArrayNotHasKey(
            'rescuedProps',
            $data,
            'Inactive rescued metadata must be omitted.',
        );
    }

    /**
     * @param Closure(): void $operation
     * @param list<int|string> $arguments
     */
    #[DataProviderExternal(PageProvider::class, 'invalidAuxiliaryInputs')]
    public function testThrowInvalidPageInputExceptionForInvalidAuxiliaryInput(
        Closure $operation,
        Message $message,
        array $arguments,
    ): void {
        $this->expectException(InvalidPageInputException::class);
        $this->expectExceptionMessage($message->getMessage(...$arguments));

        $operation();
    }

    /**
     * @param array<string, mixed> $props
     * @param array<array-key, mixed> $errors
     * @param list<int|string> $arguments
     */
    #[DataProviderExternal(PageProvider::class, 'invalidInputs')]
    public function testThrowInvalidPageInputExceptionForInvalidInput(
        string $component,
        array $props,
        array $errors,
        Message $message,
        array $arguments,
    ): void {
        $this->expectException(InvalidPageInputException::class);
        $this->expectExceptionMessage(
            $message->getMessage(...$arguments),
        );

        new PageInput($component, $props, 'v1', errors: $errors);
    }
}
