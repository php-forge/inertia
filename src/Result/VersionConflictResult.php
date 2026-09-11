<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

use PHPForge\Inertia\Header;

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
            Header::LOCATION->value => $this->url,
            Header::VERSION->value => (string) $this->version,
            Header::VARY->value => Header::INERTIA->value,
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
