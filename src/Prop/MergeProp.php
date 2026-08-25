<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Prop;

use PHPForge\Inertia\Exception\{InvalidPropException, Message};

use function array_values;
use function in_array;
use function is_string;

/**
 * Wraps a value that merges with existing client-side data during partial reloads instead of replacing it.
 */
final class MergeProp implements PropValue
{
    /**
     * Whether to append at the root level when no paths are specified.
     */
    private bool $appendAtRoot = true;

    /**
     * @var list<string> The paths to append to.
     */
    private array $appendPaths = [];

    /**
     * Whether to perform a deep merge.
     */
    private bool $deep = false;

    /**
     * @var list<string> The paths to match on for deduplication.
     */
    private array $matchOn = [];

    /**
     * @var list<string> The paths to prepend to.
     */
    private array $prependPaths = [];

    /**
     * @param mixed $value The value to merge.
     */
    public function __construct(private readonly mixed $value) {}

    /**
     * Returns a new instance with an append operation at the given path.
     *
     * @param string $path The path to append to, or empty string for root.
     * @param string|null $matchOn Optional match key for deduplication.
     *
     * @return MergeProp A new MergeProp with an append operation at the given path.
     */
    public function append(string $path = '', string|null $matchOn = null): self
    {
        if ($path === '') {
            $clone = clone $this;
            $clone->deep = false;
            $clone->appendAtRoot = true;
            $clone->appendPaths = [];
            $clone->prependPaths = [];

            return $clone;
        }

        self::validatePath($path);

        $matchPath = self::nestedMatchPath($path, $matchOn);

        $clone = clone $this;
        $clone->deep = false;
        $clone->appendPaths = self::unique([...$clone->appendPaths, $path]);

        if ($matchPath !== null) {
            $clone->matchOn = self::unique([...$clone->matchOn, $matchPath]);
        }

        return $clone;
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
    public function deepMerge(): self
    {
        $clone = clone $this;
        $clone->deep = true;
        $clone->appendPaths = [];
        $clone->prependPaths = [];

        return $clone;
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
     * @param array<array-key, mixed>|string $paths Match keys to add.
     *
     * @return MergeProp A new MergeProp with match paths for deduplication.
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

        foreach ($paths as $path) {
            self::validatePath($path);
        }

        $clone = clone $this;
        $clone->matchOn = self::unique([...$clone->matchOn, ...$paths]);

        return $clone;
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
    public function prepend(string $path = '', string|null $matchOn = null): self
    {
        if ($path === '') {
            $clone = clone $this;
            $clone->deep = false;
            $clone->appendAtRoot = false;
            $clone->appendPaths = [];
            $clone->prependPaths = [];

            return $clone;
        }

        self::validatePath($path);

        $matchPath = self::nestedMatchPath($path, $matchOn);

        $clone = clone $this;
        $clone->deep = false;
        $clone->prependPaths = self::unique([...$clone->prependPaths, $path]);

        if ($matchPath !== null) {
            $clone->matchOn = self::unique([...$clone->matchOn, $matchPath]);
        }

        return $clone;
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
     * Builds and validates a nested match path when a match key is supplied.
     *
     * @param string $path The parent path of the match key.
     * @param string|null $matchOn Optional match key for deduplication.
     *
     * @return string|null The validated nested match path, or `null` when no match key is supplied.
     */
    private static function nestedMatchPath(string $path, string|null $matchOn): string|null
    {
        if ($matchOn === null) {
            return null;
        }

        $matchPath = "{$path}.{$matchOn}";

        self::validatePath($matchPath);

        return $matchPath;
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
