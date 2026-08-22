<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use PHPForge\Inertia\Exception\{InvalidPropException, Message};

final readonly class MergeProp implements PropValue
{
    /**
     * @param list<string> $appendPaths
     * @param list<string> $prependPaths
     * @param list<string> $matchOn
     */
    public function __construct(
        private mixed $value,
        private bool $deep = false,
        private bool $appendAtRoot = true,
        private array $appendPaths = [],
        private array $prependPaths = [],
        private array $matchOn = [],
    ) {
        $paths = [
            ...$appendPaths,
            ...$prependPaths,
            ...$matchOn,
        ];

        foreach ($paths as $path) {
            self::validatePath($path);
        }
    }

    public function append(string $path = '', string|null $matchOn = null): self
    {
        if ($path === '') {
            return new self(
                $this->value,
                false,
                true,
                [],
                [],
                $this->matchOn,
            );
        }

        $matches = $this->matchOn;

        if ($matchOn !== null) {
            $matches[] = "{$path}.{$matchOn}";
        }

        return new self(
            $this->value,
            false,
            $this->appendAtRoot,
            self::unique([...$this->appendPaths, $path]),
            $this->prependPaths,
            self::unique($matches),
        );
    }

    /**
     * @return list<string>
     */
    public function appendPaths(): array
    {
        return $this->appendPaths;
    }

    public function appendsAtRoot(): bool
    {
        return !$this->deep && $this->appendAtRoot && $this->appendPaths === [] && $this->prependPaths === [];
    }

    public function deepMerge(): self
    {
        return new self(
            $this->value,
            true,
            $this->appendAtRoot,
            [],
            [],
            $this->matchOn,
        );
    }

    public function isDeep(): bool
    {
        return $this->deep;
    }

    /**
     * @param string|list<string> $paths
     * @phpstan-param string|array<array-key, mixed> $paths
     */
    public function matchOn(string|array $paths): self
    {
        $paths = is_string($paths) ? [$paths] : array_values($paths);

        foreach ($paths as $path) {
            if (!is_string($path)) {
                throw new InvalidPropException(
                    Message::MERGE_MATCH_PATH_INVALID->getMessage(),
                );
            }

        }

        return new self(
            $this->value,
            $this->deep,
            $this->appendAtRoot,
            $this->appendPaths,
            $this->prependPaths,
            self::unique([...$this->matchOn, ...$paths]),
        );
    }

    /**
     * @return list<string>
     */
    public function matchPaths(): array
    {
        return $this->matchOn;
    }

    public function once(): OnceProp
    {
        return new OnceProp($this);
    }

    public function prepend(string $path = '', string|null $matchOn = null): self
    {
        if ($path === '') {
            return new self(
                $this->value,
                false,
                false,
                [],
                [],
                $this->matchOn,
            );
        }

        $matches = $this->matchOn;

        if ($matchOn !== null) {
            $matches[] = "{$path}.{$matchOn}";
        }

        return new self(
            $this->value,
            false,
            $this->appendAtRoot,
            $this->appendPaths,
            self::unique([...$this->prependPaths, $path]),
            self::unique($matches),
        );
    }

    /**
     * @return list<string>
     */
    public function prependPaths(): array
    {
        return $this->prependPaths;
    }

    public function prependsAtRoot(): bool
    {
        return !$this->deep && !$this->appendAtRoot && $this->appendPaths === [] && $this->prependPaths === [];
    }

    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * @param list<string> $items
     *
     * @return list<string>
     */
    private static function unique(array $items): array
    {
        return array_values(array_unique($items));
    }

    private static function validatePath(string $path): void
    {
        if (
            preg_match('/[\x00-\x1F\x7F,]/', $path) === 1
            || in_array('', explode('.', $path), true)
        ) {
            throw new InvalidPropException(
                Message::MERGE_PATH_INVALID->getMessage(),
            );
        }
    }
}
