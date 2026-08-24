<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

/**
 * Represents a standard HTTP redirect with its validated status code and target URL.
 */
final readonly class RedirectResult implements ProtocolResult
{
    /**
     * @param string $url Redirect target URL.
     * @param int $statusCode HTTP redirect status code. Defaults to `302`.
     */
    public function __construct(public string $url, private int $statusCode = 302) {}

    /**
     * Returns the redirect response headers.
     *
     * @return array<string, string> HTTP response headers to send with the redirect response.
     */
    public function headers(): array
    {
        return [
            'Location' => $this->url,
            'Vary' => 'X-Inertia',
        ];
    }

    /**
     * Returns the HTTP redirect status code.
     *
     * @return int HTTP redirect status code.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
