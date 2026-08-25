<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use Closure;
use PHPForge\Inertia\Exception\{InvalidPropException, Message};

use function explode;
use function in_array;
use function preg_match;

/**
 * Wraps paginated data with infinite-scroll metadata and its client-side merge path.
 */
final readonly class ScrollProp implements PropValue
{
    /**
     * @param mixed $value The paginated data value.
     * @param (Closure(mixed): mixed)|ScrollMetadata $metadata Static metadata or a closure receiving the resolved value.
     * @param string $wrapper The client-side key under which the paginated data is nested.
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

    /**
     * Returns a new {@see DeferredProp} wrapping this scroll prop.
     *
     * @param string $group Deferred-loading group name.
     * @param bool $rescue Whether to suppress resolution failures instead of re-throwing.
     *
     * @return DeferredProp A deferred prop wrapping this scroll prop.
     */
    public function defer(string $group = 'default', bool $rescue = false): DeferredProp
    {
        return new DeferredProp($this, $group, $rescue);
    }

    /**
     * Returns the scroll metadata or a closure that produces it from the resolved value.
     *
     * @return (Closure(mixed): mixed)|ScrollMetadata Static metadata, or a closure deriving it from the value.
     */
    public function metadata(): ScrollMetadata|Closure
    {
        return $this->metadata;
    }

    /**
     * Returns a new {@see OnceProp} wrapping this scroll prop.
     *
     * @return OnceProp A once prop wrapping this scroll prop.
     */
    public function once(): OnceProp
    {
        return new OnceProp($this);
    }

    /**
     * Returns the paginated data value.
     *
     * @return mixed The paginated data value.
     */
    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * Returns the client-side key under which the paginated data is nested.
     *
     * @return string The client-side key under which the paginated data is nested.
     */
    public function wrapper(): string
    {
        return $this->wrapper;
    }
}
