<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests;

use ArrayObject;
use Closure;
use PHPForge\Inertia\Exception\{InvalidPageInputException, Message, PropResolutionException};
use PHPForge\Inertia\{PageInput, Protocol};
use PHPForge\Inertia\Prop\{OptionalProp, Prop};
use PHPForge\Inertia\RequestContext;
use PHPForge\Inertia\Result\{InertiaPageResult, InitialPageResult, VersionConflictResult};
use PHPForge\Inertia\Tests\Fixture\{CallRecorder, JsonSerializableStub};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see Protocol} page negotiation and prop resolution.
 */
#[Group('protocol')]
#[Group('page')]
final class ProtocolPageTest extends TestCase
{
    public function testCallbackCanReturnOptionalPropWithoutResolvingItsValueOnFullVisit(): void
    {
        $calls = new CallRecorder();
        $result = (new Protocol())
            ->page(
                self::request(),
                new PageInput(
                    'Dashboard',
                    [
                        'dynamic' => static fn(): OptionalProp => Prop::optional(
                            static function () use ($calls): string {
                                $calls->record();

                                return 'hidden';
                            },
                        ),
                    ],
                    'v1',
                ),
            );

        self::assertInstanceOf(
            InitialPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            ['errors' => []],
            $result->page()->props,
            'Resolved page props must match the expected value.',
        );
        self::assertSame(
            [],
            $calls->recordedCalls(),
            'Recorded callback list must match the expected value.',
        );
    }

    public function testExpandsJsonSerializableAndObjectProps(): void
    {
        $result = (new Protocol())
            ->page(
                self::request(),
                new PageInput(
                    'Dashboard',
                    [
                        'serializable' => new JsonSerializableStub(['ratio' => 1.5]),
                        'object' => (object) ['name' => 'Ada'],
                    ],
                    'v1',
                ),
            );

        self::assertInstanceOf(
            InitialPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            [
                'serializable' => ['ratio' => 1.5],
                'object' => ['name' => 'Ada'],
                'errors' => [],
            ],
            $result->page()->props,
            'Resolved page props must match the expected value.',
        );
    }

    public function testExposesDistinctSharedRootKeysForDotNotationProps(): void
    {
        $result = (new Protocol())
            ->page(
                self::request(),
                new PageInput(
                    'Dashboard',
                    [],
                    'v1',
                    sharedProps: [
                        'auth.user' => ['id' => 1],
                        'auth.permissions' => ['view'],
                        'settings.theme' => 'dark',
                    ],
                ),
            );

        self::assertInstanceOf(
            InitialPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            ['auth', 'settings'],
            $result->page()->sharedProps,
            'Shared metadata must expose unique root keys from dot notation.',
        );
    }

    public function testHidesSharedPropMetadataWhenConfigured(): void
    {
        $result = (new Protocol())
            ->page(
                self::request(),
                new PageInput(
                    'Dashboard',
                    [],
                    'v1',
                    sharedProps: ['auth.user' => ['id' => 1]],
                    exposeSharedProps: false,
                ),
            );

        self::assertInstanceOf(
            InitialPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            [],
            $result->page()->sharedProps,
            'Shared prop metadata must match the expected value.',
        );
        self::assertSame(
            ['auth' => ['user' => ['id' => 1]], 'errors' => []],
            $result->page()->props,
            'Resolved page props must match the expected value.',
        );
    }

    public function testMapsErrorsIntoRequestedBag(): void
    {
        $result = (new Protocol())
            ->page(
                self::request(
                    [
                        'X-Inertia' => 'true',
                        'X-Inertia-Error-Bag' => 'profile',
                    ],
                ),
                new PageInput('Dashboard', [], 'v1', errors: ['email' => ['Invalid', 'Required']]),
            );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            ['errors' => ['profile' => ['email' => ['Invalid', 'Required']]]],
            $result->page()->props,
            'Resolved page props must match the expected value.',
        );
    }

    public function testNumericVersionHeaderMatchesIntegerPageVersion(): void
    {
        $result = (new Protocol())
            ->page(
                self::request(
                    [
                        'X-Inertia' => 'true',
                        'X-Inertia-Version' => '7',
                    ],
                ),
                new PageInput('Dashboard', [], 7),
            );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $result,
            'A numeric version header must match the equivalent integer page version.',
        );
    }

    public function testOptionalAndDeferredPropsResolveOnlyWhenPartiallyRequested(): void
    {
        $calls = new CallRecorder();
        $input = new PageInput(
            'Reports',
            [
                'summary' => Prop::optional(static function () use ($calls): string {
                    $calls->record('summary');

                    return 'ready';
                }),
                'metrics' => Prop::defer(static function () use ($calls): array {
                    $calls->record('metrics');

                    return ['visits' => 100];
                }, 'analytics'),
            ],
            'v1',
        );

        $full = (new Protocol())->page(self::request(['X-Inertia' => 'true']), $input);

        self::assertInstanceOf(
            InertiaPageResult::class,
            $full,
            'Full must use the expected type.',
        );
        self::assertSame(
            ['errors' => []],
            $full->page()->props,
            'Resolved page props must match the expected value.',
        );
        self::assertSame(
            ['analytics' => ['metrics']],
            $full->page()->deferredProps,
            'Deferred prop metadata must match the expected value.',
        );
        self::assertSame(
            [],
            $calls->recordedCalls(),
            'Recorded callback list must match the expected value.',
        );

        $partial = (new Protocol())->page(
            self::request(
                [
                    'X-Inertia' => 'true',
                    'X-Inertia-Partial-Component' => 'Reports',
                    'X-Inertia-Partial-Data' => 'summary,metrics',
                ],
                '/reports',
            ),
            $input,
        );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $partial,
            'Partial must use the expected type.',
        );
        self::assertSame(
            [
                'summary' => 'ready',
                'metrics' => ['visits' => 100],
                'errors' => [],
            ],
            $partial->page()->props,
            'Resolved page props must match the expected value.',
        );
        self::assertSame(
            [],
            $partial->page()->deferredProps,
            'Deferred prop metadata must match the expected value.',
        );
        self::assertSame(
            ['summary', 'metrics'],
            $calls->recordedCalls(),
            'Recorded callback list must match the expected value.',
        );
    }

    public function testPartialOnlyAndExceptRemainLazyAndHonorAlwaysProps(): void
    {
        $calls = new CallRecorder();

        $request = self::request(
            [
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => 'Dashboard',
                'X-Inertia-Partial-Data' => 'stats,settings.theme',
                'X-Inertia-Partial-Except' => 'stats.private',
            ],
        );

        $result = (new Protocol())
            ->page(
                $request,
                new PageInput(
                    'Dashboard',
                    [
                        'stats' => [
                            'public' => static function () use ($calls): int {
                                $calls->record('public');

                                return 10;
                            },
                            'private' => static function () use ($calls): int {
                                $calls->record('private');

                                return 99;
                            },
                        ],
                        'settings.theme' => static function () use ($calls): string {
                            $calls->record('theme');

                            return 'dark';
                        },
                        'unselected' => static function () use ($calls): string {
                            $calls->record('unselected');

                            return 'never';
                        },
                        'nested' => [
                            'ordinary' => static function () use ($calls): string {
                                $calls->record('ordinary');

                                return 'never';
                            },
                            'token' => Prop::always(static fn(): string => 'csrf-token'),
                        ],
                        'plainNested' => [
                            'ordinary' => static function () use ($calls): string {
                                $calls->record('plain-nested');

                                return 'never';
                            },
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
            [
                'stats' => ['public' => 10],
                'settings' => ['theme' => 'dark'],
                'nested' => ['token' => 'csrf-token'],
                'errors' => [],
            ],
            $result->page()->props,
            'Resolved page props must match the expected value.',
        );
        self::assertSame(
            ['public', 'theme'],
            $calls->recordedCalls(),
            'Recorded callback list must match the expected value.',
        );
    }

    public function testPartialPathMatchingUsesCompleteDotSegments(): void
    {
        $result = (new Protocol())
            ->page(
                self::request(
                    [
                        'X-Inertia' => 'true',
                        'X-Inertia-Partial-Component' => 'Dashboard',
                        'X-Inertia-Partial-Data' => 'user,teamwork.profile,account',
                        'X-Inertia-Partial-Except' => 'account.secret',
                    ],
                ),
                new PageInput(
                    'Dashboard',
                    [
                        'user' => 'Ada',
                        'users' => 'not selected',
                        'team' => ['profile' => 'not selected'],
                        'account' => [
                            'profile' => 'visible',
                            'secret' => 'hidden',
                            'secrets' => 'visible',
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
            [
                'user' => 'Ada',
                'account' => ['profile' => 'visible', 'secrets' => 'visible'],
                'errors' => [],
            ],
            $result->page()->props,
            'Partial path matching must not cross dot-notation segment boundaries.',
        );
    }

    public function testProducesInertiaPageResult(): void
    {
        $result = (new Protocol())->page(
            self::request(['X-Inertia' => 'true']),
            new PageInput('Dashboard', ['answer' => 42], 'v1'),
        );

        self::assertInstanceOf(
            InertiaPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            200,
            $result->statusCode(),
            'Status code must match the expected value.',
        );
        self::assertSame(
            ['X-Inertia' => 'true', 'Vary' => 'X-Inertia'],
            $result->headers(),
            'Result headers must match the expected value.',
        );
    }
    public function testProducesInitialPageWithExplicitInputs(): void
    {
        $result = (new Protocol())
            ->page(
                self::request(),
                new PageInput(
                    component: 'Dashboard',
                    props: [
                        'answer' => static fn(): int => 42,
                        'profile.name' => 'Ada',
                    ],
                    version: 'v1',
                    sharedProps: ['auth' => ['id' => 7]],
                    errors: ['email' => 'Invalid'],
                    flash: ['message' => 'Saved', 'level' => 'success'],
                    encryptHistory: true,
                    clearHistory: true,
                    preserveFragment: true,
                ),
            );

        self::assertInstanceOf(
            InitialPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            200,
            $result->statusCode(),
            'Status code must match the expected value.',
        );
        self::assertSame(
            [],
            $result->rescuedFailures(),
            'Rescued failures must match the expected value.',
        );
        self::assertSame(
            ['Vary' => 'X-Inertia'],
            $result->headers(),
            'Result headers must match the expected value.',
        );
        self::assertSame(
            [
                'auth' => ['id' => 7],
                'answer' => 42,
                'profile' => ['name' => 'Ada'],
                'errors' => ['email' => 'Invalid'],
            ],
            $result->page()->props,
            'Resolved page props must match the expected value.',
        );
        self::assertSame(
            ['auth'],
            $result->page()->sharedProps,
            'Shared prop metadata must match the expected value.',
        );
        self::assertSame(
            ['message' => 'Saved', 'level' => 'success'],
            $result->page()->flash,
            'Flash data must match the expected value.',
        );
        self::assertTrue(
            $result->page()->encryptHistory,
            'Encrypt-history flag must be `true`.',
        );
        self::assertTrue(
            $result->page()->clearHistory,
            'Clear-history flag must be `true`.',
        );
        self::assertTrue(
            $result->page()->preserveFragment,
            'Preserve-fragment flag must be `true`.',
        );
    }

    public function testResolvedCallbackArrayIncludesAllChildrenOnNestedPartialRequest(): void
    {
        $result = (new Protocol())
            ->page(
                self::request(
                    [
                        'X-Inertia' => 'true',
                        'X-Inertia-Partial-Component' => 'Dashboard',
                        'X-Inertia-Partial-Data' => 'profile.name',
                    ],
                ),
                new PageInput(
                    'Dashboard',
                    [
                        'profile' => static fn(): array => [
                            'name' => 'Ada',
                            'role' => 'administrator',
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
            [
                'profile' => ['name' => 'Ada', 'role' => 'administrator'],
                'errors' => [],
            ],
            $result->page()->props,
            'A selected callback result must include its complete resolved array.',
        );
    }

    public function testResolvesSixtyFourNestedCallbacks(): void
    {
        $result = (new Protocol())->page(
            self::request(),
            new PageInput('Dashboard', ['nested' => self::nestedCallbacks(64)], 'v1'),
        );

        self::assertInstanceOf(
            InitialPageResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            ['nested' => 'leaf', 'errors' => []],
            $result->page()->props,
            'Exactly 64 nested callbacks must resolve to their final value.',
        );
    }

    public function testReturnsVersionConflictBeforeResolvingProps(): void
    {
        $resolved = false;
        $result = (new Protocol())
            ->page(
                self::request(
                    [
                        'X-Inertia' => 'true',
                        'X-Inertia-Version' => 'old',
                    ],
                ),
                new PageInput(
                    'Dashboard',
                    ['expensive' => static function () use (&$resolved): int {
                        $resolved = true;

                        return 42;
                    }],
                    'current',
                ),
            );

        self::assertInstanceOf(
            VersionConflictResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertFalse(
            $resolved,
            'Callback resolution flag must be `false`.',
        );
        self::assertSame(
            409,
            $result->statusCode(),
            'Status code must match the expected value.',
        );
        self::assertSame(
            [
                'X-Inertia-Location' => 'https://example.com/dashboard',
                'X-Inertia-Version' => 'current',
                'Vary' => 'X-Inertia',
            ],
            $result->headers(),
            'Result headers must match the expected value.',
        );
        self::assertArrayNotHasKey(
            'X-Inertia',
            $result->headers(),
            'Result headers must not contain the unexpected key.',
        );
    }

    public function testThrowInvalidPageInputExceptionForConflictingDotNotationProps(): void
    {
        $this->expectException(InvalidPageInputException::class);
        $this->expectExceptionMessage(
            Message::PROP_PATH_CONFLICT->getMessage('profile.name', 'profile'),
        );

        (new Protocol())
            ->page(
                self::request(),
                new PageInput(
                    'Dashboard',
                    [
                        'profile' => 'not-an-array',
                        'profile.name' => 'Ada',
                    ],
                    'v1',
                ),
            );
    }

    public function testThrowPropResolutionExceptionForCallbackFailure(): void
    {
        try {
            (new Protocol())
                ->page(
                    self::request(),
                    new PageInput(
                        'Dashboard',
                        [
                            'profile' => [
                                'avatar' => static fn(): never => throw new RuntimeException('Unavailable'),
                            ],
                        ],
                        'v1',
                    ),
                );

            self::fail(
                'Expected prop resolution to fail.',
            );
        } catch (PropResolutionException $exception) {
            self::assertSame(
                'profile.avatar',
                $exception->propPath(),
                'Prop path must match the expected value.',
            );
            self::assertSame(
                'Unavailable',
                $exception->getPrevious()?->getMessage(),
                'Failure message must match the expected value.',
            );
        }
    }

    public function testThrowPropResolutionExceptionForInvalidFlashData(): void
    {
        $resource = fopen('php://memory', 'rb');

        if ($resource === false) {
            self::fail(
                'Expected the memory stream to open.',
            );
        }

        try {
            (new Protocol())
                ->page(
                    self::request(),
                    new PageInput('Dashboard', [], 'v1', flash: ['resource' => $resource]),
                );

            self::fail(
                'Expected flash normalization to fail.',
            );
        } catch (PropResolutionException $exception) {
            self::assertSame(
                'flash.resource',
                $exception->propPath(),
                'Prop path must match the expected value.',
            );
        } finally {
            fclose($resource);
        }
    }

    public function testThrowPropResolutionExceptionForNonJsonPropValue(): void
    {
        $this->expectException(PropResolutionException::class);
        $this->expectExceptionMessage(
            Message::PROP_RESOLUTION_FAILED->getMessage(
                'invalid',
                Message::JSON_VALUE_INVALID->getMessage('invalid'),
            ),
        );

        (new Protocol())
            ->page(
                self::request(),
                new PageInput('Dashboard', ['invalid' => new ArrayObject()], 'v1'),
            );
    }

    public function testThrowPropResolutionExceptionWhenCallbackLimitIsExceeded(): void
    {
        $this->expectException(PropResolutionException::class);
        $this->expectExceptionMessage(
            Message::PROP_RESOLUTION_FAILED->getMessage(
                'nested',
                Message::PROP_RESOLVER_DEPTH_EXCEEDED->getMessage('nested'),
            ),
        );

        (new Protocol())
            ->page(
                self::request(),
                new PageInput('Dashboard', ['nested' => self::nestedCallbacks(65)], 'v1'),
            );
    }

    public function testVersionHeaderDoesNotConflictForNonGetRequests(): void
    {
        $request = new RequestContext(
            'POST',
            '/dashboard',
            'https://example.com/dashboard',
            [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => 'old',
            ],
        );
        $result = (new Protocol())
            ->page($request, new PageInput('Dashboard', [], 'current'));

        self::assertInstanceOf(
            InertiaPageResult::class,
            $result,
            'Result must use the expected type.',
        );
    }

    /**
     * @return Closure(): mixed
     */
    private static function nestedCallbacks(int $depth): Closure
    {
        $value = static fn(): string => 'leaf';

        for ($level = 1; $level < $depth; ++$level) {
            $next = $value;
            $value = static fn(): mixed => $next;
        }

        return $value;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function request(array $headers = [], string $url = '/dashboard'): RequestContext
    {
        return new RequestContext('GET', $url, 'https://example.com' . $url, $headers);
    }
}
