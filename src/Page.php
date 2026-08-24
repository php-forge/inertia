<?php

declare(strict_types=1);

namespace PHPForge\Inertia;

use JsonSerializable;
use stdClass;

use function is_array;

/**
 * Represents an immutable Inertia page payload serialized by framework adapters.
 */
final readonly class Page implements JsonSerializable
{
    /**
     * @param array<string, mixed> $props Resolved page props passed to the front-end component.
     * @param list<string> $mergeProps Prop paths the client should merge into its existing state.
     * @param list<string> $prependProps Prop paths the client should prepend to its existing state.
     * @param list<string> $deepMergeProps Prop paths the client should deep-merge recursively.
     * @param list<string> $matchPropsOn Prop paths used as match keys during client-side merging.
     * @param array<
     *   string,
     *   array{
     *     pageName: string,
     *     previousPage: int|string|null,
     *     nextPage: int|string|null,
     *     currentPage: int|string|null,
     *     reset: bool
     *   }
     * > $scrollProps Per-prop infinite-scroll pagination metadata keyed by prop path.
     * @param array<string, list<string>> $deferredProps Groups of prop paths loaded lazily, keyed by group name.
     * @param list<string> $rescuedProps Prop paths whose callbacks failed but were rescued by a deferred loader.
     * @param list<string> $sharedProps Top-level keys sourced from shared props, exposed for client awareness.
     * @param array<string, array{prop: string, expiresAt: int|null}> $onceProps Once-prop cache metadata keyed by cache
     * key.
     * @param array<string, mixed> $flash Flash data passed alongside the page response.
     */
    public function __construct(
        public string $component,
        public array $props,
        public string $url,
        public string|int $version,
        public bool $encryptHistory = false,
        public bool $clearHistory = false,
        public bool $preserveFragment = false,
        public array $mergeProps = [],
        public array $prependProps = [],
        public array $deepMergeProps = [],
        public array $matchPropsOn = [],
        public array $scrollProps = [],
        public array $deferredProps = [],
        public array $rescuedProps = [],
        public array $sharedProps = [],
        public array $onceProps = [],
        public array $flash = [],
    ) {}

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

        foreach (
            [
                'mergeProps' => $this->mergeProps,
                'prependProps' => $this->prependProps,
                'deepMergeProps' => $this->deepMergeProps,
                'matchPropsOn' => $this->matchPropsOn,
                'scrollProps' => $this->scrollProps,
                'deferredProps' => $this->deferredProps,
                'rescuedProps' => $this->rescuedProps,
                'sharedProps' => $this->sharedProps,
                'onceProps' => $this->onceProps,
            ] as $name => $value
        ) {
            if ($value !== []) {
                $page[$name] = $value;
            }
        }

        if ($this->flash !== []) {
            $page['flash'] = self::asObject($this->flash);
        }

        return $page;
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
