<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use Closure;
use PHPForge\Inertia\Exception\{InvalidPropException, Message};

use function explode;
use function in_array;
use function preg_match;

final readonly class ScrollProp implements PropValue
{
    /**
     * @param ScrollMetadata|(Closure(mixed): mixed) $metadata
     */
    public function __construct(
        private mixed $value,
        private ScrollMetadata|Closure $metadata,
        private string $wrapper = 'data',
    ) {
        if (
            preg_match('/[\x00-\x1F\x7F,]/', $wrapper) === 1
            || in_array('', explode('.', $wrapper), true)
        ) {
            throw new InvalidPropException(
                Message::SCROLL_WRAPPER_INVALID->getMessage(),
            );
        }
    }

    public function defer(string $group = 'default', bool $rescue = false): DeferredProp
    {
        return new DeferredProp($this, $group, $rescue);
    }

    /**
     * @return ScrollMetadata|(Closure(mixed): mixed)
     */
    public function metadata(): ScrollMetadata|Closure
    {
        return $this->metadata;
    }

    public function once(): OnceProp
    {
        return new OnceProp($this);
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function wrapper(): string
    {
        return $this->wrapper;
    }
}
