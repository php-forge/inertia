<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use Closure;
use PHPForge\Inertia\Exception\{InvalidPropException, Message};

use function preg_match;

/**
 * Wraps a value whose evaluation is deferred until the client requests it through a partial reload.
 */
final readonly class DeferredProp implements PropValue
{
    /**
     * @param (Closure(): mixed)|PropValue $value The value to defer, either a closure or another prop.
     * @param string $group The group name for this deferred prop.
     * @param bool $rescuesFailures Whether to rescue callback failures.
     */
    public function __construct(
        private Closure|PropValue $value,
        private string $group = 'default',
        private bool $rescuesFailures = false,
    ) {
        self::validateGroup($group);
    }

    /**
     * Returns a new DeferredProp that merges its value with other props in the same group.
     *
     * @return MergeProp A new MergeProp that merges its value with other props in the same group.
     */
    public function deepMerge(): MergeProp
    {
        return (new MergeProp($this))->deepMerge();
    }

    /**
     * Returns the group name for this deferred prop.
     *
     * @return string The group name for this deferred prop.
     */
    public function group(): string
    {
        return $this->group;
    }

    /**
     * Returns a new MergeProp that merges its value with other props in the same group.
     *
     * @return MergeProp A new MergeProp that merges its value with other props in the same group.
     */
    public function merge(): MergeProp
    {
        return new MergeProp($this);
    }

    /**
     * Returns a new OnceProp that evaluates its value only once per request.
     *
     * @return OnceProp A new OnceProp that evaluates its value only once per request.
     */
    public function once(): OnceProp
    {
        return new OnceProp($this);
    }

    /**
     * Returns a new DeferredProp that rescues callback failures.
     *
     * @param bool $enabled Whether to rescue callback failures.
     *
     * @return DeferredProp A new DeferredProp that rescues callback failures.
     */
    public function rescue(bool $enabled = true): DeferredProp
    {
        return new self($this->value, $this->group, $enabled);
    }

    /**
     * Returns whether this deferred prop rescues callback failures.
     *
     * @return bool Whether this deferred prop rescues callback failures.
     */
    public function rescuesFailures(): bool
    {
        return $this->rescuesFailures;
    }

    /**
     * Returns the wrapped value.
     *
     * @return (Closure(): mixed)|PropValue The wrapped value.
     */
    public function value(): Closure|PropValue
    {
        return $this->value;
    }

    /**
     * Validates the group name for this deferred prop.
     *
     * @param string $group The group name to validate.
     *
     * @throws InvalidPropException If the group name is invalid.
     */
    private static function validateGroup(string $group): void
    {
        if ($group === '' || preg_match('/[\x00-\x1F\x7F,]/', $group) === 1) {
            throw new InvalidPropException(
                Message::DEFERRED_PROP_GROUP_INVALID->getMessage(),
            );
        }
    }
}
