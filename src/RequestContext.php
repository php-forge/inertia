<?php

declare(strict_types=1);

namespace PHPForge\Inertia;

use PHPForge\Inertia\Exception\{InvalidRequestContextException, Message};

use function array_fill_keys;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function parse_url;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * Represents validated, framework-neutral request data used by the Inertia protocol.
 */
final readonly class RequestContext
{
    /**
     * Header name for the Inertia error bag.
     */
    public const string HEADER_ERROR_BAG = 'X-Inertia-Error-Bag';
    /**
     * Header name for the Inertia once-prop cache keys to exclude from the response.
     */
    public const string HEADER_EXCEPT_ONCE_PROPS = 'X-Inertia-Except-Once-Props';
    /**
     * Header name for the Inertia infinite-scroll merge intent.
     */
    public const string HEADER_INERTIA = 'X-Inertia';
    /**
     * Header name for the Inertia infinite-scroll merge intent.
     */
    public const string HEADER_INFINITE_SCROLL_MERGE_INTENT = 'X-Inertia-Infinite-Scroll-Merge-Intent';
    /**
     * Header name for the Inertia partial reload component name.
     */
    public const string HEADER_PARTIAL_COMPONENT = 'X-Inertia-Partial-Component';
    /**
     * Header name for the Inertia partial reload prop paths to include in the response.
     */
    public const string HEADER_PARTIAL_DATA = 'X-Inertia-Partial-Data';
    /**
     * Header name for the Inertia partial reload prop paths to exclude from the response.
     */
    public const string HEADER_PARTIAL_EXCEPT = 'X-Inertia-Partial-Except';
    /**
     * Header name for the Inertia prefetch purpose.
     */
    public const string HEADER_PURPOSE = 'Purpose';
    /**
     * Header name for the Inertia reset prop paths.
     */
    public const string HEADER_RESET = 'X-Inertia-Reset';
    /**
     * Header name for the Inertia asset version.
     */
    public const string HEADER_VERSION = 'X-Inertia-Version';

    /**
     * @var string The HTTP request method, normalized to uppercase.
     */
    public string $method;

    /**
     * @var array<string, string> Supported Inertia headers, keyed by lowercase name for case-insensitive lookup.
     */
    private array $headers;

    /**
     * @param string $method The HTTP request method (for example, `GET`, `POST`), case-insensitive.
     * @param string $url The request URL, relative to the application root (e.g., `/users/123`), without a fragment.
     * @param string $absoluteUrl The absolute request URL, including scheme and host (for example,
     * `https://example.com/users/123`), without credentials or a fragment.
     * @param array<mixed> $headers Raw HTTP headers; unsupported names are silently discarded.
     */
    public function __construct(
        string $method,
        public string $url,
        public string $absoluteUrl,
        array $headers = [],
    ) {
        $method = strtoupper(trim($method));

        if (preg_match('/^[A-Z][A-Z0-9!#$%&\'*+.^_`|~-]*$/', $method) !== 1) {
            throw new InvalidRequestContextException(
                Message::REQUEST_METHOD_INVALID->getMessage(),
            );
        }

        if (!self::isRelativeRequestUrl($url)) {
            throw new InvalidRequestContextException(
                Message::REQUEST_URL_INVALID->getMessage(),
            );
        }

        if (!self::isAbsoluteHttpUrl($absoluteUrl)) {
            throw new InvalidRequestContextException(
                Message::ABSOLUTE_REQUEST_URL_INVALID->getMessage(),
            );
        }

        $this->method = $method;

        $this->headers = self::normalizeHeaders($headers);
    }

    /**
     * Returns the error bag name from the request, or `null` if absent or blank.
     *
     * @return string|null The error bag name from the request, or `null` if absent or blank.
     */
    public function errorBag(): string|null
    {
        $trimmed = trim($this->header(self::HEADER_ERROR_BAG) ?? '');

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Returns the list of once-prop keys the client reports as already loaded.
     *
     * @return list<string> Deduplicated list of once-prop cache keys the client reports as already loaded.
     */
    public function exceptOnceProps(): array
    {
        return $this->headerList(self::HEADER_EXCEPT_ONCE_PROPS);
    }

    /**
     * Returns `true` if the named protocol header was included in the request (case-insensitive lookup).
     *
     * @param string $name The name of the header to check for (case-insensitive).
     *
     * @return bool `true` if the named protocol header was included in the request, otherwise `false`.
     */
    public function hasHeader(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->headers);
    }

    /**
     * Returns the value of the named protocol header, or `null` if not present.
     *
     * @param string $name The name of the header to retrieve (case-insensitive).
     *
     * @return string|null The value of the named protocol header, or `null` if not present.
     */
    public function header(string $name): string|null
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Returns a parsed, deduplicated list of values from a comma-separated header.
     *
     * @param string $name The name of the header to parse (case-insensitive).
     *
     * @return list<string> Deduplicated, trimmed list of comma-separated values from the named header.
     */
    public function headerList(string $name): array
    {
        $value = $this->header($name);

        if ($value === null) {
            return [];
        }

        $items = array_map(trim(...), explode(',', $value));
        $items = array_filter($items, static fn(string $item): bool => $item !== '');

        return array_values(array_unique($items));
    }

    /**
     * Returns the infinite-scroll merge intent: `'prepend'` or `'append'` (default).
     *
     * @return string The infinite-scroll merge intent: `'prepend'` or `'append'` (default).
     */
    public function infiniteScrollMergeIntent(): string
    {
        return $this->normalizedHeader(self::HEADER_INFINITE_SCROLL_MERGE_INTENT) === 'prepend'
            ? 'prepend'
            : 'append';
    }

    /**
     * Returns `true` if the request method is `GET`.
     *
     * @return bool `true` if the request method is `GET`, otherwise `false`.
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * Returns `true` if the `X-Inertia` header is set to `true` or `1`.
     *
     * @return bool `true` if the `X-Inertia` header is set to `true` or `1`, otherwise `false`.
     */
    public function isInertia(): bool
    {
        $value = $this->normalizedHeader(self::HEADER_INERTIA);

        return $value === 'true' || $value === '1';
    }

    /**
     * Returns `true` if this is a partial reload targeting the given component name.
     *
     * @param string $component The component name to check for a partial reload.
     *
     * @return bool `true` if this is a partial reload targeting the given component name, otherwise `false`.
     */
    public function isPartialReloadFor(string $component): bool
    {
        return $this->isInertia() && $this->header(self::HEADER_PARTIAL_COMPONENT) === $component;
    }

    /**
     * Returns `true` if the `Purpose: prefetch` header is set.
     *
     * @return bool `true` if the `Purpose: prefetch` header is set, otherwise `false`.
     */
    public function isPrefetch(): bool
    {
        return $this->normalizedHeader(self::HEADER_PURPOSE) === 'prefetch';
    }

    /**
     * Returns the list of prop paths requested in a partial reload.
     *
     * @return list<string> Prop paths the client explicitly requests for this partial reload.
     */
    public function partialData(): array
    {
        return $this->headerList(self::HEADER_PARTIAL_DATA);
    }

    /**
     * Returns the list of prop paths excluded from a partial reload.
     *
     * @return list<string> Prop paths excluded from this partial reload response.
     */
    public function partialExcept(): array
    {
        return $this->headerList(self::HEADER_PARTIAL_EXCEPT);
    }

    /**
     * Returns the client's Inertia asset version from `X-Inertia-Version`, or `null` if not sent.
     *
     * @return string|null The client's Inertia asset version from `X-Inertia-Version`, or `null` if not sent.
     */
    public function requestVersion(): string|null
    {
        return $this->header(self::HEADER_VERSION);
    }

    /**
     * Returns the list of prop paths the client requests be reset to their initial value.
     *
     * @return list<string> Prop paths the client requests to reset to their initial server values.
     */
    public function resetProps(): array
    {
        return $this->headerList(self::HEADER_RESET);
    }

    /**
     * Returns `true` if `$url` is a valid absolute HTTP/HTTPS URL without credentials or fragments.
     *
     * @param string $url The URL to validate.
     *
     * @return bool `true` if `$url` is a valid absolute HTTP/HTTPS URL without credentials or fragments, otherwise
     * `false`.
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
            && !isset($parts['pass'])
            && !isset($parts['fragment']);
    }

    /**
     * Returns `true` if `$url` is a valid root-relative request URL (no fragment, no control characters).
     *
     * @param string $url The URL to validate.
     *
     * @return bool `true` if `$url` is a valid root-relative request URL (no fragment, no control characters),
     * otherwise `false`.
     */
    private static function isRelativeRequestUrl(string $url): bool
    {
        if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//')) {
            return false;
        }

        if (preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return false;
        }

        return !str_contains($url, '#');
    }

    /**
     * Returns the header value lowercased and trimmed, or an empty string if the header is absent.
     *
     * @param string $name The name of the header to normalize (case-insensitive).
     *
     * @return string The header value lowercased and trimmed, or an empty string if the header is absent.
     */
    private function normalizedHeader(string $name): string
    {
        return strtolower(trim($this->header($name) ?? ''));
    }

    /**
     * Normalizes and filters raw headers to the supported Inertia protocol set.
     *
     * @param array<array-key, mixed> $headers Raw HTTP headers indexed by name.
     *
     * @return array<string, string> Supported Inertia headers, normalized to lowercase names, indexed by name.
     */
    private static function normalizeHeaders(array $headers): array
    {
        $supported = array_fill_keys(
            array_map(
                strtolower(...),
                [
                    self::HEADER_ERROR_BAG,
                    self::HEADER_EXCEPT_ONCE_PROPS,
                    self::HEADER_INERTIA,
                    self::HEADER_INFINITE_SCROLL_MERGE_INTENT,
                    self::HEADER_PARTIAL_COMPONENT,
                    self::HEADER_PARTIAL_DATA,
                    self::HEADER_PARTIAL_EXCEPT,
                    self::HEADER_PURPOSE,
                    self::HEADER_RESET,
                    self::HEADER_VERSION,
                ],
            ),
            true,
        );

        $normalized = [];

        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new InvalidRequestContextException(
                    Message::REQUEST_HEADERS_INVALID->getMessage(),
                );
            }

            $name = strtolower(trim($name));

            if (!isset($supported[$name])) {
                continue;
            }

            if (preg_match('/[\r\n]/', $value) === 1) {
                throw new InvalidRequestContextException(
                    Message::REQUEST_HEADER_LINE_BREAK_INVALID->getMessage(),
                );
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }
}
