<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Resolution;

use Closure;
use DateInterval;
use PHPForge\Inertia\Clock\Clock;
use PHPForge\Inertia\Exception\{InvalidPropException, Message, PropResolutionException};
use PHPForge\Inertia\{PageInput, RequestContext};
use PHPForge\Inertia\Prop\{AlwaysProp, MergeProp, OnceProp, ScrollMetadata};
use PHPForge\Inertia\Result\RescuedPropFailure;
use PHPForge\Inertia\Support\{DotArray, JsonValue};
use Throwable;

use function in_array;
use function is_array;
use function is_int;

final class PropResolver
{
    /**
     * @var list<string>
     */
    private array $deepMergeProps = [];
    /**
     * @var array<string, list<string>>
     */
    private array $deferredProps = [];
    /**
     * @var list<string>|null
     */
    private readonly array|null $except;
    private readonly bool $isPartial;
    /**
     * @var list<string>
     */
    private readonly array $loadedOnceProps;
    /**
     * @var list<string>
     */
    private array $matchPropsOn = [];
    /**
     * @var list<string>
     */
    private array $mergeProps = [];
    /**
     * @var array<string, array{prop: string, expiresAt: int|null}>
     */
    private array $onceProps = [];
    /**
     * @var list<string>|null
     */
    private readonly array|null $only;
    /**
     * @var list<string>
     */
    private array $prependProps = [];
    /**
     * @var list<RescuedPropFailure>
     */
    private array $rescuedFailures = [];
    /**
     * @var list<string>
     */
    private array $rescuedProps = [];
    /**
     * @var list<string>
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
     * >
     */
    private array $scrollProps = [];

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

    public function resolve(PageInput $input): ResolvedPageData
    {
        $combined = array_replace($input->sharedProps, $input->props);

        $props = DotArray::expand($combined);

        $errors = $input->errors;

        $errorBag = $this->request->errorBag();

        if ($errorBag !== null && $errors !== []) {
            $errors = [$errorBag => $errors];
        }

        $props['errors'] = new AlwaysProp($errors);

        $resolvedProps = $this->resolveTopLevel($props);
        $flash = $this->normalizeFlash($input->flash);
        $sharedProps = $input->exposeSharedProps ? $this->sharedPropKeys($input->sharedProps) : [];

        return new ResolvedPageData(
            $resolvedProps,
            $this->mergeProps,
            $this->prependProps,
            $this->deepMergeProps,
            $this->matchPropsOn,
            $this->scrollProps,
            $this->deferredProps,
            $this->rescuedProps,
            $sharedProps,
            $this->onceProps,
            $this->rescuedFailures,
            $flash,
        );
    }

    private function addDeferredProp(string $group, string $path): void
    {
        $this->deferredProps[$group] ??= [];

        $this->deferredProps[$group] = self::unique([...$this->deferredProps[$group], $path]);
    }

    private function collectExcludedMetadata(PropDefinition $definition, string $path): void
    {
        if (
            $definition->deferred !== null
            && !$this->wasAlreadyLoaded($definition, $path)
        ) {
            $this->addDeferredProp($definition->deferred->group(), $path);
        }

        $this->collectMergeMetadata($definition, $path);
        $this->collectOnceMetadata($definition->once, $path);
    }

    private function collectMergeMetadata(PropDefinition $definition, string $path): void
    {
        if (
            in_array($path, $this->resetProps, true)
            || ($this->isPartial && !$this->isIncludedInPartialMetadata($path))
        ) {
            return;
        }

        if ($definition->merge !== null) {
            $this->collectMergeProp($definition->merge, $path);
        }

        if ($definition->scroll !== null) {
            $mergePath = $path . '.' . $definition->scroll->wrapper();

            if ($this->request->infiniteScrollMergeIntent() === 'prepend') {
                $this->prependProps = self::unique([...$this->prependProps, $mergePath]);
            } else {
                $this->mergeProps = self::unique([...$this->mergeProps, $mergePath]);
            }
        }
    }

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
                $this->mergeProps = self::unique([...$this->mergeProps, $path . '.' . $mergePath]);
            }

            foreach ($merge->prependPaths() as $mergePath) {
                $this->prependProps = self::unique([...$this->prependProps, $path . '.' . $mergePath]);
            }
        }

        foreach ($merge->matchPaths() as $matchPath) {
            $this->matchPropsOn = self::unique([...$this->matchPropsOn, $path . '.' . $matchPath]);
        }
    }

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

    private function collectResolvedMetadata(PropDefinition $definition, string $path, mixed $value): void
    {
        $this->collectMergeMetadata($definition, $path);

        if ($definition->scroll !== null) {
            $metadata = $definition->scroll->metadata();
            $metadata = $metadata instanceof Closure ? $metadata($value) : $metadata;

            if (!$metadata instanceof ScrollMetadata) {
                throw new InvalidPropException(
                    Message::SCROLL_METADATA_INVALID->getMessage($path),
                );
            }

            $this->scrollProps[$path] = $metadata->toArray(in_array($path, $this->resetProps, true));
        }

        $this->collectOnceMetadata($definition->once, $path);
    }

    private function containsAlways(mixed $value): bool
    {
        $definition = PropDefinition::from($value);

        if ($definition->always) {
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

    private function isExcludedFromFullResponse(PropDefinition $definition, string $path): bool
    {
        if ($this->isPartial) {
            return false;
        }

        if ($definition->optional || $definition->deferred !== null) {
            $this->collectExcludedMetadata($definition, $path);

            return true;
        }

        if ($this->request->isInertia() && $this->wasAlreadyLoaded($definition, $path)) {
            $this->collectOnceMetadata($definition->once, $path);

            return true;
        }

        return false;
    }

    private function isIncludedInPartialMetadata(string $path): bool
    {
        if ($this->only !== null && !$this->matchesOnly($path)) {
            return false;
        }

        return $this->except === null || !$this->matchesExcept($path);
    }

    private function leadsToOnly(string $path): bool
    {
        if ($this->only === null) {
            return false;
        }

        foreach ($this->only as $onlyPath) {
            if (str_starts_with($onlyPath, $path . '.')) {
                return true;
            }
        }

        return false;
    }

    private function matchesExcept(string $path): bool
    {
        if ($this->except === null) {
            return false;
        }

        foreach ($this->except as $exceptPath) {
            if ($path === $exceptPath || str_starts_with($path, $exceptPath . '.')) {
                return true;
            }
        }

        return false;
    }

    private function matchesOnly(string $path): bool
    {
        if ($this->only === null) {
            return false;
        }

        foreach ($this->only as $onlyPath) {
            if ($path === $onlyPath || str_starts_with($path, $onlyPath . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
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
     * }
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
     * @param array<string, mixed> $flash
     *
     * @return array<string, mixed>
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

    private function pathMatchesPartialRequest(string $path): bool
    {
        if ($this->only !== null && !$this->matchesOnly($path) && !$this->leadsToOnly($path)) {
            return false;
        }

        return $this->except === null || !$this->matchesExcept($path);
    }

    private function resolveProp(mixed $raw, string $path, bool $parentWasResolved): ResolvedProp
    {
        $definition = PropDefinition::from($raw);

        if (
            $this->isPartial
            && !$definition->always
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
            if ($definition->deferred !== null && $definition->deferred->rescuesFailures()) {
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
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
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
     * @param array<array-key, mixed> $props
     *
     * @return array<array-key, mixed>
     */
    private function resolveTree(array $props, string $prefix, bool $parentWasResolved): array
    {
        $result = [];

        foreach ($props as $key => $raw) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            $resolved = $this->resolveProp($raw, $path, $parentWasResolved);

            if ($resolved->included) {
                $result[$key] = $resolved->value;
            }
        }

        return $result;
    }

    /**
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
     * } $snapshot
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
     * @param array<string, mixed> $sharedProps
     *
     * @return list<string>
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
     * @param list<string> $items
     *
     * @return list<string>
     */
    private static function unique(array $items): array
    {
        return array_values(array_unique($items));
    }

    private function wasAlreadyLoaded(PropDefinition $definition, string $path): bool
    {
        return $definition->once !== null
            && !$definition->once->isFresh()
            && in_array($definition->once->key() ?? $path, $this->loadedOnceProps, true);
    }
}
