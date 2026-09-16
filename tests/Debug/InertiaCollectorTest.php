<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Debug;

use InvalidArgumentException;
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use PHPForge\Inertia\Event\ProtocolResultCreated;
use PHPForge\Inertia\Exception\Message;
use PHPForge\Inertia\{PageInput, Protocol, RequestContext};
use PHPForge\Inertia\Prop\Prop;
use PHPForge\Inertia\Result\PageResult;
use PHPForge\Inertia\Tests\Fixture\CollectingEventDispatcherStub;
use PHPForge\Inertia\Tests\Provider\InertiaCollectorProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

use function in_array;
use function is_array;

/**
 * Exercises real protocol operations through PSR-14 without a debugger or manual collection calls.
 */
final class InertiaCollectorTest extends TestCase
{
    public function testDefaultConstructorCapturesWithoutSanitizerPolicies(): void
    {
        $collector = new InertiaCollector();
        $dispatcher = new CollectingEventDispatcherStub([$collector]);

        $protocol = self::protocol($dispatcher);

        $collector->startup();

        $protocol->page(
            new RequestContext(
                'GET',
                '/page?token=secret',
                'https://example.com/page?token=secret',
                ['X-Inertia' => 'true'],
            ),
            (new PageInput('Actual', [], 'v1'))->withSharedProps(['shared' => 'value']),
        );

        $payload = $collector->capture();

        self::assertNotNull(
            $payload,
            'Default collector must still capture.',
        );
        self::assertArrayHasKey(
            'page',
            $payload,
            'Page key must be present.',
        );
        self::assertArrayHasKey(
            'sharedKeys',
            $payload,
            'Shared keys must be present.',
        );
        self::assertArrayHasKey(
            'requestHeaders',
            $payload,
            'Request headers must be present.',
        );
        self::assertArrayHasKey(
            'resultType',
            $payload,
            'Result type must be present.',
        );
        self::assertIsArray(
            $payload['page'],
            'Page must survive the identity sanitizer.',
        );
        self::assertArrayHasKey(
            'url',
            $payload['page'],
            'Page URL must be present.',
        );
        self::assertSame(
            '/page?token=secret',
            $payload['page']['url'],
            'URL must be persisted verbatim.',
        );
        self::assertSame(
            ['shared'],
            $payload['sharedKeys'],
            'Shared keys must be persisted verbatim.',
        );
        self::assertSame(
            ['X-Inertia' => 'true'],
            $payload['requestHeaders'],
            'Headers must be persisted verbatim.',
        );
        self::assertSame(
            'page',
            $payload['resultType'],
            'Result type must be `page`.',
        );

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Shutdown must clear the buffered result.',
        );
        self::assertCount(
            1,
            $dispatcher->events,
            'One event per protocol call.',
        );
    }

    public function testDispatchForwardsProtocolResultsAndIgnoresOtherEvents(): void
    {
        $collector = new InertiaCollector();
        $protocol = new Protocol(eventDispatcher: $collector);
        $unrelated = new stdClass();

        $collector->startup();

        $idle = new InertiaCollector();

        $idle->startup();

        self::assertSame(
            $unrelated,
            $idle->dispatch($unrelated),
            'An unrelated event must pass through.',
        );
        self::assertNull(
            $idle->capture(),
            'An unrelated event must not be captured.',
        );

        $idle->shutdown();

        $protocol->page(
            new RequestContext(
                'GET',
                '/',
                'https://example.com/',
                ['X-Inertia' => 'true'],
            ),
            new PageInput(
                'Actual',
                [],
                'v1',
            ),
        );

        $payload = $collector->capture();

        self::assertNotNull(
            $payload,
            'The collector must observe the result it dispatched itself.',
        );
        self::assertSame(
            'page',
            $payload['resultType'] ?? null,
            "Result type must be 'page'."
        );

        $collector->shutdown();
    }

    public function testLastResultReplacesPreviousPageAndRequestState(): void
    {
        $collector = new InertiaCollector(
            static fn(array $page): array => $page,
            static fn(string $url): string => explode('?', $url)[0],
        );
        $dispatcher = new CollectingEventDispatcherStub([$collector]);

        $protocol = self::protocol($dispatcher);

        $collector->startup();

        $protocol->page(
            new RequestContext(
                'GET',
                '/',
                'https://example.com/',
                ['X-Inertia' => 'true'],
            ),
            new PageInput(
                'Previous',
                [],
                'v1',
            ),
        );

        $protocol->location(
            new RequestContext(
                'GET',
                '/',
                'https://example.com/',
            ),
            'https://example.com/new?private=1',
        );

        self::assertSame(
            [
                'location' => 'https://example.com/new',
                'page' => null,
                'requestHeaders' => [],
                'sharedKeys' => [],
                'statusCode' => 302,
                'resultType' => 'redirect',
            ],
            $collector->capture(),
            'The latest result must replace the previous page and headers.',
        );

        $collector->shutdown();

        self::assertCount(
            2,
            $dispatcher->events,
            'One event per protocol call.',
        );
    }

    public function testListenerFailurePropagatesWithoutRetry(): void
    {
        $failure = new RuntimeException('listener failed');
        $dispatcher = new CollectingEventDispatcherStub(
            [static fn(ProtocolResultCreated $event): never => throw $failure],
        );
        $protocol = new Protocol(eventDispatcher: $dispatcher);

        try {
            $protocol->page(
                new RequestContext(
                    'GET',
                    '/',
                    'https://example.com/'
                ),
                new PageInput('Actual', [], 'v1'),
            );

            self::fail(
                'A failing listener must not be swallowed.',
            );
        } catch (RuntimeException $caught) {
            self::assertSame(
                $failure,
                $caught,
                'The listener failure must stay primary.',
            );
        }

        self::assertCount(
            1,
            $dispatcher->events,
            'The failed operation must not be retried.',
        );
    }

    public function testPolicyCanRemoveSensitiveNegotiationMetadata(): void
    {
        $collector = new InertiaCollector(
            static function (array $payload): array {
                $payload['requestHeaders'] = [];
                $payload['sharedKeys'] = [];
                return $payload;
            },
            static fn(string $url): string => $url
        );
        $dispatcher = new CollectingEventDispatcherStub([$collector]);

        $protocol = self::protocol($dispatcher);

        $collector->startup();

        $protocol->page(
            new RequestContext(
                'GET',
                '/',
                'https://example.com/',
                [
                    'X-Inertia' => 'true',
                    'X-Inertia-Version' => 'v1',
                ],
            ),
            (new PageInput('Actual', [], 'v1'))->withSharedProps(['sensitive-key' => 'value']),
        );

        $payload = $collector->capture();

        self::assertNotNull(
            $payload,
            'An active cycle must produce a capture.',
        );
        self::assertArrayHasKey(
            'requestHeaders',
            $payload,
            'Request headers must be present.',
        );
        self::assertArrayHasKey(
            'sharedKeys',
            $payload,
            'Shared keys must be present.',
        );
        self::assertArrayHasKey(
            'statusCode',
            $payload,
            'Status code must be present.',
        );
        self::assertArrayHasKey(
            'location',
            $payload,
            'Location must be present.',
        );
        self::assertArrayHasKey(
            'page',
            $payload,
            'Page key must be present.',
        );
        self::assertArrayHasKey(
            'resultType',
            $payload,
            'Result type must be present.',
        );
        self::assertSame(
            [],
            $payload['requestHeaders'],
            'Policy must strip every request header.',
        );
        self::assertSame(
            [],
            $payload['sharedKeys'],
            'Policy must strip every shared key.',
        );

        $collector->shutdown();

        self::assertCount(
            1,
            $dispatcher->events,
            'One event per protocol call.',
        );
    }

    public function testResolvedPropsAreObservedOnceAndSanitizedOnlyDuringCapture(): void
    {
        $counter = new class {
            public int $sanitizations = 0;
            public int $resolutions = 0;
        };

        $collector = new InertiaCollector(
            static function (array $page) use ($counter): array {
                $counter->sanitizations++;

                if (is_array($page['page'] ?? null) && is_array($page['page']['props'] ?? null)) {
                    unset($page['page']['props']['private']);
                }

                return $page;
            },
            static fn(string $url): string => explode('?', $url)[0],
        );

        $dispatcher = new CollectingEventDispatcherStub([$collector]);

        $protocol = self::protocol($dispatcher);

        $request = new RequestContext(
            'GET',
            '/page?private=1',
            'https://example.com/page?private=1',
            ['X-Inertia' => 'true'],
        );

        self::assertSame(
            'inertia',
            $collector->id(),
            'Collector ID must stay stable.',
        );

        $protocol->page(
            $request,
            new PageInput(
                'Inactive',
                [],
                'v1',
            ),
        );

        self::assertNull(
            $collector->capture(),
            'No cycle means `null`.',
        );

        $collector->startup();

        self::assertNull(
            $collector->capture(),
            'A new cycle must start empty.',
        );

        $result = $protocol->page(
            $request,
            (
                new PageInput(
                    'Original',
                    [
                        'visible' => static function () use ($counter): string {
                            $counter->resolutions++;

                            return 'yes';
                        },
                        'private' => 'not persisted',
                        'deferred' => Prop::defer(
                            static fn(): never => throw new RuntimeException('must not resolve'),
                        ),
                        'optional' => Prop::optional(
                            static fn(): never => throw new RuntimeException('must not resolve'),
                        ),
                    ],
                    'v1',
                )
            )->withSharedProps(['shared' => 'value'])
        );

        self::assertSame(
            0,
            $counter->sanitizations,
            'Sanitization must not run inside the protocol operation.',
        );
        self::assertInstanceOf(
            PageResult::class,
            $result,
            'The protocol must return a page result.',
        );

        $collector->startup();

        $payload = $collector->capture();

        self::assertNotNull(
            $payload,
            'An active cycle must produce a capture.',
        );
        self::assertArrayHasKey(
            'requestHeaders',
            $payload,
            'Request headers must be present.',
        );
        self::assertArrayHasKey(
            'sharedKeys',
            $payload,
            'Shared keys must be present.',
        );
        self::assertArrayHasKey(
            'statusCode',
            $payload,
            'Status code must be present.',
        );
        self::assertArrayHasKey(
            'location',
            $payload,
            'Location must be present.',
        );
        self::assertArrayHasKey(
            'page',
            $payload,
            'Page key must be present.',
        );
        self::assertArrayHasKey(
            'resultType',
            $payload,
            'Result type must be present.',
        );
        self::assertSame(
            1,
            $counter->resolutions,
            'Each prop callback must resolve once.',
        );
        self::assertSame(
            1,
            $counter->sanitizations,
            'Sanitization must run once, at capture.',
        );
        self::assertSame(
            ['X-Inertia' => 'true'],
            $payload['requestHeaders'],
            'Observed headers must be persisted verbatim.',
        );
        self::assertSame(
            ['shared'],
            $payload['sharedKeys'],
            'Shared keys must be persisted verbatim.',
        );
        self::assertSame(
            200,
            $payload['statusCode'],
            'Status code must be persisted verbatim.',
        );
        self::assertNull(
            $payload['location'],
            'A page result has no navigation target.',
        );

        $expectedPage = $result->page()->toArray();

        self::assertArrayHasKey(
            'props',
            $expectedPage,
            'The page must expose its props.',
        );
        self::assertIsArray(
            $expectedPage['props'],
            'Props must be an array.',
        );

        unset($expectedPage['props']['private']);

        $expectedPage['url'] = '/page';

        self::assertEquals(
            $expectedPage,
            $payload['page'],
            'The sanitized page must drop the private prop and the query string.',
        );
        self::assertArrayHasKey(
            'private',
            $result->page()->props,
            'Sanitization must not mutate the protocol result.',
        );

        $collector->shutdown();
        $collector->shutdown();

        $protocol->page(
            $request,
            new PageInput(
                'Changed',
                [],
                'v1',
            )
        );

        self::assertNull(
            $collector->capture(),
            'A stopped collector must not observe.',
        );
        self::assertSame(
            'Original',
            (new InertiaPanel())->present($payload)->toolbarMetrics()[0]->value ?? null,
            'A replayed capture must keep its component metric.',
        );
        self::assertSame(
            1,
            $counter->resolutions,
            'A replayed capture must not resolve props again.',
        );

        $collector->startup();

        self::assertNull(
            $collector->capture(),
            'A new cycle must start empty.',
        );

        $collector->shutdown();

        self::assertCount(
            3,
            $dispatcher->events,
            'One event per protocol call.',
        );
    }

    #[DataProviderExternal(InertiaCollectorProvider::class, 'protocolOperations')]
    public function testResultEventsPreserveNegotiation(
        string $operation,
        bool $inertia,
        string $version,
        int $status,
        string|null $location,
        string $type
    ): void {
        $collector = new InertiaCollector(
            static fn(array $page): array => $page,
            static fn(string $url): string => $url,
        );
        $dispatcher = new CollectingEventDispatcherStub([$collector]);

        $protocol = self::protocol($dispatcher);

        $request = new RequestContext(
            $operation === 'redirect' ? 'PUT' : 'GET',
            '/page',
            'https://example.com/page',
            $inertia ? ['X-Inertia' => 'true', 'X-Inertia-Version' => $version] : [],
        );

        $collector->startup();

        $result = match ($operation) {
            'page' => $protocol->page($request, new PageInput('Actual', [], 'v1')),
            'location' => $protocol->location($request, 'https://example.com/target'),
            'fragment' => $protocol->redirect($request, '/target#section'),
            default => $protocol->redirect($request, '/target'),
        };

        $payload = $collector->capture();

        self::assertNotNull(
            $payload,
            'An active cycle must produce a capture.',
        );
        self::assertArrayHasKey(
            'requestHeaders',
            $payload,
            'Request headers must be present.',
        );
        self::assertArrayHasKey(
            'sharedKeys',
            $payload,
            'Shared keys must be present.',
        );
        self::assertArrayHasKey(
            'statusCode',
            $payload,
            'Status code must be present.',
        );
        self::assertArrayHasKey(
            'location',
            $payload,
            'Location must be present.',
        );
        self::assertArrayHasKey(
            'page',
            $payload,
            'Page key must be present.',
        );
        self::assertArrayHasKey(
            'resultType',
            $payload,
            'Result type must be present.',
        );
        self::assertSame(
            $status,
            $payload['statusCode'],
            'Status must match the negotiated result.',
        );
        self::assertSame(
            $result->statusCode(),
            $payload['statusCode'],
            'Captured status must match the result itself.',
        );
        self::assertSame(
            $location,
            $payload['location'],
            'Navigation target must be persisted verbatim.',
        );
        self::assertSame(
            $type,
            $payload['resultType'],
            'Result type must distinguish the navigation kind.',
        );

        if (in_array($type, ['location', 'redirect', 'fragment-redirect'], true)) {
            [$label, $title] = match ($type) {
                'location' => ['External location', 'External location visit'],
                'fragment-redirect' => ['Fragment redirect', 'Fragment redirect'],
                default => ['Redirect', 'Redirect'],
            };

            $expected = \PHPForge\Debug\PanelView::create()
                ->summary('', '—')
                ->summary('', $label, emphasized: false)
                ->active($inertia)
                ->emptyState(
                    $title,
                    'The protocol returned a navigation result without resolving a page.',
                    ['Navigation target: ', \PHPForge\Debug\PanelView::code($location ?? '—')],
                );

            self::assertEquals(
                $expected,
                (new InertiaPanel())->present($payload),
                'A navigation capture must render the matching empty state.',
            );
        }

        self::assertEquals(
            $result instanceof PageResult ? $result->page()->toArray() : null,
            $payload['page'],
            'Only a page result may carry a page.',
        );
        self::assertSame(
            [],
            $payload['sharedKeys'],
            'A navigation result has no shared keys.',
        );
        self::assertSame(
            $inertia ? ['X-Inertia' => 'true', 'X-Inertia-Version' => $version] : [],
            $payload['requestHeaders'],
            'Only an Inertia request contributes headers.',
        );

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'A stopped collector must not observe.',
        );
        self::assertCount(
            1,
            $dispatcher->events,
            'One event per protocol call.',
        );
    }

    public function testSanitizerFailureIsDeferredToTheHostCaptureBoundary(): void
    {
        $failure = new RuntimeException('sanitize failed');
        $collector = new InertiaCollector(
            static fn(array $page): never => throw $failure,
            static fn(string $url): string => $url,
        );
        $dispatcher = new CollectingEventDispatcherStub([$collector]);

        $protocol = self::protocol($dispatcher);

        $collector->startup();

        $result = $protocol->page(
            new RequestContext(
                'GET',
                '/',
                'https://example.com/',
            ),
            new PageInput(
                'Actual',
                [],
                'v1',
            ),
        );

        self::assertInstanceOf(
            PageResult::class,
            $result,
            'The protocol operation must succeed.',
        );

        $this->expectExceptionObject($failure);

        try {
            $collector->capture();
        } finally {
            $collector->shutdown();
            self::assertNull(
                $collector->capture(),
                'Shutdown must clear the buffered result.',
            );
        }

        self::assertCount(
            1,
            $dispatcher->events,
            'One event per protocol call.',
        );
    }

    public function testUnknownStoredResultTypeIsNotInterpreted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            Message::DIAGNOSTICS_RESULT_TYPE_INVALID->getMessage(),
        );

        (new InertiaPanel())->present(['resultType' => ['class' => 'arbitrary']]);
    }

    public function testUnsupportedExternalResultFailsAtCapture(): void
    {
        $collector = new InertiaCollector(
            static fn(array $page): array => $page,
            static fn(string $url): string => $url,
        );

        $collector->startup();

        $collector(
            new ProtocolResultCreated(
                new RequestContext(
                    'GET',
                    '/',
                    'https://example.com/',
                ),
                new class implements \PHPForge\Inertia\Result\ProtocolResult {
                    public function headers(): array
                    {
                        return [];
                    }

                    public function statusCode(): int
                    {
                        return 200;
                    }
                }
            )
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            Message::DIAGNOSTICS_RESULT_UNSUPPORTED->getMessage(),
        );

        try {
            $collector->capture();
        } finally {
            $collector->shutdown();
        }
    }

    /**
     * Builds a protocol dispatching through the supplied recording dispatcher.
     *
     * @param CollectingEventDispatcherStub $dispatcher Dispatcher recording every dispatched event.
     *
     * @return Protocol Protocol dispatching through the recording dispatcher.
     */
    private static function protocol(CollectingEventDispatcherStub $dispatcher): Protocol
    {
        return Protocol::create(eventDispatcher: $dispatcher);
    }
}
