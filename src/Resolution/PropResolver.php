<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Resolution;

use Closure;
use DateInterval;
use PHPForge\Inertia\Clock\Clock;
use PHPForge\Inertia\Exception\{InvalidPropException, Message, PropResolutionException};
use PHPForge\Inertia\{PageInput, PageMetadata, RequestContext};
use PHPForge\Inertia\Prop\{AlwaysProp, MergeProp, OnceProp, ScrollMetadata};
use PHPForge\Inertia\Result\RescuedPropFailure;
use PHPForge\Inertia\Support\{DotArray, JsonValue};
use Throwable;

use function array_keys;
use function array_replace;
use function array_unique;
use function array_values;
use function explode;
use function in_array;
use function is_array;
use function is_int;
use function str_starts_with;

/**
 * Resolves page props and collects the metadata required by the current request.
 */
final class PropResolver
{
    /**
     * @var list<string> Prop paths the client should deep-merge recursively into its existing state.
     */
    private array $deepMergeProps = [];
    /**
     * @var array<string, list<string>> Deferred prop paths collected during resolution, keyed by group name.
     */
    private array $deferredProps = [];
    /**
     * @var list<string>|null Prop paths excluded by the partial request, or `null` outside a partial reload.
     */
    private readonly array|null $except;
    /**
     * @var bool Whether the current request is a partial reload for the given component.
     */
    private readonly bool $isPartial;
    /**
     * @var list<string> Once-prop cache keys the client reports as already loaded.
     */
    private readonly array $loadedOnceProps;
    /**
     * @var list<string> Prop paths used as match keys during client-side merging.
     */
    private array $matchPropsOn = [];
    /**
     * @var list<string> Prop paths the client should merge into its existing state.
     */
    private array $mergeProps = [];
    /**
     * @var array<string, array{prop: string, expiresAt: int|null}> Once-prop cache metadata, keyed by cache key.
     */
    private array $onceProps = [];
    /**
     * @var list<string>|null Prop paths requested by the partial request, or `null` outside a partial reload.
     */
    private readonly array|null $only;
    /**
     * @var list<string> Prop paths the client should prepend to its existing state.
     */
    private array $prependProps = [];
    /**
     * @var list<RescuedPropFailure> Prop failures captured during resolution for adapter reporting.
     */
    private array $rescuedFailures = [];
    /**
     * @var list<string> Prop paths whose callbacks failed but were rescued by a deferred loader.
     */
    private array $rescuedProps = [];
    /**
     * @var list<string> Prop paths the client requests to reset to their initial server values.
     */
    private readonly array $resetProps;

    /**
     * @var array<
     *   string,
     *   array{
     *     pageName: string,
     *     previousPage: int|string|null,
     *     nextPage: int|string|null,
     *     currentPage: int|string|null,
     *     reset: bool
     *   }
     * > Per-prop infinite-scroll pagination metadata, keyed by prop path.
     */
    private array $scrollProps = [];

    /**
     * @param RequestContext $request Validated request context from the framework adapter.
     * @param string $component Inertia component name matched against the partial-reload header.
     * @param Clock $clock Clock used to compute once-prop expiration timestamps.
     */
    public function __construct(
        private readonly RequestContext $request,
        private readonly string $component,
        private readonly Clock $clock,
    ) {
        $this->isPartial = $request->isPartialReloadFor($component);

        $only = $request->partialData();
        $except = $request->partialExcept();

        $this->only = $only === [] ? null : $only;
        $this->except = $except === [] ? null : $except;
        $this->resetProps = $request->resetProps();
        $this->loadedOnceProps = $request->exceptOnceProps();
    }

    /**
     * Resolves all page props and returns the data needed to construct an Inertia page.
     *
     * @param PageInput $input Validated page data, props, and options.
     *
     * @throws PropResolutionException when a prop closure throws and the prop is not deferred.
     *
     * @return ResolvedPageData Resolved page data and all metadata required for the current request.
     */
    public function resolve(PageInput $input): ResolvedPageData
    {
        $sharedProps = $input->sharedProps();

        $combined = array_replace($sharedProps, $input->props);

        $props = DotArray::expand($combined);

        $errors = $input->errors();

        $errorBag = $this->request->errorBag();

        if ($errorBag !== null && $errors !== []) {
            $errors = [$errorBag => $errors];
        }

        $props['errors'] = new AlwaysProp($errors);

        $resolvedProps = $this->resolveTopLevel($props);

        $flash = $this->normalizeFlash($input->flash());

        $sharedPropKeys = $input->exposesSharedProps() ? $this->sharedPropKeys($sharedProps) : [];

        $metadata = (new PageMetadata())
            ->withMergeProps($this->mergeProps)
            ->withPrependProps($this->prependProps)
            ->withDeepMergeProps($this->deepMergeProps)
            ->withMatchPropsOn($this->matchPropsOn)
            ->withScrollProps($this->scrollProps)
            ->withDeferredProps($this->deferredProps)
            ->withRescuedProps($this->rescuedProps)
            ->withSharedProps($sharedPropKeys)
            ->withOnceProps($this->onceProps);

        return new ResolvedPageData(
            $resolvedProps,
            $metadata,
            $this->rescuedFailures,
            $flash,
        );
    }

    /**
     * Registers a prop path under the given deferred group, deduplicating entries.
     *
     * @param string $group Deferred group name.
     * @param string $path Dot-notation path of the deferred prop.
     */
    private function addDeferredProp(string $group, string $path): void
    {
        $this->deferredProps[$group] ??= [];

        $this->deferredProps[$group] = self::unique([...$this->deferredProps[$group], $path]);
    }

    /**
     * Records deferred, merge, and once metadata for a prop that is excluded from the current response.
     *
     * @param PropDefinition $definition Prop definition containing metadata to register.
     * @param string $path Dot-notation path of the excluded prop.
     */
    private function collectExcludedMetadata(PropDefinition $definition, string $path): void
    {
        $deferred = $definition->deferred();

        if (
            $deferred !== null
            && !$this->wasAlreadyLoaded($definition, $path)
        ) {
            $this->addDeferredProp($deferred->group(), $path);
        }

        $this->collectMergeMetadata($definition, $path);
        $this->collectOnceMetadata($definition->once(), $path);
    }

    /**
     * Records merge and scroll metadata for a prop path, skipping reset or excluded partial paths.
     *
     * @param PropDefinition $definition Prop definition containing merge and scroll metadata.
     * @param string $path Dot-notation path of the prop.
     */
    private function collectMergeMetadata(PropDefinition $definition, string $path): void
    {
        if (
            in_array($path, $this->resetProps, true)
            || ($this->isPartial && !$this->isIncludedInPartialMetadata($path))
        ) {
            return;
        }

        $merge = $definition->merge();

        if ($merge !== null) {
            $this->collectMergeProp($merge, $path);
        }

        $scroll = $definition->scroll();

        if ($scroll !== null) {
            $mergePath = "{$path}." . $scroll->wrapper();

            if ($this->request->infiniteScrollMergeIntent() === 'prepend') {
                $this->prependProps = self::unique([...$this->prependProps, $mergePath]);
            } else {
                $this->mergeProps = self::unique([...$this->mergeProps, $mergePath]);
            }
        }
    }

    /**
     * Registers merge, deep-merge, prepend, and match paths from a {@see MergeProp} modifier.
     *
     * @param MergeProp $merge Merge modifier containing paths to register.
     * @param string $path Dot-notation path of the prop.
     */
    private function collectMergeProp(MergeProp $merge, string $path): void
    {
        if ($merge->isDeep()) {
            $this->deepMergeProps = self::unique([...$this->deepMergeProps, $path]);
        } elseif ($merge->appendsAtRoot()) {
            $this->mergeProps = self::unique([...$this->mergeProps, $path]);
        } elseif ($merge->prependsAtRoot()) {
            $this->prependProps = self::unique([...$this->prependProps, $path]);
        } else {
            foreach ($merge->appendPaths() as $mergePath) {
                $this->mergeProps = self::unique([...$this->mergeProps, "{$path}.{$mergePath}"]);
            }

            foreach ($merge->prependPaths() as $mergePath) {
                $this->prependProps = self::unique([...$this->prependProps, "{$path}.{$mergePath}"]);
            }
        }

        foreach ($merge->matchPaths() as $matchPath) {
            $this->matchPropsOn = self::unique([...$this->matchPropsOn, "{$path}.{$matchPath}"]);
        }
    }

    /**
     * Records once-prop cache metadata for the given path, skipping absent or excluded partial paths.
     *
     * @param OnceProp|null $once Once-prop modifier containing cache metadata, or `null` if absent.
     * @param string $path Dot-notation path of the prop.
     */
    private function collectOnceMetadata(OnceProp|null $once, string $path): void
    {
        if ($once === null || ($this->isPartial && !$this->isIncludedInPartialMetadata($path))) {
            return;
        }

        $this->onceProps[$once->key() ?? $path] = [
            'prop' => $path,
            'expiresAt' => $this->expirationTimestamp($once),
        ];
    }

    /**
     * Records merge, scroll, and once metadata after a prop value has been successfully resolved.
     *
     * @param PropDefinition $definition The prop definition.
     * @param string $path Dot-notation path of the prop.
     * @param mixed $value The resolved value of the prop.
     *
     * @throws InvalidPropException When scroll metadata is not a {@see ScrollMetadata}.
     */
    private function collectResolvedMetadata(PropDefinition $definition, string $path, mixed $value): void
    {
        $this->collectMergeMetadata($definition, $path);

        $scroll = $definition->scroll();

        if ($scroll !== null) {
            $metadata = $scroll->metadata();
            $metadata = $metadata instanceof Closure ? $metadata($value) : $metadata;

            if (!$metadata instanceof ScrollMetadata) {
                throw new InvalidPropException(
                    Message::SCROLL_METADATA_INVALID->getMessage($path),
                );
            }

            $this->scrollProps[$path] = $metadata->toArray(in_array($path, $this->resetProps, true));
        }

        $this->collectOnceMetadata($definition->once(), $path);
    }

    /**
     * Returns `true` if the value or any of its nested prop wrappers carries an always flag.
     *
     * @param mixed $value The value to check for always-prop wrappers.
     *
     * @return bool `true` if the value or any nested prop is always, `false` otherwise.
     */
    private function containsAlways(mixed $value): bool
    {
        $definition = PropDefinition::from($value);

        if ($definition->always()) {
            return true;
        }

        if (!is_array($definition->base)) {
            return false;
        }

        foreach ($definition->base as $child) {
            if ($this->containsAlways($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the expiration timestamp in milliseconds for the given once-prop, or `null` if it never expires.
     *
     * @param OnceProp $once The once-prop to compute the expiration timestamp for.
     *
     * @return int|null Expiration timestamp in milliseconds, or `null` if the once-prop never expires.
     */
    private function expirationTimestamp(OnceProp $once): int|null
    {
        $expiration = $once->expiration();

        if ($expiration === null) {
            return null;
        }

        if (is_int($expiration)) {
            return ($this->clock->now()->getTimestamp() + $expiration) * 1000;
        }

        if ($expiration instanceof DateInterval) {
            return $this->clock->now()->add($expiration)->getTimestamp() * 1000;
        }

        return $expiration->getTimestamp() * 1000;
    }

    /**
     * Returns `true` if the prop should be omitted from a full (non-partial) response and records its metadata.
     *
     * @param PropDefinition $definition The prop definition to check for exclusion.
     * @param string $path Dot-notation path of the prop.
     *
     * @return bool `true` if the prop should be omitted from a full response, `false` otherwise.
     */
    private function isExcludedFromFullResponse(PropDefinition $definition, string $path): bool
    {
        if ($this->isPartial) {
            return false;
        }

        if ($definition->optional() || $definition->deferred() !== null) {
            $this->collectExcludedMetadata($definition, $path);

            return true;
        }

        if ($this->request->isInertia() && $this->wasAlreadyLoaded($definition, $path)) {
            $this->collectOnceMetadata($definition->once(), $path);

            return true;
        }

        return false;
    }

    /**
     * Returns `true` if the path falls within the partial request's `only`/`except` metadata filters.
     *
     * @param string $path Dot-notation path of the prop.
     *
     * @return bool `true` if the path is included in the partial request's metadata, `false` otherwise.
     */
    private function isIncludedInPartialMetadata(string $path): bool
    {
        if ($this->only !== null && !self::matchesPath($path, $this->only)) {
            return false;
        }

        return $this->except === null || !self::matchesPath($path, $this->except);
    }

    /**
     * Returns `true` if any `only` path is a descendant of the given path, requiring traversal of its subtree.
     *
     * @param string $path Dot-notation path of the prop.
     * @param list<string> $only Prop paths requested by the partial request.
     *
     * @return bool `true` if any `only` path is a descendant of the given path, `false` otherwise.
     */
    private static function leadsToOnly(string $path, array $only): bool
    {
        foreach ($only as $onlyPath) {
            if (str_starts_with($onlyPath, "{$path}.")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns `true` if the path matches or is a descendant of one of the supplied paths.
     *
     * @param string $path Dot-notation path of the prop.
     * @param list<string> $paths Prop paths to match against.
     *
     * @return bool `true` if the path matches or descends from a supplied path, `false` otherwise.
     */
    private static function matchesPath(string $path, array $paths): bool
    {
        foreach ($paths as $candidate) {
            if ($path === $candidate || str_starts_with($path, "{$candidate}.")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Captures a point-in-time copy of all tracked resolution metadata.
     *
     * @return array{
     *   deferredProps: array<string, list<string>>,
     *   rescuedProps: list<string>,
     *   rescuedFailures: list<RescuedPropFailure>,
     *   mergeProps: list<string>,
     *   prependProps: list<string>,
     *   deepMergeProps: list<string>,
     *   matchPropsOn: list<string>,
     *   scrollProps: array<
     *     string,
     *     array{
     *       pageName: string,
     *       previousPage: int|string|null,
     *       nextPage: int|string|null,
     *       currentPage: int|string|null,
     *       reset: bool
     *     }
     *   >,
     *   onceProps: array<string, array{prop: string, expiresAt: int|null}>
     * } Current resolution state, used to roll back metadata on rescued prop failure.
     */
    private function metadataSnapshot(): array
    {
        return [
            'deferredProps' => $this->deferredProps,
            'rescuedProps' => $this->rescuedProps,
            'rescuedFailures' => $this->rescuedFailures,
            'mergeProps' => $this->mergeProps,
            'prependProps' => $this->prependProps,
            'deepMergeProps' => $this->deepMergeProps,
            'matchPropsOn' => $this->matchPropsOn,
            'scrollProps' => $this->scrollProps,
            'onceProps' => $this->onceProps,
        ];
    }

    /**
     * Normalizes flash data values through JSON validation, throwing on invalid values.
     *
     * @param array<string, mixed> $flash Flash data indexed by key.
     *
     * @return array<string, mixed> Flash data with all values normalized to JSON-safe scalars.
     */
    private function normalizeFlash(array $flash): array
    {
        $normalized = [];

        foreach ($flash as $key => $value) {
            try {
                $normalized[$key] = JsonValue::normalize($value, "flash.{$key}");
            } catch (Throwable $failure) {
                throw new PropResolutionException(
                    "flash.{$key}",
                    $failure,
                );
            }
        }

        return $normalized;
    }

    /**
     * Returns `true` if the path should be traversed or included during a partial request.
     *
     * @param string $path Dot-notation path of the prop.
     *
     * @return bool `true` if the path is included in the partial request's metadata, `false` otherwise.
     */
    private function pathMatchesPartialRequest(string $path): bool
    {
        if (
            $this->only !== null
            && !self::matchesPath($path, $this->only)
            && !self::leadsToOnly($path, $this->only)
        ) {
            return false;
        }

        return $this->except === null || !self::matchesPath($path, $this->except);
    }

    /**
     * Resolves a single prop value at the given path, applying inclusion rules and unwrapping closures.
     *
     * @param mixed $raw The raw prop value, which may be a {@see PropValue} wrapper or a plain value.
     * @param string $path Dot-notation path used for error messages and metadata registration.
     * @param bool $parentWasResolved Whether the parent array was itself resolved, bypassing partial filters.
     *
     * @throws PropResolutionException When a closure throws and the prop is not deferred.
     *
     * @return ResolvedProp The resolved prop value and inclusion status.
     */
    private function resolveProp(mixed $raw, string $path, bool $parentWasResolved): ResolvedProp
    {
        $definition = PropDefinition::from($raw);

        if (
            $this->isPartial
            && !$definition->always()
            && !$parentWasResolved
            && !$this->pathMatchesPartialRequest($path)
        ) {
            if (!is_array($definition->base) || !$this->containsAlways($definition->base)) {
                return ResolvedProp::omit();
            }
        }

        if ($this->isExcludedFromFullResponse($definition, $path)) {
            return ResolvedProp::omit();
        }

        $snapshot = $this->metadataSnapshot();

        try {
            $value = $definition->base;

            for ($depth = 0; $value instanceof Closure; ++$depth) {
                if ($depth === 64) {
                    throw new InvalidPropException(
                        Message::PROP_RESOLVER_DEPTH_EXCEEDED->getMessage($path),
                    );
                }

                $value = $value();

                $resolvedDefinition = PropDefinition::from($value);

                $definition = $definition->mergeWith($resolvedDefinition);

                $value = $definition->base;

                if ($this->isExcludedFromFullResponse($definition, $path)) {
                    return ResolvedProp::omit();
                }
            }

            $value = is_array($value)
                ? $this->resolveTree($value, $path, $parentWasResolved || !is_array($raw))
                : JsonValue::normalize($value, $path);

            $this->collectResolvedMetadata($definition, $path, $value);

            return ResolvedProp::include($value);
        } catch (Throwable $failure) {
            $deferred = $definition->deferred();

            if ($deferred !== null && $deferred->rescuesFailures()) {
                $this->restoreMetadata($snapshot);
                $this->rescuedProps = self::unique([...$this->rescuedProps, $path]);
                $this->rescuedFailures[] = new RescuedPropFailure($path, $failure);

                return ResolvedProp::omit();
            }

            if ($failure instanceof PropResolutionException) {
                throw $failure;
            }

            throw new PropResolutionException(
                $path,
                $failure,
            );
        }
    }

    /**
     * Resolves all top-level props and returns a `string`-keyed result array.
     *
     * @param array<string, mixed> $props Top-level prop map to resolve.
     *
     * @return array<string, mixed> Resolved prop map keyed by top-level name, with omitted entries removed.
     */
    private function resolveTopLevel(array $props): array
    {
        $result = [];

        foreach ($props as $key => $raw) {
            $resolved = $this->resolveProp($raw, $key, false);

            if ($resolved->included) {
                $result[$key] = $resolved->value;
            }
        }

        return $result;
    }

    /**
     * Recursively resolves a nested prop array, building dot-notation paths for each entry.
     *
     * @param array<array-key, mixed> $props Nested prop array to traverse.
     *
     * @return array<array-key, mixed> Resolved nested prop array, with omitted entries removed.
     */
    private function resolveTree(array $props, string $prefix, bool $parentWasResolved): array
    {
        $result = [];

        foreach ($props as $key => $raw) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            $resolved = $this->resolveProp($raw, $path, $parentWasResolved);

            if ($resolved->included) {
                $result[$key] = $resolved->value;
            }
        }

        return $result;
    }

    /**
     * Restores resolution metadata from a snapshot, rolling back any changes made during a rescued prop failure.
     *
     * @param array{
     *   deferredProps: array<string, list<string>>,
     *   rescuedProps: list<string>,
     *   rescuedFailures: list<RescuedPropFailure>,
     *   mergeProps: list<string>,
     *   prependProps: list<string>,
     *   deepMergeProps: list<string>,
     *   matchPropsOn: list<string>,
     *   scrollProps: array<
     *     string,
     *     array{
     *       pageName: string,
     *       previousPage: int|string|null,
     *       nextPage: int|string|null,
     *       currentPage: int|string|null,
     *       reset: bool
     *     }
     *   >,
     *   onceProps: array<string, array{prop: string, expiresAt: int|null}>
     * } $snapshot Metadata snapshot captured before resolution, restored to roll back failed rescues.
     */
    private function restoreMetadata(array $snapshot): void
    {
        $this->deferredProps = $snapshot['deferredProps'];
        $this->rescuedProps = $snapshot['rescuedProps'];
        $this->rescuedFailures = $snapshot['rescuedFailures'];
        $this->mergeProps = $snapshot['mergeProps'];
        $this->prependProps = $snapshot['prependProps'];
        $this->deepMergeProps = $snapshot['deepMergeProps'];
        $this->matchPropsOn = $snapshot['matchPropsOn'];
        $this->scrollProps = $snapshot['scrollProps'];
        $this->onceProps = $snapshot['onceProps'];
    }

    /**
     * Returns the deduplicated top-level keys from the shared props array.
     *
     * @param array<string, mixed> $sharedProps Shared props whose top-level keys to extract.
     *
     * @return list<string> Deduplicated top-level prop keys extracted from the shared props map.
     */
    private function sharedPropKeys(array $sharedProps): array
    {
        $keys = [];

        foreach (array_keys($sharedProps) as $key) {
            $keys[] = explode('.', $key)[0];
        }

        return self::unique($keys);
    }

    /**
     * Returns a deduplicated, re-indexed copy of `$items`.
     *
     * @param list<string> $items The list to deduplicate.
     *
     * @return list<string> Deduplicated, re-indexed copy of the input list.
     */
    private static function unique(array $items): array
    {
        return array_values(array_unique($items));
    }

    /**
     * Returns `true` if the once-prop at the given path was reported as already loaded by the client.
     */
    private function wasAlreadyLoaded(PropDefinition $definition, string $path): bool
    {
        $once = $definition->once();

        return $once !== null
            && !$once->isFresh()
            && in_array($once->key() ?? $path, $this->loadedOnceProps, true);
    }
}
