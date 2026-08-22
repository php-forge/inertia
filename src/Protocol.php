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

final readonly class Protocol
{
    private Clock $clock;

    public function __construct(Clock|null $clock = null)
    {
        $this->clock = $clock ?? new SystemClock();
    }

    public function location(RequestContext $request, string $absoluteUrl): RedirectResult|LocationResult
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
