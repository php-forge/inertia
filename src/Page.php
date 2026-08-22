<?php

declare(strict_types=1);

namespace PHPForge\Inertia;

use JsonSerializable;
use stdClass;

/**
 * Immutable protocol page data ready for JSON serialization.
 */
final readonly class Page implements JsonSerializable
{
    /**
     * @param array<string, mixed> $props
     * @param list<string> $mergeProps
     * @param list<string> $prependProps
     * @param list<string> $deepMergeProps
     * @param list<string> $matchPropsOn
     * @param array<
     *   string,
     *   array{
     *     pageName: string,
     *     previousPage: int|string|null,
     *     nextPage: int|string|null,
     *     currentPage: int|string|null,
     *     reset: bool
     *   }
     * > $scrollProps
     * @param array<string, list<string>> $deferredProps
     * @param list<string> $rescuedProps
     * @param list<string> $sharedProps
     * @param array<string, array{prop: string, expiresAt: int|null}> $onceProps
     * @param array<string, mixed> $flash
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
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
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
     * @param array<array-key, mixed> $value
     */
    private static function asObject(array $value): stdClass
    {
        return (object) $value;
    }
}
