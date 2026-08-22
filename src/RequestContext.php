<?php

declare(strict_types=1);

namespace PHPForge\Inertia;

use PHPForge\Inertia\Exception\{InvalidRequestContextException, Message};

use function array_key_exists;
use function in_array;
use function is_array;

final readonly class RequestContext
{
    public const string HEADER_ERROR_BAG = 'X-Inertia-Error-Bag';
    public const string HEADER_EXCEPT_ONCE_PROPS = 'X-Inertia-Except-Once-Props';
    public const string HEADER_INERTIA = 'X-Inertia';
    public const string HEADER_INFINITE_SCROLL_MERGE_INTENT = 'X-Inertia-Infinite-Scroll-Merge-Intent';
    public const string HEADER_PARTIAL_COMPONENT = 'X-Inertia-Partial-Component';
    public const string HEADER_PARTIAL_DATA = 'X-Inertia-Partial-Data';
    public const string HEADER_PARTIAL_EXCEPT = 'X-Inertia-Partial-Except';
    public const string HEADER_PURPOSE = 'Purpose';
    public const string HEADER_RESET = 'X-Inertia-Reset';
    public const string HEADER_VERSION = 'X-Inertia-Version';

    public string $method;

    /**
     * @var array<string, string>
     */
    private array $headers;

    /**
     * @param array<string, string> $headers
     * @phpstan-param array<array-key, mixed> $headers
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

    public function errorBag(): string|null
    {
        $value = $this->header(self::HEADER_ERROR_BAG);

        return $value === null || trim($value) === '' ? null : trim($value);
    }

    /**
     * @return list<string>
     */
    public function exceptOnceProps(): array
    {
        return $this->headerList(self::HEADER_EXCEPT_ONCE_PROPS);
    }

    public function hasHeader(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->headers);
    }

    public function header(string $name): string|null
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * @return list<string>
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

    public function infiniteScrollMergeIntent(): string
    {
        return strtolower(trim($this->header(self::HEADER_INFINITE_SCROLL_MERGE_INTENT) ?? '')) === 'prepend'
            ? 'prepend'
            : 'append';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function isInertia(): bool
    {
        $value = strtolower(trim($this->header(self::HEADER_INERTIA) ?? ''));

        return $value === 'true' || $value === '1';
    }

    public function isPartialReloadFor(string $component): bool
    {
        return $this->isInertia() && $this->header(self::HEADER_PARTIAL_COMPONENT) === $component;
    }

    public function isPrefetch(): bool
    {
        return strtolower(trim($this->header(self::HEADER_PURPOSE) ?? '')) === 'prefetch';
    }

    /**
     * @return list<string>
     */
    public function partialData(): array
    {
        return $this->headerList(self::HEADER_PARTIAL_DATA);
    }

    /**
     * @return list<string>
     */
    public function partialExcept(): array
    {
        return $this->headerList(self::HEADER_PARTIAL_EXCEPT);
    }

    public function requestVersion(): string|null
    {
        return $this->header(self::HEADER_VERSION);
    }

    /**
     * @return list<string>
     */
    public function resetProps(): array
    {
        return $this->headerList(self::HEADER_RESET);
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
            && !isset($parts['pass'])
            && !isset($parts['fragment']);
    }

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
     * @param array<array-key, mixed> $headers
     *
     * @return array<string, string>
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
