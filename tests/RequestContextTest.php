<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests;

use PHPForge\Inertia\Exception\{InvalidRequestContextException, Message};
use PHPForge\Inertia\RequestContext;
use PHPForge\Inertia\Tests\Provider\RequestContextProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see RequestContext} normalization, validation, and protocol header parsing.
 *
 * {@see RequestContextProvider} for test case data providers.
 */
#[Group('request')]
final class RequestContextTest extends TestCase
{
    public function testDefaultsOptionalProtocolHeaders(): void
    {
        $context = new RequestContext('POST', '/users', 'http://localhost/users');

        self::assertFalse(
            $context->isGet(),
            'GET request flag must be `false`.',
        );
        self::assertFalse(
            $context->isInertia(),
            'Inertia request flag must be `false`.',
        );
        self::assertFalse(
            $context->isPartialReloadFor('Users/Index'),
            'Partial reload flag must be `false`.',
        );
        self::assertFalse(
            $context->isPrefetch(),
            'Prefetch flag must be `false`.',
        );
        self::assertSame(
            [],
            $context->partialData(),
            'Partial data list must match the expected value.',
        );
        self::assertSame(
            [],
            $context->partialExcept(),
            'Partial exclusion list must match the expected value.',
        );
        self::assertSame(
            [],
            $context->resetProps(),
            'Reset prop list must match the expected value.',
        );
        self::assertSame(
            [],
            $context->exceptOnceProps(),
            'Except-once prop list must match the expected value.',
        );
        self::assertSame(
            'append',
            $context->infiniteScrollMergeIntent(),
            'Infinite-scroll merge intent must match the expected value.',
        );
        self::assertNull(
            $context->errorBag(),
            'Error bag must be `null`.',
        );
        self::assertNull(
            $context->requestVersion(),
            'Request version must be `null`.',
        );
    }

    public function testNormalizesAndParsesProtocolHeaders(): void
    {
        $context = new RequestContext(
            'get',
            '/users?page=2',
            'https://example.com/users?page=2',
            [
                'x-inertia' => 'true',
                'X-Inertia-Partial-Component' => 'Users/Index',
                'X-Inertia-Partial-Data' => ' users, users.profile,users ',
                'X-Inertia-Partial-Except' => 'users.secret',
                'X-Inertia-Reset' => 'users, feed',
                'X-Inertia-Except-Once-Props' => 'countries, roles',
                'X-Inertia-Error-Bag' => 'profile',
                'X-Inertia-Infinite-Scroll-Merge-Intent' => 'PREPEND',
                'Purpose' => 'prefetch',
                'Authorization' => 'not retained',
            ],
        );

        self::assertSame(
            'GET',
            $context->method,
            'Normalized request method must match the expected value.',
        );
        self::assertTrue(
            $context->isGet(),
            'GET request flag must be `true`.',
        );
        self::assertTrue(
            $context->isInertia(),
            'Inertia request flag must be `true`.',
        );
        self::assertTrue(
            $context->isPartialReloadFor('Users/Index'),
            'Partial reload flag must be `true`.',
        );
        self::assertSame(
            ['users', 'users.profile'],
            $context->partialData(),
            'Partial data list must match the expected value.',
        );
        self::assertSame(
            ['users.secret'],
            $context->partialExcept(),
            'Partial exclusion list must match the expected value.',
        );
        self::assertSame(
            ['users', 'feed'],
            $context->resetProps(),
            'Reset prop list must match the expected value.',
        );
        self::assertSame(
            ['countries', 'roles'],
            $context->exceptOnceProps(),
            'Except-once prop list must match the expected value.',
        );
        self::assertSame(
            'profile',
            $context->errorBag(),
            'Error bag must match the expected value.',
        );
        self::assertSame(
            'prepend',
            $context->infiniteScrollMergeIntent(),
            'Infinite-scroll merge intent must match the expected value.',
        );
        self::assertTrue(
            $context->isPrefetch(),
            'Prefetch flag must be `true`.',
        );
        self::assertTrue(
            $context->hasHeader('x-inertia'),
            'Retained header flag must be `true`.',
        );
        self::assertNull(
            $context->header('Authorization'),
            'Requested header value must be `null`.',
        );
    }

    public function testNormalizesWhitespaceCaseAndPublicHeaderLists(): void
    {
        $context = new RequestContext(
            ' get ',
            '/users',
            'HTTPS://example.com/users',
            [
                ' Unsupported ' => 'ignored',
                ' X-Inertia ' => ' TRUE ',
                ' Purpose ' => ' PREFETCH ',
                ' X-Inertia-Error-Bag ' => ' profile ',
                ' X-Inertia-Partial-Data ' => ' users, , users.profile, users ',
                ' X-Inertia-Infinite-Scroll-Merge-Intent ' => ' PREPEND ',
            ],
        );

        self::assertSame(
            'GET',
            $context->method,
            'The request method must be trimmed and uppercased.',
        );
        self::assertTrue(
            $context->hasHeader('X-INERTIA'),
            'Header lookup must be case-insensitive.',
        );
        self::assertTrue(
            $context->isInertia(),
            'Inertia header parsing must ignore surrounding whitespace and case.',
        );
        self::assertTrue(
            $context->isPrefetch(),
            'Purpose header parsing must ignore surrounding whitespace and case.',
        );
        self::assertSame(
            'prepend',
            $context->infiniteScrollMergeIntent(),
            'Merge intent parsing must ignore surrounding whitespace and case.',
        );
        self::assertSame(
            'profile',
            $context->errorBag(),
            'The error bag must be trimmed.',
        );
        self::assertSame(
            ['users', 'users.profile'],
            $context->headerList('X-Inertia-Partial-Data'),
            'A public header list must trim, filter, and deduplicate its values.',
        );

        $blankBag = new RequestContext(
            'GET',
            '/users',
            'https://example.com/users',
            ['X-Inertia-Error-Bag' => '   '],
        );

        self::assertNull(
            $blankBag->errorBag(),
            'A whitespace-only error bag must normalize to `null`.',
        );
    }

    /**
     * @param array<array-key, mixed> $headers
     */
    #[DataProviderExternal(RequestContextProvider::class, 'invalidContexts')]
    public function testThrowInvalidRequestContextExceptionForInvalidContext(
        string $method,
        string $url,
        string $absoluteUrl,
        array $headers,
        Message $message,
    ): void {
        $this->expectException(InvalidRequestContextException::class);
        $this->expectExceptionMessage(
            $message->getMessage(),
        );

        new RequestContext($method, $url, $absoluteUrl, $headers);
    }
}
