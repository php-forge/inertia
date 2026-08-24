<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use Closure;
use DateInterval;
use DateTimeInterface;
use PHPForge\Inertia\Exception\{InvalidPropException, Message};

use function is_int;
use function preg_match;

/**
 * Wraps a value that the client may retain and omit from subsequent requests.
 */
final readonly class OnceProp implements PropValue
{
    /**
     * @param (Closure(): mixed)|PropValue $value The value to resolve once.
     * @param string|null $key The cache key to use for this prop. If `null`, a key will be generated from the value's
     * hash.
     * @param DateTimeInterface|DateInterval|int|null $expiration The expiration time for this prop. If `null`, the prop
     * will not expire.
     * @param bool $forceFresh Whether to force a fresh resolution of the value even if the client reports it is cached.
     */
    public function __construct(
        private Closure|PropValue $value,
        private string|null $key = null,
        private DateTimeInterface|DateInterval|int|null $expiration = null,
        private bool $forceFresh = false,
    ) {
        if ($key !== null) {
            self::validateKey($key);
        }

        if (is_int($expiration) && $expiration < 0) {
            throw new InvalidPropException(
                Message::ONCE_PROP_EXPIRATION_INVALID->getMessage(),
            );
        }
    }

    /**
     * Returns a new instance with the given cache key.
     *
     * @param string $key The cache key to use for this prop.
     *
     * @return OnceProp A new OnceProp with the given cache key.
     */
    public function as(string $key): OnceProp
    {
        return new self(
            $this->value,
            $key,
            $this->expiration,
            $this->forceFresh,
        );
    }

    /**
     * Returns the expiration time for this prop.
     *
     * @return DateTimeInterface|DateInterval|int|null The expiration time for this prop, or `null` if it does not
     * expire.
     */
    public function expiration(): DateTimeInterface|DateInterval|int|null
    {
        return $this->expiration;
    }

    /**
     * Returns a new instance with the given freshness setting.
     *
     * @param bool $enabled Whether to force a fresh resolution of the value.
     *
     * @return OnceProp A new OnceProp with the given freshness setting.
     */
    public function fresh(bool $enabled = true): OnceProp
    {
        return new self(
            $this->value,
            $this->key,
            $this->expiration,
            $enabled,
        );
    }

    /**
     * Returns whether this prop is marked as fresh.
     *
     * @return bool Whether this prop is marked as fresh.
     */
    public function isFresh(): bool
    {
        return $this->forceFresh;
    }

    /**
     * Returns the cache key for this prop.
     *
     * @return string|null The cache key for this prop, or `null` if it is not set.
     */
    public function key(): string|null
    {
        return $this->key;
    }

    /**
     * Returns a new instance with the given expiration time.
     *
     * @param DateTimeInterface|DateInterval|int $expiration Expiration as a timestamp, offset in seconds, or interval
     * relative to the current time.
     */
    public function until(DateTimeInterface|DateInterval|int $expiration): self
    {
        return new self(
            $this->value,
            $this->key,
            $expiration,
            $this->forceFresh,
        );
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
     * Validates the cache key format.
     *
     * @throws InvalidPropException When `$key` is empty or contains control characters or commas.
     */
    private static function validateKey(string $key): void
    {
        if ($key === '' || preg_match('/[\x00-\x1F\x7F,]/', $key) === 1) {
            throw new InvalidPropException(
                Message::ONCE_PROP_KEY_INVALID->getMessage(),
            );
        }
    }
}
