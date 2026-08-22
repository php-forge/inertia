<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests;

use PHPForge\Inertia\Exception\{InvalidPropException, Message};
use PHPForge\Inertia\Prop\{AlwaysProp, DeferredProp, MergeProp, OnceProp, ScrollMetadata, ScrollProp};
use PHPForge\Inertia\Resolution\PropDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for public prop-definition inspection and composition.
 */
#[Group('resolution')]
final class ResolutionTest extends TestCase
{
    public function testMergeCombinesAlwaysFlagsFromBothDefinitions(): void
    {
        $always = PropDefinition::from(new AlwaysProp('outer'));
        $plain = PropDefinition::from('inner');
        $alsoAlways = PropDefinition::from(new AlwaysProp('inner'));

        self::assertTrue(
            $always->mergeWith($plain)->always,
            'An outer always flag must survive definition merging.',
        );
        self::assertTrue(
            $always->mergeWith($alsoAlways)->always,
            'Two always flags must remain enabled after definition merging.',
        );
    }

    public function testMergePrefersInnerConcreteModifiers(): void
    {
        $outerDeferred = new DeferredProp(static fn(): string => 'outer', 'outer');
        $innerDeferred = new DeferredProp(static fn(): string => 'inner', 'inner');
        $outerMerge = new MergeProp(['outer']);
        $innerMerge = new MergeProp(['inner']);
        $outerOnce = new OnceProp(static fn(): string => 'outer', 'outer');
        $innerOnce = new OnceProp(static fn(): string => 'inner', 'inner');
        $metadata = new ScrollMetadata('page', null, null, 1);
        $outerScroll = new ScrollProp(['outer'], $metadata, 'outer');
        $innerScroll = new ScrollProp(['inner'], $metadata, 'inner');

        self::assertSame(
            $innerDeferred,
            PropDefinition::from($outerDeferred)->mergeWith(PropDefinition::from($innerDeferred))->deferred,
            'An inner deferred modifier must replace the outer modifier.',
        );
        self::assertSame(
            $innerMerge,
            PropDefinition::from($outerMerge)->mergeWith(PropDefinition::from($innerMerge))->merge,
            'An inner merge modifier must replace the outer modifier.',
        );
        self::assertSame(
            $innerOnce,
            PropDefinition::from($outerOnce)->mergeWith(PropDefinition::from($innerOnce))->once,
            'An inner once modifier must replace the outer modifier.',
        );
        self::assertSame(
            $innerScroll,
            PropDefinition::from($outerScroll)->mergeWith(PropDefinition::from($innerScroll))->scroll,
            'An inner scroll modifier must replace the outer modifier.',
        );
    }

    public function testPlainValueUsesEmptyDefinitionFlags(): void
    {
        $definition = PropDefinition::from('value');

        self::assertFalse(
            $definition->always,
            'A plain value must not be marked as always included.',
        );
        self::assertFalse(
            $definition->optional,
            'A plain value must not be marked as optional.',
        );
    }

    public function testPropDefinitionInspectsSixtyFourNestedWrappers(): void
    {
        $withinLimit = PropDefinition::from(self::nestedAlwaysProps(64));

        self::assertSame(
            'leaf',
            $withinLimit->base,
            'Exactly 64 nested prop wrappers must be fully inspected.',
        );
    }

    public function testThrowInvalidPropExceptionWhenPropDefinitionExceedsWrapperLimit(): void
    {
        $this->expectException(InvalidPropException::class);
        $this->expectExceptionMessage(
            Message::PROP_WRAPPER_DEPTH_EXCEEDED->getMessage(),
        );

        PropDefinition::from(self::nestedAlwaysProps(65));
    }

    private static function nestedAlwaysProps(int $depth): mixed
    {
        $value = 'leaf';

        for ($level = 0; $level < $depth; ++$level) {
            $value = new AlwaysProp($value);
        }

        return $value;
    }
}
