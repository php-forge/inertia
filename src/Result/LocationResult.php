<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

/**
 * Represents an Inertia external-location visit using a `409` protocol response.
 */
final readonly class LocationResult implements ProtocolResult
{
    /**
     * @param string $url Absolute URL of the external location, sent to the client via `X-Inertia-Location`.
     */
    public function __construct(public string $url) {}

    /**
     * Returns the Inertia external-location headers.
     *
     * @return array<string, string> HTTP response headers to send with the external-location response.
     */
    public function headers(): array
    {
        return [
            'X-Inertia-Location' => $this->url,
            'Vary' => 'X-Inertia',
        ];
    }

    /**
     * Returns the HTTP status code (`409`) used by the Inertia external-location protocol.
     *
     * @return int HTTP status code for the external-location response.
     */
    public function statusCode(): int
    {
        return 409;
    }
}
