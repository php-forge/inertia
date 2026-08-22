<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use Closure;
use PHPForge\Inertia\Exception\{InvalidPropException, Message};

final readonly class DeferredProp implements PropValue
{
    /**
     * @param (Closure(): mixed)|PropValue $value
     */
    public function __construct(
        private Closure|PropValue $value,
        private string $group = 'default',
        private bool $rescuesFailures = false,
    ) {
        self::validateGroup($group);
    }

    public function deepMerge(): MergeProp
    {
        return (new MergeProp($this))->deepMerge();
    }

    public function group(): string
    {
        return $this->group;
    }

    public function merge(): MergeProp
    {
        return new MergeProp($this);
    }

    public function once(): OnceProp
    {
        return new OnceProp($this);
    }

    public function rescue(bool $enabled = true): self
    {
        return new self($this->value, $this->group, $enabled);
    }

    public function rescuesFailures(): bool
    {
        return $this->rescuesFailures;
    }

    /**
     * @return (Closure(): mixed)|PropValue
     */
    public function value(): Closure|PropValue
    {
        return $this->value;
    }

    private static function validateGroup(string $group): void
    {
        if ($group === '' || preg_match('/[\x00-\x1F\x7F,]/', $group) === 1) {
            throw new InvalidPropException(
                Message::DEFERRED_PROP_GROUP_INVALID->getMessage(),
            );
        }
    }
}
