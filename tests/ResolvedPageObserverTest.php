<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests;

use PHPForge\Inertia\{Page, PageMetadata, ResolvedPageObserver};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Verifies the framework-neutral resolved-page callback adapter.
 */
final class ResolvedPageObserverTest extends TestCase
{
    public function testForwardsEmptySharedKeys(): void
    {
        $observed = null;
        $observer = new ResolvedPageObserver(
            static function (array $data, array $sharedKeys) use (&$observed): void {
                $observed = $sharedKeys;
            },
        );

        $observer->observe(new Page('Home', [], '/', ''));

        self::assertSame(
            [],
            $observed,
            'A page without shared metadata must forward an empty key list.',
        );
    }

    public function testForwardsPagePayloadAndSharedKeysWithoutMutation(): void
    {
        $page = (
            new Page(
                'Dashboard',
                ['user' => ['name' => 'Ada'], 'errors' => ['email' => 'Invalid']],
                '/dashboard',
                'v1'
            )
        )->withMetadata((new PageMetadata())->withSharedProps(['user']));

        $payload = $page->toArray();

        $observed = [];

        $observer = new ResolvedPageObserver(
            static function (array $data, array $sharedKeys) use (&$observed): void {
                $observed[] = [$data, $sharedKeys];
            },
        );

        $observer->observe($page);
        $observer->observe($page);

        self::assertEquals(
            [[$payload, ['user']], [$payload, ['user']]],
            $observed,
            'Each observation must forward the original payload and shared keys exactly once.',
        );
        self::assertEquals(
            $payload,
            $page->toArray(),
            'Observation must not change the page.',
        );
    }

    public function testPropagatesCallbackFailure(): void
    {
        $observer = new ResolvedPageObserver(
            static function (): never {
                throw new RuntimeException(
                    'Observer failed.',
                );
            },
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Observer failed.',
        );

        $observer->observe(new Page('Home', [], '/', ''));
    }
}
