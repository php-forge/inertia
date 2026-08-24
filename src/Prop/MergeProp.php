<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use PHPForge\Inertia\Exception\{InvalidPropException, Message};

use function array_values;
use function is_string;

/**
 * Wraps a value that merges with existing client-side data during partial reloads instead of replacing it.
 */
final readonly class MergeProp implements PropValue
{
    /**
     * @param mixed $value The value to merge.
     * @param bool $deep Whether to perform a deep merge.
     * @param bool $appendAtRoot Whether to append at the root level (true) or prepend at the root level (false) when no
     * paths are specified.
     * @param list<string> $appendPaths The paths to append to.
     * @param list<string> $prependPaths The paths to prepend to.
     * @param list<string> $matchOn The paths to match on for deduplication.
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

    /**
     * Returns a new instance with an append operation at the given path.
     *
     * @param string $path The path to append to, or empty string for root.
     * @param string|null $matchOn Optional match key for deduplication.
     *
     * @return MergeProp A new MergeProp with an append operation at the given path.
     */
    public function append(string $path = '', string|null $matchOn = null): MergeProp
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
     * Returns the list of append paths.
     *
     * @return list<string> List of append paths.
     */
    public function appendPaths(): array
    {
        return $this->appendPaths;
    }

    /**
     * Returns `true` if the merge appends at the root level.
     *
     * @return bool `true` if the merge appends at the root level, `false` otherwise.
     */
    public function appendsAtRoot(): bool
    {
        return !$this->deep && $this->appendAtRoot && $this->appendPaths === [] && $this->prependPaths === [];
    }

    /**
     * Returns a new instance with deep merge enabled.
     *
     * @return MergeProp A new MergeProp with deep merge enabled.
     */
    public function deepMerge(): MergeProp
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

    /**
     * Returns `true` if deep merge is enabled.
     *
     * @return bool `true` if deep merge is enabled, `false` otherwise.
     */
    public function isDeep(): bool
    {
        return $this->deep;
    }

    /**
     * Returns a new instance with match paths for deduplication.
     *
     * @param string|array<array-key, mixed> $paths Match keys to add.
     *
     * @return MergeProp A new MergeProp with match paths for deduplication.
     */
    public function matchOn(string|array $paths): MergeProp
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
     * Returns the list of match paths.
     *
     * @return list<string> List of match paths.
     */
    public function matchPaths(): array
    {
        return $this->matchOn;
    }

    /**
     * Returns a new `OnceProp` wrapper around this merge prop.
     *
     * @return OnceProp A new OnceProp wrapper around this merge prop.
     */
    public function once(): OnceProp
    {
        return new OnceProp($this);
    }

    /**
     * Returns a new instance with a prepend operation at the given path.
     *
     * @param string $path The path to prepend to, or empty string for root.
     * @param string|null $matchOn Optional match key for deduplication.
     *
     * @return MergeProp A new MergeProp with a prepend operation at the given path.
     */
    public function prepend(string $path = '', string|null $matchOn = null): MergeProp
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
     * Returns the list of prepend paths.
     *
     * @return list<string> List of prepend paths.
     */
    public function prependPaths(): array
    {
        return $this->prependPaths;
    }

    /**
     * Returns `true` if the merge prepends at the root level.
     *
     * @return bool `true` if the merge prepends at the root level, `false` otherwise.
     */
    public function prependsAtRoot(): bool
    {
        return !$this->deep && !$this->appendAtRoot && $this->appendPaths === [] && $this->prependPaths === [];
    }

    /**
     * Returns the wrapped value.
     *
     * @return mixed The wrapped value.
     */
    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * Returns a unique list of items.
     *
     * @param list<string> $items The list of items to make unique.
     *
     * @return list<string> A unique list of items.
     */
    private static function unique(array $items): array
    {
        return array_values(array_unique($items));
    }

    /**
     * Validates a path string.
     *
     * @param string $path The path string to validate.
     *
     * @throws InvalidPropException If the path is invalid.
     */
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
