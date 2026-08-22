<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

final readonly class VersionConflictResult implements ProtocolResult
{
    public function __construct(
        public string $url,
        public string|int $version,
    ) {}

    public function headers(): array
    {
        return [
            'X-Inertia-Location' => $this->url,
            'X-Inertia-Version' => (string) $this->version,
            'Vary' => 'X-Inertia',
        ];
    }

    public function statusCode(): int
    {
        return 409;
    }
}
