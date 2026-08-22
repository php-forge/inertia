<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests;

use Closure;
use PHPForge\Inertia\Exception\{InvalidPropException, Message};
use PHPForge\Inertia\Prop\{DeferredProp, OnceProp, Prop, ScrollMetadata};
use PHPForge\Inertia\Tests\Provider\PropProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Prop} factory values and immutable modifier composition.
 *
 * {@see PropProvider} for test case data providers.
 */
#[Group('prop')]
final class PropTest extends TestCase
{
    public function testComposesDeferredOptionalAndScrollWithOnce(): void
    {
        $deferred = Prop::defer(static fn(): array => [], 'analytics', true);
        $optional = Prop::optional(static fn(): array => []);
        $scroll = Prop::scroll(
            static fn(): array => ['data' => []],
            new ScrollMetadata('page', null, 2, 1),
        );

        self::assertSame(
            'analytics',
            $deferred->group(),
            'Deferred group must match the expected value.',
        );
        self::assertTrue(
            $deferred->rescuesFailures(),
            'Deferred rescue flag must be `true`.',
        );
        self::assertFalse(
            Prop::defer(static fn(): null => null)->rescuesFailures(),
            'Deferred rescue flag must default to `false`.',
        );
        self::assertFalse(
            (new DeferredProp(static fn(): null => null))->rescuesFailures(),
            'A directly constructed deferred prop must default rescue to `false`.',
        );
        self::assertSame(
            $deferred,
            $deferred->once()->value(),
            'Once must retain the deferred wrapper.',
        );
        self::assertSame(
            $optional,
            $optional->once()->value(),
            'Once must retain the optional wrapper.',
        );
        self::assertSame(
            $scroll,
            $scroll->once()->value(),
            'Once must retain the scroll wrapper.',
        );
        self::assertSame(
            $scroll,
            $scroll->defer()->value(),
            'Deferral must retain the scroll wrapper.',
        );
        self::assertFalse(
            $scroll->defer()->rescuesFailures(),
            'Scroll deferral rescue flag must default to `false`.',
        );
    }

    public function testComposesImmutableMergeModifiers(): void
    {
        $merge = Prop::merge(['data' => []]);

        $configured = $merge->append('data', 'id')->prepend('notices')->matchOn('items.uuid');

        self::assertTrue(
            $merge->appendsAtRoot(),
            'Root append flag must be `true`.',
        );
        self::assertFalse(
            $merge->prependsAtRoot(),
            'A root append must not also prepend at the root.',
        );
        self::assertSame(
            [],
            $merge->appendPaths(),
            'Append paths must match the expected value.',
        );
        self::assertSame(
            ['data'],
            $configured->appendPaths(),
            'Append paths must match the expected value.',
        );
        self::assertSame(
            ['notices'],
            $configured->prependPaths(),
            'Prepend paths must match the expected value.',
        );
        self::assertSame(
            ['data.id', 'items.uuid'],
            $configured->matchPaths(),
            'Match paths must match the expected value.',
        );
        self::assertFalse(
            $configured->appendsAtRoot(),
            'Root append flag must be `false`.',
        );
        self::assertTrue(
            $configured->deepMerge()->isDeep(),
            'Deep merge flag must be `true`.',
        );

        $accumulated = $merge
            ->append('users')
            ->append('roles')
            ->prepend('alerts')
            ->prepend('notices')
            ->matchOn(['users.id', 'roles.id']);

        self::assertSame(
            ['users', 'roles'],
            $accumulated->appendPaths(),
            'Accumulated append paths must retain their insertion order.',
        );
        self::assertSame(
            ['alerts', 'notices'],
            $accumulated->prependPaths(),
            'Accumulated prepend paths must retain their insertion order.',
        );
        self::assertSame(
            ['users.id', 'roles.id'],
            $accumulated->matchPaths(),
            'Accumulated match paths must retain their insertion order.',
        );
        self::assertFalse(
            $accumulated->prependsAtRoot(),
            'A path-based merge must not prepend at the root.',
        );
        self::assertFalse(
            $merge->deepMerge()->prependsAtRoot(),
            'A deep merge must not prepend at the root.',
        );
    }

    public function testPreservesZeroOnceExpirationAndScrollResetDefault(): void
    {
        $callback = static fn(): null => null;
        $once = (new OnceProp($callback, expiration: 0))->until(0);
        $metadata = new ScrollMetadata('page', null, null, 1);

        self::assertSame(
            0,
            $once->expiration(),
            'A zero once expiration must remain valid.',
        );
        self::assertFalse(
            $metadata->toArray()['reset'],
            'Scroll reset must default to `false`.',
        );
    }

    /**
     * @param Closure(): void $operation
     */
    #[DataProviderExternal(PropProvider::class, 'invalidDefinitions')]
    public function testThrowInvalidPropExceptionForInvalidDefinition(
        Closure $operation,
        Message $message,
    ): void {
        $this->expectException(InvalidPropException::class);
        $this->expectExceptionMessage(
            $message->getMessage(),
        );

        $operation();
    }
}
