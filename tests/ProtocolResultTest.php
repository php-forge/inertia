<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests;

use InvalidArgumentException;
use PHPForge\Inertia\Exception\Message;
use PHPForge\Inertia\{Protocol, RequestContext};
use PHPForge\Inertia\Result\{FragmentRedirectResult, LocationResult, RedirectResult};
use PHPForge\Inertia\Tests\Provider\ProtocolResultProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Protocol} framework-neutral redirect and location results.
 *
 * {@see ProtocolResultProvider} for test case data providers.
 */
#[Group('protocol')]
#[Group('result')]
final class ProtocolResultTest extends TestCase
{
    public function testAbsoluteFragmentRedirectRetainsAbsoluteUrl(): void
    {
        $result = Protocol::create()
            ->redirect(
                self::request('GET', ['X-Inertia' => 'true']),
                'https://other.example/users#profile',
            );

        self::assertInstanceOf(
            FragmentRedirectResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            'https://other.example/users#profile',
            $result->url,
            'Result URL must match the expected value.',
        );
    }

    public function testAbsoluteStandardRedirectIsAccepted(): void
    {
        $result = Protocol::create()
            ->redirect(
                self::request('GET'),
                'HTTPS://other.example/users',
            );

        self::assertInstanceOf(
            RedirectResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            'HTTPS://other.example/users',
            $result->url,
            'Result URL must match the expected value.',
        );
    }

    #[DataProviderExternal(ProtocolResultProvider::class, 'redirectStatuses')]
    public function testAcceptsEveryRedirectStatus(int $statusCode): void
    {
        $result = Protocol::create()
            ->redirect(self::request('GET'), '/users', $statusCode);

        self::assertSame(
            $statusCode,
            $result->statusCode(),
            "Redirect status `{$statusCode}` must be preserved.",
        );
    }

    public function testFragmentRedirectPreservesRequestOriginPort(): void
    {
        $request = new RequestContext(
            'GET',
            '/current',
            'https://example.com:8443/current',
            ['X-Inertia' => 'true'],
        );

        $result = Protocol::create()
            ->redirect($request, '/users#profile');

        self::assertInstanceOf(
            FragmentRedirectResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            'https://example.com:8443/users#profile',
            $result->url,
            'A fragment redirect must preserve the request origin port.',
        );
    }

    public function testFragmentRedirectUsesAbsoluteControlUrlForInertia(): void
    {
        $result = Protocol::create()
            ->redirect(
                self::request('GET', ['X-Inertia' => 'true']),
                '/users#profile',
            );

        self::assertInstanceOf(
            FragmentRedirectResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            409,
            $result->statusCode(),
            'Status code must match the expected value.',
        );
        self::assertSame(
            [
                'X-Inertia-Redirect' => 'https://example.com/users#profile',
                'Vary' => 'X-Inertia',
            ],
            $result->headers(),
            'Result headers must match the expected value.',
        );
    }

    public function testGetRedirectRetainsStatusCode(): void
    {
        $result = Protocol::create()
            ->redirect(
                self::request('GET', ['X-Inertia' => 'true']),
                '/users',
                307,
            );

        self::assertInstanceOf(
            RedirectResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            307,
            $result->statusCode(),
            'Status code must match the expected value.',
        );
    }

    public function testLocationUsesConflictControlResponseForInertia(): void
    {
        $result = Protocol::create()
            ->location(
                self::request('GET', ['X-Inertia' => 'true']),
                'https://other.example/path',
            );

        self::assertInstanceOf(
            LocationResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            409,
            $result->statusCode(),
            'Status code must match the expected value.',
        );
        self::assertSame(
            [
                'X-Inertia-Location' => 'https://other.example/path',
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

    public function testLocationUsesStandardRedirectOutsideInertia(): void
    {
        $result = Protocol::create()
            ->location(
                self::request('GET'),
                'https://other.example/path',
            );

        self::assertInstanceOf(
            RedirectResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            302,
            $result->statusCode(),
            'Status code must match the expected value.',
        );
        self::assertSame(
            [
                'Location' => 'https://other.example/path',
                'Vary' => 'X-Inertia',
            ],
            $result->headers(),
            'Result headers must match the expected value.',
        );
    }

    #[DataProviderExternal(ProtocolResultProvider::class, 'inertiaMutationMethods')]
    public function testMutationRedirectBecomesSeeOtherForInertia(string $method, string $message): void
    {
        $result = Protocol::create()
            ->redirect(
                self::request($method, ['X-Inertia' => 'true']),
                '/users',
            );

        self::assertInstanceOf(
            RedirectResult::class,
            $result,
            'An Inertia mutation redirect must return a redirect result.',
        );
        self::assertSame(
            303,
            $result->statusCode(),
            $message,
        );
    }

    public function testPrefetchRetainsNormalFragmentRedirect(): void
    {
        $result = Protocol::create()
            ->redirect(
                self::request(
                    'GET',
                    [
                        'X-Inertia' => 'true',
                        'Purpose' => 'prefetch',
                    ],
                ),
                '/users#profile',
            );

        self::assertInstanceOf(
            RedirectResult::class,
            $result,
            'Result must use the expected type.',
        );
        self::assertSame(
            '/users#profile',
            $result->url,
            'Result URL must match the expected value.',
        );
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProviderExternal(ProtocolResultProvider::class, 'redirectsWithoutSeeOtherConversion')]
    public function testRetainsRedirectStatusOutsideInertiaMutation(
        string $method,
        array $headers,
        int $statusCode,
    ): void {
        $result = Protocol::create()
            ->redirect(self::request($method, $headers), '/users', $statusCode);

        self::assertSame(
            $statusCode,
            $result->statusCode(),
            'A redirect outside the Inertia mutation rule must preserve its status.',
        );
    }

    public function testStandardRedirectDefaultsToFoundStatus(): void
    {
        $result = Protocol::create()
            ->redirect(self::request('GET'), '/users');

        self::assertSame(
            302,
            $result->statusCode(),
            'A standard redirect must default to status `302`.',
        );
    }

    /**
     * @param 'location'|'redirect' $operation
     */
    #[DataProviderExternal(ProtocolResultProvider::class, 'invalidResponses')]
    public function testThrowInvalidArgumentExceptionForInvalidResponse(
        string $operation,
        string $url,
        int $statusCode,
        Message $message,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            $message->getMessage(),
        );

        if ($operation === 'location') {
            Protocol::create()
                ->location(self::request('GET'), $url);

            return;
        }

        Protocol::create()
            ->redirect(self::request('GET'), $url, $statusCode);
    }

    /**
     * @param array<string, string> $headers
     */
    private static function request(string $method, array $headers = []): RequestContext
    {
        return new RequestContext(
            $method,
            '/current?tab=one',
            'https://example.com/current?tab=one',
            $headers,
        );
    }
}
