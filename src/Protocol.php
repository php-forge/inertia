<?php

declare(strict_types=1);

namespace PHPForge\Inertia;

use InvalidArgumentException;
use PHPForge\Inertia\Clock\{Clock, SystemClock};
use PHPForge\Inertia\Exception\Message;
use PHPForge\Inertia\Resolution\PropResolver;
use PHPForge\Inertia\Result\{
    FragmentRedirectResult,
    InertiaPageResult,
    InitialPageResult,
    LocationResult,
    RedirectResult,
    VersionConflictResult,
};

use function in_array;
use function is_array;
use function parse_url;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function strtolower;

/**
 * Applies Inertia protocol decisions to framework-neutral requests, pages, locations, and `redirects.is`
 * `InitialPageResult` or `InertiaPageResult` or `VersionConflictResult`.
 */
final readonly class Protocol
{
    /**
     * @var Clock Clock used for once-prop expiration.
     */
    private Clock $clock;

    /**
     * @param Clock|null $clock Clock used for once-prop expiration. Defaults to {@see SystemClock}.
     */
    public function __construct(Clock|null $clock = null)
    {
        $this->clock = $clock ?? new SystemClock();
    }

    /**
     * Returns an Inertia location result for external navigations, or a standard redirect outside Inertia.
     *
     * @param RequestContext $request Validated request context from the framework adapter.
     * @param string $absoluteUrl Absolute HTTP/HTTPS URL of the target location.
     *
     * @throws InvalidArgumentException When `$absoluteUrl` is not a valid absolute HTTP/HTTPS URL.
     *
     * @return LocationResult|RedirectResult Returns a {@see LocationResult} for Inertia requests, or a
     * {@see RedirectResult} for non-Inertia requests.
     */
    public function location(RequestContext $request, string $absoluteUrl): LocationResult|RedirectResult
    {
        if (!self::isAbsoluteHttpUrl($absoluteUrl)) {
            throw new InvalidArgumentException(
                Message::LOCATION_URL_INVALID->getMessage(),
            );
        }

        return $request->isInertia()
            ? new LocationResult($absoluteUrl)
            : new RedirectResult($absoluteUrl);
    }

    /**
     * Resolves page props and returns the appropriate Inertia page result.
     *
     * Returns {@see VersionConflictResult} when the client's asset version differs from the page version.
     * Returns {@see InertiaPageResult} for Inertia partial or full visits.
     * Returns {@see InitialPageResult} for the initial HTML render.
     *
     * @param RequestContext $request Validated request context from the framework adapter.
     * @param PageInput $input Validated page data, props, and options.
     *
     * @return InitialPageResult|InertiaPageResult|VersionConflictResult Returns an {@see InitialPageResult} for the
     * initial HTML render, an {@see InertiaPageResult} for Inertia partial or full visits, or a
     * {@see VersionConflictResult} when the client's asset version differs from the page version.
     */
    public function page(
        RequestContext $request,
        PageInput $input,
    ): InitialPageResult|InertiaPageResult|VersionConflictResult {
        if (
            $request->isInertia()
            && $request->isGet()
            && $request->requestVersion() !== null
            && $request->requestVersion() !== (string) $input->version
        ) {
            return new VersionConflictResult($request->absoluteUrl, $input->version);
        }

        $resolved = (new PropResolver($request, $input->component, $this->clock))->resolve($input);
        $page = new Page(
            component: $input->component,
            props: $resolved->props,
            url: $request->url,
            version: $input->version,
            encryptHistory: $input->encryptHistory,
            clearHistory: $input->clearHistory,
            preserveFragment: $input->preserveFragment,
            mergeProps: $resolved->mergeProps,
            prependProps: $resolved->prependProps,
            deepMergeProps: $resolved->deepMergeProps,
            matchPropsOn: $resolved->matchPropsOn,
            scrollProps: $resolved->scrollProps,
            deferredProps: $resolved->deferredProps,
            rescuedProps: $resolved->rescuedProps,
            sharedProps: $resolved->sharedProps,
            onceProps: $resolved->onceProps,
            flash: $resolved->flash,
        );

        return $request->isInertia()
            ? new InertiaPageResult($page, $resolved->rescuedFailures)
            : new InitialPageResult($page, $resolved->rescuedFailures);
    }

    /**
     * Returns a redirect result, automatically adjusting for Inertia protocol rules.
     *
     * Converts `302` to `303` for `PUT`/`PATCH`/`DELETE` Inertia requests. Returns a {@see FragmentRedirectResult} when
     * the URL contains a fragment and the request is a non-prefetch Inertia visit.
     *
     * @param RequestContext $request Validated request context from the framework adapter.
     * @param string $url Absolute or root-relative redirect target URL.
     * @param int $statusCode HTTP redirect status code (`301`, `302`, `303`, `307`, or `308`).
     *
     * @throws InvalidArgumentException When `$url` is invalid or `$statusCode` is not an allowed redirect status.
     */
    public function redirect(
        RequestContext $request,
        string $url,
        int $statusCode = 302,
    ): RedirectResult|FragmentRedirectResult {
        if (!self::isRedirectUrl($url)) {
            throw new InvalidArgumentException(
                Message::REDIRECT_URL_INVALID->getMessage(),
            );
        }

        if (!in_array($statusCode, [301, 302, 303, 307, 308], true)) {
            throw new InvalidArgumentException(
                Message::REDIRECT_STATUS_INVALID->getMessage(),
            );
        }

        if ($request->isInertia() && !$request->isPrefetch() && str_contains($url, '#')) {
            return new FragmentRedirectResult($this->absoluteRedirectUrl($request, $url));
        }

        if (
            $request->isInertia()
            && $statusCode === 302
            && in_array($request->method, ['PUT', 'PATCH', 'DELETE'], true)
        ) {
            $statusCode = 303;
        }

        return new RedirectResult($url, $statusCode);
    }

    /**
     * Resolves a relative redirect URL to an absolute URL using the request origin.
     *
     * @param RequestContext $request Validated request context from the framework adapter.
     * @param string $url Absolute or root-relative redirect target URL.
     *
     * @throws InvalidArgumentException When the request's absolute URL cannot be parsed into scheme and host.
     *
     * @return string Returns the absolute redirect URL.
     */
    private function absoluteRedirectUrl(RequestContext $request, string $url): string
    {
        if (self::isAbsoluteHttpUrl($url)) {
            return $url;
        }

        $parts = parse_url($request->absoluteUrl);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException(
                Message::REQUEST_ORIGIN_INVALID->getMessage(),
            );
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $parts['scheme'] . '://' . $parts['host'] . $port . $url;
    }

    /**
     * Returns `true` if `$url` is a valid absolute HTTP/HTTPS URL without credentials.
     *
     * @param string $url The URL to validate.
     *
     * @return bool `true` if the URL is valid, `false` otherwise.
     */
    private static function isAbsoluteHttpUrl(string $url): bool
    {
        if (preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            && $parts['host'] !== ''
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    /**
     * Returns `true` if `$url` is a valid redirect target (absolute HTTP/HTTPS or root-relative without `//`).
     *
     * @param string $url The URL to validate.
     *
     * @return bool `true` if the URL is valid, `false` otherwise.
     */
    private static function isRedirectUrl(string $url): bool
    {
        if (self::isAbsoluteHttpUrl($url)) {
            return true;
        }

        return $url !== ''
            && $url[0] === '/'
            && !str_starts_with($url, '//')
            && preg_match('/[\x00-\x20\x7F]/', $url) !== 1;
    }
}
