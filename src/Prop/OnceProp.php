<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use Closure;
use DateInterval;
use DateTimeInterface;
use PHPForge\Inertia\Exception\{InvalidPropException, Message};

final readonly class OnceProp implements PropValue
{
    /**
     * @param (Closure(): mixed)|PropValue $value
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

    public function as(string $key): self
    {
        return new self(
            $this->value,
            $key,
            $this->expiration,
            $this->forceFresh,
        );
    }

    public function expiration(): DateTimeInterface|DateInterval|int|null
    {
        return $this->expiration;
    }

    public function fresh(bool $enabled = true): self
    {
        return new self(
            $this->value,
            $this->key,
            $this->expiration,
            $enabled,
        );
    }

    public function isFresh(): bool
    {
        return $this->forceFresh;
    }

    public function key(): string|null
    {
        return $this->key;
    }

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
     * @return (Closure(): mixed)|PropValue
     */
    public function value(): Closure|PropValue
    {
        return $this->value;
    }

    private static function validateKey(string $key): void
    {
        if ($key === '' || preg_match('/[\x00-\x1F\x7F,]/', $key) === 1) {
            throw new InvalidPropException(
                Message::ONCE_PROP_KEY_INVALID->getMessage(),
            );
        }
    }
}
