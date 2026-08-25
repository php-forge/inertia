<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

/**
 * Represents an asset-version mismatch that instructs the client to perform a full-page visit.
 */
final readonly class VersionConflictResult implements ProtocolResult
{
    /**
     * @param string $url Absolute URL the client should reload.
     * @param int|string $version Current asset version reported in the `X-Inertia-Version` header.
     */
    public function __construct(public string $url, public string|int $version) {}

    /**
     * Returns the Inertia version-conflict headers.
     *
     * @return array<string, string> HTTP response headers to send with the version-conflict response.
     */
    public function headers(): array
    {
        return [
            'X-Inertia-Location' => $this->url,
            'X-Inertia-Version' => (string) $this->version,
            'Vary' => 'X-Inertia',
        ];
    }

    /**
     * Returns the HTTP status code (`409`) used by the Inertia version-conflict protocol.
     *
     * @return int HTTP status code.
     */
    public function statusCode(): int
    {
        return 409;
    }
}
