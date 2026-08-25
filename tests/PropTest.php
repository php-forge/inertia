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

        $appended = $merge->append('data', 'id');
        $prepended = $appended->prepend('notices');
        $configured = $prepended->matchOn('items.uuid');
        $deep = $configured->deepMerge();

        self::assertNotSame(
            $merge,
            $appended,
            'Append must return a new merge prop.',
        );
        self::assertNotSame(
            $appended,
            $prepended,
            'Prepend must return a new merge prop.',
        );
        self::assertNotSame(
            $prepended,
            $configured,
            'Match configuration must return a new merge prop.',
        );
        self::assertNotSame(
            $configured,
            $deep,
            'Deep merge must return a new merge prop.',
        );

        self::assertTrue(
            $merge->appendsAtRoot(),
            'The original merge prop must continue appending at the root.',
        );
        self::assertFalse(
            $merge->prependsAtRoot(),
            'The original merge prop must not prepend at the root.',
        );
        self::assertFalse(
            $merge->isDeep(),
            'The original merge prop must not become a deep merge.',
        );
        self::assertSame(
            [],
            $merge->appendPaths(),
            'The original merge prop must retain empty append paths.',
        );
        self::assertSame(
            [],
            $merge->prependPaths(),
            'The original merge prop must retain empty prepend paths.',
        );
        self::assertSame(
            [],
            $merge->matchPaths(),
            'The original merge prop must retain empty match paths.',
        );

        self::assertSame(
            ['data'],
            $appended->appendPaths(),
            'Append paths must match the expected value.',
        );
        self::assertSame(
            [],
            $appended->prependPaths(),
            'A later prepend must not modify the appended merge prop.',
        );
        self::assertSame(
            ['data.id'],
            $appended->matchPaths(),
            'A later match operation must not modify the appended merge prop.',
        );

        self::assertSame(
            ['data'],
            $prepended->appendPaths(),
            'The prepended merge prop must retain append paths.',
        );
        self::assertSame(
            ['notices'],
            $prepended->prependPaths(),
            'Prepend paths must match the expected value.',
        );
        self::assertSame(
            ['data.id'],
            $prepended->matchPaths(),
            'A later match operation must not modify the prepended merge prop.',
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
            $deep->isDeep(),
            'Deep merge flag must be `true`.',
        );
        self::assertSame(
            [],
            $deep->appendPaths(),
            'Deep merge must clear append paths.',
        );
        self::assertSame(
            [],
            $deep->prependPaths(),
            'Deep merge must clear prepend paths.',
        );
        self::assertSame(
            ['data.id', 'items.uuid'],
            $deep->matchPaths(),
            'Deep merge must retain match paths.',
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

        $rootAppend = $configured->append();

        self::assertNotSame(
            $configured,
            $rootAppend,
            'Root append must return a new merge prop.',
        );
        self::assertTrue(
            $rootAppend->appendsAtRoot(),
            'An empty append path must restore root append behavior.',
        );
        self::assertSame(
            ['data.id', 'items.uuid'],
            $rootAppend->matchPaths(),
            'Restoring root append behavior must retain match paths.',
        );

        $rootPrepend = $configured->prepend();

        self::assertNotSame(
            $configured,
            $rootPrepend,
            'Root prepend must return a new merge prop.',
        );
        self::assertTrue(
            $rootPrepend->prependsAtRoot(),
            'An empty prepend path must restore root prepend behavior.',
        );
        self::assertSame(
            ['data.id', 'items.uuid'],
            $rootPrepend->matchPaths(),
            'Restoring root prepend behavior must retain match paths.',
        );

        $matched = $merge->matchOn('items.uuid');

        self::assertSame(
            ['items.uuid', 'users.id'],
            $matched->append('users', 'id')->matchPaths(),
            'Append must retain existing match paths.',
        );
        self::assertSame(
            ['items.uuid', 'notices.id'],
            $matched->prepend('notices', 'id')->matchPaths(),
            'Prepend must retain existing match paths.',
        );
        self::assertSame(
            ['data'],
            $configured->appendPaths(),
            'Root operations must not modify the configured merge prop append paths.',
        );
        self::assertSame(
            ['notices'],
            $configured->prependPaths(),
            'Root operations must not modify the configured merge prop prepend paths.',
        );
    }

    public function testConfiguresDeferredFailureRescueImmutably(): void
    {
        $deferred = Prop::defer(static fn(): null => null);

        $rescued = $deferred->rescue();
        $notRescued = $rescued->rescue(false);

        self::assertFalse(
            $deferred->rescuesFailures(),
            'The original deferred prop rescue flag must remain `false`.',
        );
        self::assertTrue(
            $rescued->rescuesFailures(),
            'The configured deferred prop rescue flag must be `true`.',
        );
        self::assertFalse(
            $notRescued->rescuesFailures(),
            'The disabled deferred prop rescue flag must be `false`.',
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
