<?php

declare(strict_types=1);

namespace PHPForge\Inertia;

use JsonSerializable;
use stdClass;

use function is_array;

/**
 * Represents an immutable Inertia page payload serialized by framework adapters.
 */
final class Page implements JsonSerializable
{
    /**
     * Whether to clear the page history for this response.
     */
    private bool $clearHistory = false;

    /**
     * Whether to encrypt the page history for this response.
     */
    private bool $encryptHistory = false;

    /**
     * @var array<string, mixed> Flash data passed alongside the page response.
     */
    private array $flash = [];

    /**
     * Client-side metadata attached to the page payload.
     */
    private PageMetadata $metadata;

    /**
     * Whether to preserve the URL fragment for this response.
     */
    private bool $preserveFragment = false;

    /**
     * @param string $component The name of the front-end component to render.
     * @param array<string, mixed> $props Resolved page props passed to the front-end component.
     * @param string $url The request URL included in the page payload.
     * @param string|int $version The current page version used to detect stale client pages.
     */
    public function __construct(
        public readonly string $component,
        public readonly array $props,
        public readonly string $url,
        public readonly string|int $version,
    ) {
        $this->metadata = new PageMetadata();
    }

    /**
     * Returns whether the page history should be cleared.
     *
     * @return bool Whether the page history should be cleared.
     */
    public function clearHistory(): bool
    {
        return $this->clearHistory;
    }

    /**
     * Returns the prop paths the client should deep-merge recursively.
     *
     * @return list<string> Prop paths the client should deep-merge recursively.
     */
    public function deepMergeProps(): array
    {
        return $this->metadata->deepMergeProps();
    }

    /**
     * Returns the deferred prop groups.
     *
     * @return array<string, list<string>> Groups of prop paths loaded lazily, keyed by group name.
     */
    public function deferredProps(): array
    {
        return $this->metadata->deferredProps();
    }

    /**
     * Returns whether the page history should be encrypted.
     *
     * @return bool Whether the page history should be encrypted.
     */
    public function encryptHistory(): bool
    {
        return $this->encryptHistory;
    }

    /**
     * Returns the flash data passed alongside the page response.
     *
     * @return array<string, mixed> Flash data passed alongside the page response.
     */
    public function flash(): array
    {
        return $this->flash;
    }

    /**
     * Serializes the page payload as a JSON-compatible array.
     *
     * @return array<string, mixed> The page payload as a JSON-compatible associative array.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns the prop paths used as client-side match keys.
     *
     * @return list<string> Prop paths used as match keys during client-side merging.
     */
    public function matchPropsOn(): array
    {
        return $this->metadata->matchPropsOn();
    }

    /**
     * Returns the prop paths the client should merge into its existing state.
     *
     * @return list<string> Prop paths the client should merge into its existing state.
     */
    public function mergeProps(): array
    {
        return $this->metadata->mergeProps();
    }

    /**
     * Returns the client-side page metadata.
     *
     * @return PageMetadata Client-side metadata attached to the page payload.
     */
    public function metadata(): PageMetadata
    {
        return $this->metadata;
    }

    /**
     * Returns the once-prop cache metadata.
     *
     * @return array<string, array{prop: string, expiresAt: int|null}> Once-prop cache metadata keyed by cache key.
     */
    public function onceProps(): array
    {
        return $this->metadata->onceProps();
    }

    /**
     * Returns the prop paths the client should prepend to its existing state.
     *
     * @return list<string> Prop paths the client should prepend to its existing state.
     */
    public function prependProps(): array
    {
        return $this->metadata->prependProps();
    }

    /**
     * Returns whether the URL fragment should be preserved.
     *
     * @return bool Whether the URL fragment should be preserved.
     */
    public function preserveFragment(): bool
    {
        return $this->preserveFragment;
    }

    /**
     * Returns the prop paths whose failures were rescued.
     *
     * @return list<string> Prop paths whose callbacks failed but were rescued by a deferred loader.
     */
    public function rescuedProps(): array
    {
        return $this->metadata->rescuedProps();
    }

    /**
     * Returns the infinite-scroll pagination metadata.
     *
     * @return array<
     *   string,
     *   array{
     *     pageName: string,
     *     previousPage: int|string|null,
     *     nextPage: int|string|null,
     *     currentPage: int|string|null,
     *     reset: bool
     *   }
     * > Per-prop infinite-scroll pagination metadata keyed by prop path.
     */
    public function scrollProps(): array
    {
        return $this->metadata->scrollProps();
    }

    /**
     * Returns the top-level keys sourced from shared props.
     *
     * @return list<string> Top-level keys sourced from shared props, exposed for client awareness.
     */
    public function sharedProps(): array
    {
        return $this->metadata->sharedProps();
    }

    /**
     * Returns the page as an associative array for the framework adapter.
     *
     * @return array<string, mixed> The page payload as an associative array for the framework adapter.
     */
    public function toArray(): array
    {
        $props = $this->props;

        $errors = $props['errors'] ?? [];

        $props['errors'] = self::asObject(is_array($errors) ? $errors : []);

        $page = [
            'component' => $this->component,
            'props' => $props,
            'url' => $this->url,
            'version' => $this->version,
        ];

        foreach (
            [
                'encryptHistory' => $this->encryptHistory,
                'clearHistory' => $this->clearHistory,
                'preserveFragment' => $this->preserveFragment,
            ] as $name => $enabled
        ) {
            if ($enabled) {
                $page[$name] = true;
            }
        }

        foreach ($this->metadata->toArray() as $name => $value) {
            $page[$name] = $value;
        }

        if ($this->flash !== []) {
            $page['flash'] = self::asObject($this->flash);
        }

        return $page;
    }

    /**
     * Returns a new instance with the clear-history setting.
     *
     * @param bool $clearHistory Whether to clear the page history for this response.
     *
     * @return Page A new instance containing the supplied clear-history setting.
     */
    public function withClearHistory(bool $clearHistory = true): Page
    {
        $clone = clone $this;
        $clone->clearHistory = $clearHistory;

        return $clone;
    }

    /**
     * Returns a new instance with the encrypt-history setting.
     *
     * @param bool $encryptHistory Whether to encrypt the page history for this response.
     *
     * @return Page A new instance containing the supplied encrypt-history setting.
     */
    public function withEncryptHistory(bool $encryptHistory = true): Page
    {
        $clone = clone $this;
        $clone->encryptHistory = $encryptHistory;

        return $clone;
    }

    /**
     * Returns a new instance with the supplied flash data.
     *
     * @param array<string, mixed> $flash Flash data passed alongside the page response.
     *
     * @return Page A new instance containing the supplied flash data.
     */
    public function withFlash(array $flash): Page
    {
        $clone = clone $this;
        $clone->flash = $flash;

        return $clone;
    }

    /**
     * Returns a new instance with the supplied client-side metadata.
     *
     * @param PageMetadata $metadata Client-side metadata attached to the page payload.
     *
     * @return Page A new instance containing the supplied client-side metadata.
     */
    public function withMetadata(PageMetadata $metadata): Page
    {
        $clone = clone $this;
        $clone->metadata = $metadata;

        return $clone;
    }

    /**
     * Returns a new instance with the preserve-fragment setting.
     *
     * @param bool $preserveFragment Whether to preserve the URL fragment for this response.
     *
     * @return Page A new instance containing the supplied preserve-fragment setting.
     */
    public function withPreserveFragment(bool $preserveFragment = true): Page
    {
        $clone = clone $this;
        $clone->preserveFragment = $preserveFragment;

        return $clone;
    }

    /**
     * Casts an associative array to a plain object for JSON serialization.
     *
     * @param array<array-key, mixed> $value The associative array to cast to a plain object.
     */
    private static function asObject(array $value): stdClass
    {
        return (object) $value;
    }
}
