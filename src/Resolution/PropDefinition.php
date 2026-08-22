<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Resolution;

use PHPForge\Inertia\Exception\{InvalidPropException, Message};
use PHPForge\Inertia\Prop\{AlwaysProp, DeferredProp, MergeProp, OnceProp, OptionalProp, PropValue, ScrollProp};

final readonly class PropDefinition
{
    private function __construct(
        public mixed $base,
        public bool $always,
        public DeferredProp|null $deferred,
        public bool $optional,
        public MergeProp|null $merge,
        public OnceProp|null $once,
        public ScrollProp|null $scroll,
    ) {}

    public static function from(mixed $value): self
    {
        $always = false;
        $deferred = null;
        $optional = false;
        $merge = null;
        $once = null;
        $scroll = null;
        $depth = 0;

        while ($value instanceof PropValue) {
            if (++$depth > 64) {
                throw new InvalidPropException(
                    Message::PROP_WRAPPER_DEPTH_EXCEEDED->getMessage(),
                );
            }

            match (true) {
                $value instanceof AlwaysProp => $always = true,
                $value instanceof DeferredProp => $deferred = $value,
                $value instanceof OptionalProp => $optional = true,
                $value instanceof MergeProp => $merge = $value,
                $value instanceof OnceProp => $once = $value,
                $value instanceof ScrollProp => $scroll = $value,
                default => null,
            };

            $value = $value->value();
        }

        return new self(
            $value,
            $always,
            $deferred,
            $optional,
            $merge,
            $once,
            $scroll,
        );
    }

    public function mergeWith(self $other): self
    {
        return new self(
            $other->base,
            $this->always || $other->always,
            $other->deferred ?? $this->deferred,
            $this->optional || $other->optional,
            $other->merge ?? $this->merge,
            $other->once ?? $this->once,
            $other->scroll ?? $this->scroll,
        );
    }
}
