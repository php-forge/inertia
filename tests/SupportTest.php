<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests;

use ArrayObject;
use PHPForge\Inertia\Exception\{InvalidPropException, Message};
use PHPForge\Inertia\Support\{DotArray, JsonValue};
use PHPForge\Inertia\Tests\Fixture\JsonSerializableStub;
use PHPForge\Inertia\Tests\Provider\SupportProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for public dot-array expansion and JSON value normalization helpers.
 *
 * {@see SupportProvider} for test case data providers.
 */
#[Group('support')]
final class SupportTest extends TestCase
{
    /**
     * @param 'array'|'json-serializable'|'object' $kind
     */
    #[DataProviderExternal(SupportProvider::class, 'containerKinds')]
    public function testAcceptsMaximumJsonNestingDepth(string $kind, int $maximumDepth): void
    {
        $value = self::nestedValue($kind, $maximumDepth, 'leaf');

        self::assertNotNull(
            JsonValue::normalize($value, 'root'),
            'A value at the maximum JSON nesting depth must remain valid.',
        );
    }

    public function testExpandsDotPathsWithoutDiscardingSiblings(): void
    {
        self::assertSame(
            [
                'profile' => ['name' => 'Ada', 'age' => 37],
                'settings' => ['theme' => 'dark'],
                'organization' => [
                    'department' => [
                        'team' => ['name' => 'Core', 'size' => 3],
                    ],
                ],
            ],
            DotArray::expand([
                'profile.name' => 'Ada',
                'profile.age' => 37,
                'settings.theme' => 'dark',
                'organization.department.team.name' => 'Core',
                'organization.department.team.size' => 3,
            ]),
            'Dot expansion must retain siblings at every nesting level.',
        );
    }

    public function testThrowInvalidPropExceptionForInvalidNestedJsonValue(): void
    {
        $this->expectException(InvalidPropException::class);
        $this->expectExceptionMessage(
            Message::JSON_VALUE_INVALID->getMessage('root.first.second'),
        );

        JsonValue::normalize(['first' => ['second' => new ArrayObject()]], 'root');
    }

    public function testThrowInvalidPropExceptionForInvalidUtf8(): void
    {
        $this->expectException(InvalidPropException::class);
        $this->expectExceptionMessage(
            Message::JSON_UTF8_INVALID->getMessage('name'),
        );

        JsonValue::normalize("\xB1\x31", 'name');
    }

    /**
     * @param 'array'|'json-serializable'|'object' $kind
     */
    #[DataProviderExternal(SupportProvider::class, 'containerKinds')]
    public function testThrowInvalidPropExceptionWhenJsonNestingDepthIsExceeded(
        string $kind,
        int $maximumDepth,
    ): void {
        $path = match ($kind) {
            'array' => 'root' . str_repeat('.value', $maximumDepth + 1),
            'json-serializable' => 'root',
            'object' => 'root' . str_repeat('.value', $maximumDepth),
        };

        $this->expectException(InvalidPropException::class);
        $this->expectExceptionMessage(
            Message::JSON_DEPTH_EXCEEDED->getMessage($path),
        );

        JsonValue::normalize(self::nestedValue($kind, $maximumDepth + 1, 'leaf'), 'root');
    }

    /**
     * @param 'array'|'json-serializable'|'object' $kind
     */
    private static function nestedValue(string $kind, int $depth, mixed $leaf): mixed
    {
        $value = $leaf;

        for ($level = 0; $level < $depth; ++$level) {
            $value = match ($kind) {
                'array' => ['value' => $value],
                'json-serializable' => new JsonSerializableStub($value),
                'object' => (object) ['value' => $value],
            };
        }

        return $value;
    }
}
