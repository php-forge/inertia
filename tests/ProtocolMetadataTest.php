<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests;

use DateInterval;
use DateTimeImmutable;
use PHPForge\Inertia\Exception\{Message, PropResolutionException};
use PHPForge\Inertia\PageInput;
use PHPForge\Inertia\Prop\{AlwaysProp, MergeProp, OnceProp, Prop, ScrollMetadata};
use PHPForge\Inertia\{Protocol, RequestContext};
use PHPForge\Inertia\Result\InertiaPageResult;
use PHPForge\Inertia\Tests\Fixture\{CallRecorder, FrozenClock};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see Protocol} prop metadata and modifier interactions.
 */
#[Group('protocol')]
#[Group('metadata')]
final class ProtocolMetadataTest extends TestCase
{
    public function testCollectsMergeStrategiesAndHonorsReset(): void
    {
        $result = (new Protocol())
            ->page(
                self::request(['X-Inertia-Reset' => 'events']),
                new PageInput(
                    'Feed',
                    [
                        'events' => Prop::merge([1, 2]),
                        'notices' => Prop::merge([3, 4])->prepend(),
                        'users' => Prop::merge(['data' => [['id' => 1]]])->append('data', 'id'),
                        'alerts' => Prop::merge(['data' => [['uuid' => 'one']]])->prepend('data', 'uuid'),
                        'settings' => Prop::merge(['items' => [['id' => 1]]])
                            ->deepMerge()
                            ->matchOn('items.id'),
                        'dynamic' => static fn(): MergeProp => Prop::merge(['item']),
                    ],
                    'v1',
                ),
            );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            ['users.data', 'dynamic'],
            $result->page()->mergeProps(),
            'Merge prop metadata must match the expected value.',
        );
        self::assertSame(
            ['notices', 'alerts.data'],
            $result->page()->prependProps(),
            'Prepend prop metadata must match the expected value.',
        );
        self::assertSame(
            ['settings'],
            $result->page()->deepMergeProps(),
            'Deep-merge prop metadata must match the expected value.',
        );
        self::assertSame(
            ['users.data.id', 'alerts.data.uuid', 'settings.items.id'],
            $result->page()->matchPropsOn(),
            'Match-on prop metadata must match the expected value.',
        );
        self::assertSame(
            [
                'events' => [1, 2],
                'notices' => [3, 4],
                'users' => ['data' => [['id' => 1]]],
                'alerts' => ['data' => [['uuid' => 'one']]],
                'settings' => ['items' => [['id' => 1]]],
                'dynamic' => ['item'],
                'errors' => [],
            ],
            $result->page()->props,
            'Resolved page props must match the expected value.',
        );
    }

    public function testCollectsMultipleMetadataEntriesWithoutDiscardingEarlierProps(): void
    {
        $metadata = new ScrollMetadata('page', null, null, 1);
        $input = new PageInput(
            'Feed',
            [
                'deferredOne' => Prop::defer(static fn(): array => [], 'shared'),
                'deferredTwo' => Prop::defer(static fn(): array => [], 'shared'),
                'scrollOne' => Prop::scroll(['data' => [1]], $metadata),
                'scrollTwo' => Prop::scroll(['data' => [2]], $metadata),
                'deepOne' => Prop::merge(['id' => 1])->deepMerge(),
                'deepTwo' => Prop::merge(['id' => 2])->deepMerge(),
                'prependOne' => Prop::merge([1])->prepend(),
                'prependTwo' => Prop::merge([2])->prepend(),
                'nested' => Prop::merge(['first' => [], 'second' => []])
                    ->append('first')
                    ->append('second'),
            ],
            'v1',
        );
        $append = (new Protocol())
            ->page(
                self::request(),
                $input,
            );
        $prepend = (new Protocol())
            ->page(
                self::request(['X-Inertia-Infinite-Scroll-Merge-Intent' => 'prepend']),
                $input,
            );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $append,
            'Append visit must return an Inertia page result.',
        );
        self::assertInstanceOf(
            InertiaPageResult::class,
            $prepend,
            'Prepend visit must return an Inertia page result.',
        );
        self::assertSame(
            ['shared' => ['deferredOne', 'deferredTwo']],
            $append->page()->deferredProps(),
            'Deferred metadata must retain every prop in a shared group.',
        );
        self::assertSame(
            ['scrollOne.data', 'scrollTwo.data', 'nested.first', 'nested.second'],
            $append->page()->mergeProps(),
            'Append metadata must retain scroll and nested merge paths.',
        );
        self::assertSame(
            ['prependOne', 'prependTwo'],
            $append->page()->prependProps(),
            'Root prepend metadata must retain every configured prop.',
        );
        self::assertSame(
            ['deepOne', 'deepTwo'],
            $append->page()->deepMergeProps(),
            'Deep-merge metadata must retain every configured prop.',
        );
        self::assertSame(
            ['nested.first', 'nested.second'],
            $prepend->page()->mergeProps(),
            'Nested append paths must remain append metadata during a prepend visit.',
        );
        self::assertSame(
            ['scrollOne.data', 'scrollTwo.data', 'prependOne', 'prependTwo'],
            $prepend->page()->prependProps(),
            'Prepend metadata must retain scroll and root prepend paths.',
        );
    }

    public function testDeferredScrollPropAnnouncesDeferredAndMergeMetadata(): void
    {
        $result = (new Protocol())
            ->page(
                self::request(),
                new PageInput(
                    'Posts',
                    [
                        'posts' => Prop::scroll(
                            static fn(): array => ['data' => []],
                            new ScrollMetadata('page', null, null, 1),
                        )->defer('feed'),
                    ],
                    'v1',
                ),
            );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            ['feed' => ['posts']],
            $result->page()->deferredProps(),
            'Deferred prop metadata must match the expected value.',
        );
        self::assertSame(
            ['posts.data'],
            $result->page()->mergeProps(),
            'Merge prop metadata must match the expected value.',
        );
        self::assertSame(
            [],
            $result->page()->scrollProps(),
            'Scroll prop metadata must match the expected value.',
        );
    }

    public function testDefersComposedPropsWithoutCallingCallbacks(): void
    {
        $called = false;

        $result = (new Protocol())
            ->page(
                self::request(),
                new PageInput(
                    'Reports',
                    [
                        'combined' => Prop::defer(
                            static function () use (&$called): array {
                                $called = true;

                                return ['data' => []];
                            },
                            'analytics'
                        )
                        ->deepMerge()
                        ->once()
                        ->as('report-data'),
                    ],
                    'v1',
                ),
            );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertFalse(
            $called,
            'Callback invocation flag must be `false`.',
        );
        self::assertSame(
            ['analytics' => ['combined']],
            $result->page()->deferredProps(),
            'Deferred prop metadata must match the expected value.',
        );
        self::assertSame(
            ['combined'],
            $result->page()->deepMergeProps(),
            'Deep-merge prop metadata must match the expected value.',
        );
        self::assertSame(
            ['report-data' => ['prop' => 'combined', 'expiresAt' => null]],
            $result->page()->onceProps(),
            'Once prop metadata must match the expected value.',
        );
    }

    public function testOnceExpirationAcceptsAbsoluteDateTime(): void
    {
        $expiration = new DateTimeImmutable('2026-08-23T12:00:00+00:00');
        $result = (new Protocol())
            ->page(
                self::request(),
                new PageInput(
                    'Billing',
                    ['plans' => Prop::once(static fn(): array => [])->until($expiration)],
                    'v1',
                ),
            );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            ['plans' => ['prop' => 'plans', 'expiresAt' => $expiration->getTimestamp() * 1000]],
            $result->page()->onceProps(),
            'Once prop metadata must match the expected value.',
        );
    }

    public function testOnceExpirationAcceptsDateInterval(): void
    {
        $now = new DateTimeImmutable('2026-08-22T12:00:00+00:00');
        $result = (new Protocol(new FrozenClock($now)))
            ->page(
                self::request(),
                new PageInput(
                    'Billing',
                    ['plans' => Prop::once(static fn(): array => [])->until(new DateInterval('PT30M'))],
                    'v1',
                ),
            );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            ['plans' => ['prop' => 'plans', 'expiresAt' => ($now->getTimestamp() + 1800) * 1000]],
            $result->page()->onceProps(),
            'Once prop metadata must match the expected value.',
        );
    }

    public function testOncePropsSkipCachedValuesButResolveExplicitPartialsAndFreshValues(): void
    {
        $now = new DateTimeImmutable('2026-08-22T12:00:00+00:00');
        $protocol = new Protocol(new FrozenClock($now));
        $calls = new CallRecorder();

        $callback = static function () use ($calls): array {
            $calls->record();

            return ['pro'];
        };

        $input = new PageInput(
            'Billing',
            [
                'plans' => Prop::once($callback)
                    ->as('billing-plans')
                    ->until(3600),
            ],
            'v1',
        );

        $initial = $protocol->page(self::request(), $input);

        self::assertInstanceOf(
            InertiaPageResult::class,
            $initial,
            'Initial visit must return an Inertia page result.',
        );
        self::assertSame(
            ['plans' => ['pro'], 'errors' => []],
            $initial->page()->props,
            'Resolved page props must match the expected value.',
        );
        self::assertSame(
            1,
            $calls->count(),
            'Callback count must match the expected value.',
        );
        self::assertSame(
            [
                'billing-plans' => [
                    'prop' => 'plans',
                    'expiresAt' => ($now->getTimestamp() + 3600) * 1000,
                ],
            ],
            $initial->page()->onceProps(),
            'Once prop metadata must match the expected value.',
        );

        $cached = $protocol->page(
            self::request(['X-Inertia-Except-Once-Props' => 'billing-plans']),
            $input,
        );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $cached,
            'Cached visit must return an Inertia page result.',
        );
        self::assertArrayNotHasKey(
            'plans',
            $cached->page()->props,
            'Resolved page props must not contain the unexpected key.',
        );
        self::assertArrayHasKey(
            'billing-plans',
            $cached->page()->onceProps(),
            'Once prop metadata must contain the expected key.',
        );
        self::assertSame(
            1,
            $calls->count(),
            'Callback count must match the expected value.',
        );

        $partial = $protocol->page(
            self::request(
                [
                    'X-Inertia-Except-Once-Props' => 'billing-plans',
                    'X-Inertia-Partial-Component' => 'Billing',
                    'X-Inertia-Partial-Data' => 'plans',
                ],
            ),
            $input,
        );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $partial,
            'Partial visit must return an Inertia page result.',
        );
        self::assertSame(
            ['plans' => ['pro'], 'errors' => []],
            $partial->page()->props,
            'Resolved page props must match the expected value.',
        );
        self::assertSame(
            2,
            $calls->count(),
            'Callback count must match the expected value.',
        );

        $freshInput = new PageInput(
            'Billing',
            ['plans' => Prop::once($callback)->as('billing-plans')->fresh()],
            'v1',
        );

        $fresh = $protocol->page(
            self::request(['X-Inertia-Except-Once-Props' => 'billing-plans']),
            $freshInput,
        );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $fresh,
            'Fresh visit must return an Inertia page result.',
        );
        self::assertSame(
            ['plans' => ['pro'], 'errors' => []],
            $fresh->page()->props,
            'Resolved page props must match the expected value.',
        );
        self::assertSame(
            3,
            $calls->count(),
            'Callback count must match the expected value.',
        );
    }

    public function testOptionalOncePropAnnouncesMetadataWithoutResolvingOnFullVisit(): void
    {
        $calls = new CallRecorder();
        $result = (new Protocol())
            ->page(
                self::request(),
                new PageInput(
                    'Billing',
                    [
                        'plans' => Prop::optional(static function () use ($calls): array {
                            $calls->record();

                            return [];
                        })->once()->as('plans'),
                    ],
                    'v1',
                ),
            );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            ['errors' => []],
            $result->page()->props,
            'Resolved page props must match the expected value.',
        );
        self::assertSame(
            ['plans' => ['prop' => 'plans', 'expiresAt' => null]],
            $result->page()->onceProps(),
            'Once prop metadata must match the expected value.',
        );
        self::assertSame(
            [],
            $calls->recordedCalls(),
            'Recorded callback list must match the expected value.',
        );
    }

    public function testPartialMetadataHonorsOnlyAndExceptForAlwaysProps(): void
    {
        $definition = static fn(string $name): AlwaysProp => new AlwaysProp(
            new MergeProp(new OnceProp(static fn(): array => [$name], $name)),
        );
        $result = (new Protocol())
            ->page(
                self::request([
                    'X-Inertia-Partial-Component' => 'Dashboard',
                    'X-Inertia-Partial-Data' => 'selected,excluded,nested',
                    'X-Inertia-Partial-Except' => 'excluded,nested.secret',
                ]),
                new PageInput(
                    'Dashboard',
                    [
                        'selected' => $definition('selected'),
                        'excluded' => $definition('excluded'),
                        'unselected' => $definition('unselected'),
                        'nested' => static fn(): array => [
                            'secret' => [
                                'token' => Prop::merge(['hidden']),
                            ],
                        ],
                    ],
                    'v1',
                ),
            );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            ['selected'],
            $result->page()->mergeProps(),
            'Partial merge metadata must include only selected, non-excluded props.',
        );
        self::assertSame(
            ['selected' => ['prop' => 'selected', 'expiresAt' => null]],
            $result->page()->onceProps(),
            'Partial once metadata must include only selected, non-excluded props.',
        );
        self::assertSame(
            [
                'selected' => ['selected'],
                'excluded' => ['excluded'],
                'unselected' => ['unselected'],
                'nested' => ['secret' => ['token' => ['hidden']]],
                'errors' => [],
            ],
            $result->page()->props,
            'Always props must remain in partial data independently of metadata filtering.',
        );
    }

    public function testRescuesDeferredFailureOnPartialReload(): void
    {
        $request = self::request(
            [
                'X-Inertia-Partial-Component' => 'Reports',
                'X-Inertia-Partial-Data' => 'metrics,summary',
            ],
        );
        $result = (new Protocol())
            ->page(
                $request,
                new PageInput(
                    'Reports',
                    [
                        'metrics' => Prop::defer(
                            static fn(): never => throw new RuntimeException('Analytics unavailable'),
                            rescue: true,
                        )->merge()->once(),
                        'summary' => Prop::defer(
                            static fn(): never => throw new RuntimeException('Summary unavailable'),
                            rescue: true,
                        ),
                    ],
                    'v1',
                ),
            );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            ['errors' => []],
            $result->page()->props,
            'Resolved page props must match the expected value.',
        );
        self::assertSame(
            ['metrics', 'summary'],
            $result->page()->rescuedProps(),
            'Rescued prop metadata must match the expected value.',
        );
        self::assertSame(
            [],
            $result->page()->mergeProps(),
            'Merge prop metadata must match the expected value.',
        );
        self::assertSame(
            [],
            $result->page()->onceProps(),
            'Once prop metadata must match the expected value.',
        );
        self::assertCount(
            2,
            $result->rescuedFailures(),
            'Rescued failures must contain the expected number of items.',
        );
        self::assertSame(
            'metrics',
            $result->rescuedFailures()[0]->propPath,
            'Prop path must match the expected value.',
        );
        self::assertSame(
            'Analytics unavailable',
            $result->rescuedFailures()[0]->failure->getMessage(),
            'Failure message must match the expected value.',
        );
        self::assertSame(
            'summary',
            $result->rescuedFailures()[1]->propPath,
            'Second rescued prop path must match the expected value.',
        );
    }

    public function testScrollPropCollectsDirectionMetadataAndReset(): void
    {
        $input = new PageInput(
            'Posts',
            [
                'posts' => Prop::scroll(
                    static fn(): array => ['data' => [['id' => 1]]],
                    static function (mixed $value): ScrollMetadata {
                        self::assertSame(
                            ['data' => [['id' => 1]]],
                            $value,
                            'Scroll metadata value must match the expected value.',
                        );

                        return new ScrollMetadata('page', null, 2, 1);
                    },
                ),
            ],
            'v1',
        );

        $append = (new Protocol())->page(self::request(), $input);

        self::assertInstanceOf(
            InertiaPageResult::class,
            $append,
            'Append visit must return an Inertia page result.',
        );
        self::assertSame(
            ['posts.data'],
            $append->page()->mergeProps(),
            'Merge prop metadata must match the expected value.',
        );
        self::assertSame(
            [],
            $append->page()->prependProps(),
            'Prepend prop metadata must match the expected value.',
        );
        self::assertSame(
            [
                'posts' => [
                    'pageName' => 'page',
                    'previousPage' => null,
                    'nextPage' => 2,
                    'currentPage' => 1,
                    'reset' => false,
                ],
            ],
            $append->page()->scrollProps(),
            'Scroll prop metadata must match the expected value.',
        );

        $prepend = (new Protocol())->page(
            self::request(['X-Inertia-Infinite-Scroll-Merge-Intent' => 'prepend']),
            $input,
        );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $prepend,
            'Prepend visit must return an Inertia page result.',
        );
        self::assertSame(
            ['posts.data'],
            $prepend->page()->prependProps(),
            'Prepend prop metadata must match the expected value.',
        );

        $reset = (new Protocol())->page(
            self::request(['X-Inertia-Reset' => 'posts']),
            $input,
        );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $reset,
            'Reset visit must return an Inertia page result.',
        );
        self::assertSame(
            [],
            $reset->page()->mergeProps(),
            'Merge prop metadata must match the expected value.',
        );
        self::assertSame(
            [
                'posts' => [
                    'pageName' => 'page',
                    'previousPage' => null,
                    'nextPage' => 2,
                    'currentPage' => 1,
                    'reset' => true,
                ],
            ],
            $reset->page()->scrollProps(),
            'Scroll prop metadata must match the expected value.',
        );
    }

    public function testThrowPropResolutionExceptionForDeferredFailureWithoutRescue(): void
    {
        $this->expectException(PropResolutionException::class);
        $this->expectExceptionMessage(
            Message::PROP_RESOLUTION_FAILED->getMessage('metrics', 'Unavailable'),
        );

        (new Protocol())
            ->page(
                self::request(
                    [
                        'X-Inertia-Partial-Component' => 'Reports',
                        'X-Inertia-Partial-Data' => 'metrics',
                    ],
                ),
                new PageInput(
                    'Reports',
                    [
                        'metrics' => Prop::defer(static fn(): never => throw new RuntimeException('Unavailable')),
                    ],
                    'v1',
                ),
            );
    }

    public function testThrowPropResolutionExceptionForInvalidScrollMetadataProviderResult(): void
    {
        $this->expectException(PropResolutionException::class);
        $this->expectExceptionMessage(
            Message::PROP_RESOLUTION_FAILED->getMessage(
                'posts',
                Message::SCROLL_METADATA_INVALID->getMessage('posts'),
            ),
        );

        (new Protocol())
            ->page(
                self::request(),
                new PageInput(
                    'Posts',
                    [
                        'posts' => Prop::scroll([], static fn(): string => 'invalid'),
                    ],
                    'v1',
                ),
            );
    }

    /**
     * @param array<string, string> $headers
     */
    private static function request(array $headers = []): RequestContext
    {
        return new RequestContext(
            'GET',
            '/page',
            'https://example.com/page',
            ['X-Inertia' => 'true', ...$headers],
        );
    }
}
