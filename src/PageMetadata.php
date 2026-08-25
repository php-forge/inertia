<?php

declare(strict_types=1);

namespace PHPForge\Inertia;

/**
 * Carries immutable client-side metadata attached to an Inertia page payload.
 */
final class PageMetadata
{
    /**
     * @var list<string> Prop paths the client should deep-merge recursively.
     */
    private array $deepMergeProps = [];

    /**
     * @var array<string, list<string>> Groups of prop paths loaded lazily, keyed by group name.
     */
    private array $deferredProps = [];

    /**
     * @var list<string> Prop paths used as match keys during client-side merging.
     */
    private array $matchPropsOn = [];

    /**
     * @var list<string> Prop paths the client should merge into its existing state.
     */
    private array $mergeProps = [];

    /**
     * @var array<string, array{prop: string, expiresAt: int|null}> Once-prop cache metadata keyed by cache key.
     */
    private array $onceProps = [];

    /**
     * @var list<string> Prop paths the client should prepend to its existing state.
     */
    private array $prependProps = [];

    /**
     * @var list<string> Prop paths whose callbacks failed but were rescued by a deferred loader.
     */
    private array $rescuedProps = [];

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
     * > Per-prop infinite-scroll pagination metadata keyed by prop path.
     */
    private array $scrollProps = [];

    /**
     * @var list<string> Top-level keys sourced from shared props, exposed for client awareness.
     */
    private array $sharedProps = [];

    /**
     * Returns the prop paths the client should deep-merge recursively.
     *
     * @return list<string> Prop paths the client should deep-merge recursively.
     */
    public function deepMergeProps(): array
    {
        return $this->deepMergeProps;
    }

    /**
     * Returns the deferred prop groups.
     *
     * @return array<string, list<string>> Groups of prop paths loaded lazily, keyed by group name.
     */
    public function deferredProps(): array
    {
        return $this->deferredProps;
    }

    /**
     * Returns the prop paths used as client-side match keys.
     *
     * @return list<string> Prop paths used as match keys during client-side merging.
     */
    public function matchPropsOn(): array
    {
        return $this->matchPropsOn;
    }

    /**
     * Returns the prop paths the client should merge into its existing state.
     *
     * @return list<string> Prop paths the client should merge into its existing state.
     */
    public function mergeProps(): array
    {
        return $this->mergeProps;
    }

    /**
     * Returns the once-prop cache metadata.
     *
     * @return array<string, array{prop: string, expiresAt: int|null}> Once-prop cache metadata keyed by cache key.
     */
    public function onceProps(): array
    {
        return $this->onceProps;
    }

    /**
     * Returns the prop paths the client should prepend to its existing state.
     *
     * @return list<string> Prop paths the client should prepend to its existing state.
     */
    public function prependProps(): array
    {
        return $this->prependProps;
    }

    /**
     * Returns the prop paths whose failures were rescued.
     *
     * @return list<string> Prop paths whose callbacks failed but were rescued by a deferred loader.
     */
    public function rescuedProps(): array
    {
        return $this->rescuedProps;
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
        return $this->scrollProps;
    }

    /**
     * Returns the top-level keys sourced from shared props.
     *
     * @return list<string> Top-level keys sourced from shared props, exposed for client awareness.
     */
    public function sharedProps(): array
    {
        return $this->sharedProps;
    }

    /**
     * Returns the non-empty metadata fields as an Inertia page fragment.
     *
     * @return array{
     *   mergeProps?: list<string>,
     *   prependProps?: list<string>,
     *   deepMergeProps?: list<string>,
     *   matchPropsOn?: list<string>,
     *   scrollProps?: array<
     *     string,
     *     array{
     *       pageName: string,
     *       previousPage: int|string|null,
     *       nextPage: int|string|null,
     *       currentPage: int|string|null,
     *       reset: bool
     *     }
     *   >,
     *   deferredProps?: array<string, list<string>>,
     *   rescuedProps?: list<string>,
     *   sharedProps?: list<string>,
     *   onceProps?: array<string, array{prop: string, expiresAt: int|null}>
     * } Non-empty page metadata indexed by its Inertia protocol field name.
     */
    public function toArray(): array
    {
        $metadata = [];

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
                $metadata[$name] = $value;
            }
        }

        return $metadata;
    }

    /**
     * Returns a new instance with the prop paths the client should deep-merge recursively.
     *
     * @param list<string> $deepMergeProps Prop paths the client should deep-merge recursively.
     *
     * @return PageMetadata A new instance containing the supplied deep-merge prop paths.
     */
    public function withDeepMergeProps(array $deepMergeProps): PageMetadata
    {
        $clone = clone $this;
        $clone->deepMergeProps = $deepMergeProps;

        return $clone;
    }

    /**
     * Returns a new instance with the deferred prop groups.
     *
     * @param array<string, list<string>> $deferredProps Groups of prop paths loaded lazily, keyed by group name.
     *
     * @return PageMetadata A new instance containing the supplied deferred prop groups.
     */
    public function withDeferredProps(array $deferredProps): PageMetadata
    {
        $clone = clone $this;
        $clone->deferredProps = $deferredProps;

        return $clone;
    }

    /**
     * Returns a new instance with the prop paths used as client-side match keys.
     *
     * @param list<string> $matchPropsOn Prop paths used as match keys during client-side merging.
     *
     * @return PageMetadata A new instance containing the supplied match-key prop paths.
     */
    public function withMatchPropsOn(array $matchPropsOn): PageMetadata
    {
        $clone = clone $this;
        $clone->matchPropsOn = $matchPropsOn;

        return $clone;
    }

    /**
     * Returns a new instance with the prop paths the client should merge into its existing state.
     *
     * @param list<string> $mergeProps Prop paths the client should merge into its existing state.
     *
     * @return PageMetadata A new instance containing the supplied merge prop paths.
     */
    public function withMergeProps(array $mergeProps): PageMetadata
    {
        $clone = clone $this;
        $clone->mergeProps = $mergeProps;

        return $clone;
    }

    /**
     * Returns a new instance with the once-prop cache metadata.
     *
     * @param array<string, array{prop: string, expiresAt: int|null}> $onceProps Once-prop cache metadata keyed by cache
     * key.
     *
     * @return PageMetadata A new instance containing the supplied once-prop cache metadata.
     */
    public function withOnceProps(array $onceProps): PageMetadata
    {
        $clone = clone $this;
        $clone->onceProps = $onceProps;

        return $clone;
    }

    /**
     * Returns a new instance with the prop paths the client should prepend to its existing state.
     *
     * @param list<string> $prependProps Prop paths the client should prepend to its existing state.
     *
     * @return PageMetadata A new instance containing the supplied prepend prop paths.
     */
    public function withPrependProps(array $prependProps): PageMetadata
    {
        $clone = clone $this;
        $clone->prependProps = $prependProps;

        return $clone;
    }

    /**
     * Returns a new instance with the prop paths whose failures were rescued.
     *
     * @param list<string> $rescuedProps Prop paths whose callbacks failed but were rescued by a deferred loader.
     *
     * @return PageMetadata A new instance containing the supplied rescued prop paths.
     */
    public function withRescuedProps(array $rescuedProps): PageMetadata
    {
        $clone = clone $this;
        $clone->rescuedProps = $rescuedProps;

        return $clone;
    }

    /**
     * Returns a new instance with the infinite-scroll pagination metadata.
     *
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
     *
     * @return PageMetadata A new instance containing the supplied infinite-scroll pagination metadata.
     */
    public function withScrollProps(array $scrollProps): PageMetadata
    {
        $clone = clone $this;
        $clone->scrollProps = $scrollProps;

        return $clone;
    }

    /**
     * Returns a new instance with the top-level keys sourced from shared props.
     *
     * @param list<string> $sharedProps Top-level keys sourced from shared props, exposed for client awareness.
     *
     * @return PageMetadata A new instance containing the supplied shared prop keys.
     */
    public function withSharedProps(array $sharedProps): PageMetadata
    {
        $clone = clone $this;
        $clone->sharedProps = $sharedProps;

        return $clone;
    }
}
