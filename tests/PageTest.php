<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests;

use Closure;
use PHPForge\Inertia\Exception\{InvalidPageInputException, Message};
use PHPForge\Inertia\{Page, PageInput, PageMetadata};
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
    public function testCreatesPageInputFromStaticFactory(): void
    {
        $input = PageInput::create('Dashboard', ['answer' => 42], 7);

        self::assertSame(
            'Dashboard',
            $input->component,
            'Factory component must match the supplied value.',
        );
        self::assertSame(
            ['answer' => 42],
            $input->props,
            'Factory props must match the supplied value.',
        );
        self::assertSame(
            7,
            $input->version,
            'Factory version must match the supplied value.',
        );
        self::assertSame(
            [],
            $input->sharedProps(),
            'Factory inputs must preserve the shared-prop default.',
        );
    }

    public function testPageInputModifiersReturnNewValues(): void
    {
        $input = PageInput::create('Dashboard', ['answer' => 42], 'v1');

        self::assertNotSame(
            $input,
            $input->withClearHistory(),
            'Clear-history must return a new input instance.',
        );
        self::assertNotSame(
            $input,
            $input->withEncryptHistory(),
            'Encrypt-history must return a new input instance.',
        );
        self::assertNotSame(
            $input,
            $input->withErrors(['email' => 'Invalid']),
            'Validation errors must return a new input instance.',
        );
        self::assertNotSame(
            $input,
            $input->withFlash(['message' => 'Saved']),
            'Flash data must return a new input instance.',
        );
        self::assertNotSame(
            $input,
            $input->withPreserveFragment(),
            'Fragment preservation must return a new input instance.',
        );

        $hidden = $input->withSharedPropsExposure(false);
        $exposed = $hidden->withSharedPropsExposure();

        self::assertNotSame(
            $hidden,
            $exposed,
            'Shared-prop exposure must return a new input instance.',
        );
        self::assertFalse(
            $hidden->exposesSharedProps(),
            'The hidden input must remain hidden.',
        );
        self::assertTrue(
            $exposed->exposesSharedProps(),
            'Shared-prop exposure must default to enabled.',
        );

        $configured = $input
            ->withSharedProps(['auth' => ['id' => 7]])
            ->withErrors(['email' => 'Invalid'])
            ->withFlash(['message' => 'Saved'])
            ->withEncryptHistory()
            ->withClearHistory()
            ->withPreserveFragment()
            ->withSharedPropsExposure(false);

        self::assertNotSame(
            $input,
            $configured,
            'Modifiers must return a different input instance.',
        );
        self::assertSame(
            [],
            $input->sharedProps(),
            'Modifiers must not change the original shared props.',
        );
        self::assertSame(
            [],
            $input->errors(),
            'Modifiers must not change the original validation errors.',
        );
        self::assertSame(
            [],
            $input->flash(),
            'Modifiers must not change the original flash data.',
        );
        self::assertFalse(
            $input->encryptHistory(),
            'Modifiers must not change the original encrypt-history option.',
        );
        self::assertFalse(
            $input->clearHistory(),
            'Modifiers must not change the original clear-history option.',
        );
        self::assertFalse(
            $input->preserveFragment(),
            'Modifiers must not change the original fragment option.',
        );
        self::assertTrue(
            $input->exposesSharedProps(),
            'Modifiers must not change the original exposure option.',
        );
        self::assertSame(
            ['auth' => ['id' => 7]],
            $configured->sharedProps(),
            'Configured shared props must match the replacement value.',
        );
        self::assertSame(
            ['email' => 'Invalid'],
            $configured->errors(),
            'Configured validation errors must match the replacement value.',
        );
        self::assertSame(
            ['message' => 'Saved'],
            $configured->flash(),
            'Configured flash data must match the replacement value.',
        );
        self::assertTrue(
            $configured->encryptHistory(),
            'Encrypt-history must be enabled on the configured input.',
        );
        self::assertTrue(
            $configured->clearHistory(),
            'Clear-history must be enabled on the configured input.',
        );
        self::assertTrue(
            $configured->preserveFragment(),
            'Fragment preservation must be enabled on the configured input.',
        );
        self::assertFalse(
            $configured->exposesSharedProps(),
            'Shared-prop exposure must be disabled on the configured input.',
        );
    }

    public function testPageInputUsesProtocolDefaults(): void
    {
        $input = new PageInput('Dashboard', [], 'v1');

        self::assertFalse(
            $input->encryptHistory(),
            'Encrypt-history must default to `false`.',
        );
        self::assertFalse(
            $input->clearHistory(),
            'Clear-history must default to `false`.',
        );
        self::assertFalse(
            $input->preserveFragment(),
            'Preserve-fragment must default to `false`.',
        );
        self::assertTrue(
            $input->exposesSharedProps(),
            'Shared-prop exposure must default to `true`.',
        );
        self::assertSame(
            [],
            $input->sharedProps(),
            'Shared props must default to an empty array.',
        );
        self::assertSame(
            [],
            $input->errors(),
            'Validation errors must default to an empty array.',
        );
        self::assertSame(
            [],
            $input->flash(),
            'Flash data must default to an empty array.',
        );
    }

    public function testPageMetadataModifiersReturnNewValues(): void
    {
        $metadata = new PageMetadata();

        $scrollProps = [
            'posts' => [
                'pageName' => 'page',
                'previousPage' => null,
                'nextPage' => 2,
                'currentPage' => 1,
                'reset' => false,
            ],
        ];
        $onceProps = [
            'plans' => [
                'prop' => 'plans',
                'expiresAt' => null,
            ],
        ];

        self::assertNotSame(
            $metadata,
            $metadata->withPrependProps(['messages']),
            'Prepend metadata must return a new instance.',
        );
        self::assertNotSame(
            $metadata,
            $metadata->withDeepMergeProps(['settings']),
            'Deep-merge metadata must return a new instance.',
        );
        self::assertNotSame(
            $metadata,
            $metadata->withMatchPropsOn(['users.data.id']),
            'Match metadata must return a new instance.',
        );
        self::assertNotSame(
            $metadata,
            $metadata->withScrollProps($scrollProps),
            'Scroll metadata must return a new instance.',
        );
        self::assertNotSame(
            $metadata,
            $metadata->withDeferredProps(['default' => ['analytics']]),
            'Deferred metadata must return a new instance.',
        );
        self::assertNotSame(
            $metadata,
            $metadata->withRescuedProps(['reports']),
            'Rescued metadata must return a new instance.',
        );
        self::assertNotSame(
            $metadata,
            $metadata->withSharedProps(['auth']),
            'Shared metadata must return a new instance.',
        );
        self::assertNotSame(
            $metadata,
            $metadata->withOnceProps($onceProps),
            'Once metadata must return a new instance.',
        );

        $configured = $metadata
            ->withMergeProps(['feed'])
            ->withPrependProps(['messages'])
            ->withDeepMergeProps(['settings'])
            ->withMatchPropsOn(['users.data.id'])
            ->withScrollProps($scrollProps)
            ->withDeferredProps(['default' => ['analytics']])
            ->withRescuedProps(['reports'])
            ->withSharedProps(['auth'])
            ->withOnceProps($onceProps);

        self::assertNotSame(
            $metadata,
            $configured,
            'Metadata modifiers must return a different instance.',
        );
        self::assertSame(
            [],
            $metadata->toArray(),
            'Metadata modifiers must not change the original instance.',
        );
        self::assertSame(
            ['feed'],
            $configured->mergeProps(),
            'Merge metadata must match the replacement value.',
        );
        self::assertSame(
            ['messages'],
            $configured->prependProps(),
            'Prepend metadata must match the replacement value.',
        );
        self::assertSame(
            ['settings'],
            $configured->deepMergeProps(),
            'Deep-merge metadata must match the replacement value.',
        );
        self::assertSame(
            ['users.data.id'],
            $configured->matchPropsOn(),
            'Match metadata must match the replacement value.',
        );
        self::assertSame(
            $scrollProps,
            $configured->scrollProps(),
            'Scroll metadata must match the replacement value.',
        );
        self::assertSame(
            ['default' => ['analytics']],
            $configured->deferredProps(),
            'Deferred metadata must match the replacement value.',
        );
        self::assertSame(
            ['reports'],
            $configured->rescuedProps(),
            'Rescued metadata must match the replacement value.',
        );
        self::assertSame(
            ['auth'],
            $configured->sharedProps(),
            'Shared metadata must match the replacement value.',
        );
        self::assertSame(
            $onceProps,
            $configured->onceProps(),
            'Once metadata must match the replacement value.',
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
        $original = new Page('Feed', ['errors' => ['email' => 'Invalid']], '/feed', 7);
        $metadata = (new PageMetadata())
            ->withMergeProps(['feed.data'])
            ->withSharedProps(['auth']);

        self::assertNotSame(
            $original,
            $original->withClearHistory(),
            'Clear-history must return a new page instance.',
        );
        self::assertNotSame(
            $original,
            $original->withEncryptHistory(),
            'Encrypt-history must return a new page instance.',
        );
        self::assertNotSame(
            $original,
            $original->withFlash(['message' => 'Saved']),
            'Flash data must return a new page instance.',
        );
        self::assertNotSame(
            $original,
            $original->withPreserveFragment(),
            'Fragment preservation must return a new page instance.',
        );

        $page = $original
            ->withMetadata($metadata)
            ->withEncryptHistory()
            ->withClearHistory()
            ->withPreserveFragment()
            ->withFlash(['message' => 'Saved']);

        $data = $page->toArray();

        self::assertNotSame(
            $original,
            $page,
            'Page modifiers must return a different instance.',
        );
        self::assertSame(
            [],
            $original->metadata()->toArray(),
            'Page modifiers must not change the original metadata.',
        );
        self::assertSame(
            [],
            $original->flash(),
            'Page modifiers must not change the original flash data.',
        );
        self::assertFalse(
            $original->encryptHistory(),
            'Page modifiers must not change the original history encryption.',
        );
        self::assertFalse(
            $original->clearHistory(),
            'Page modifiers must not change the original clear-history setting.',
        );
        self::assertFalse(
            $original->preserveFragment(),
            'Page modifiers must not change the original fragment setting.',
        );
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
        $this->expectExceptionMessage(
            $message->getMessage(...$arguments),
        );

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

        PageInput::create($component, $props, 'v1')->withErrors($errors);
    }
}
