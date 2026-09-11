<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Provider;

use PHPForge\Inertia\Exception\Message;

use function array_replace;

/**
 * Data provider for {@see \PHPForge\Inertia\Tests\Debug\InertiaPanelTest} test cases.
 */
final class InertiaPanelProvider
{
    /**
     * Builds one valid decoded capture.
     *
     * @param array<string, mixed> $overrides Fields replacing the valid ones.
     *
     * @return array<string, mixed> Decoded Inertia diagnostics.
     */
    public static function capture(array $overrides = []): array
    {
        return array_replace(
            [
                'page' => null,
                'requestHeaders' => [],
                'sharedKeys' => [],
                'statusCode' => 200,
                'location' => null,
            ],
            $overrides,
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function malformedCaptures(): iterable
    {
        $invalid = Message::DIAGNOSTICS_INVALID->getMessage();

        yield 'capture carries only a status code' => [['statusCode' => '200'], $invalid];
        yield 'location is not a string' => [self::capture(['location' => 42]), $invalid];
        yield 'request headers are not an array' => [self::capture(['requestHeaders' => false]), $invalid];
        yield 'shared keys are not an array' => [self::capture(['sharedKeys' => false]), $invalid];
        yield 'status code is not an integer' => [self::capture(['statusCode' => '200']), $invalid];
    }
}
