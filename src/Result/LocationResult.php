<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

final readonly class LocationResult implements ProtocolResult
{
    public function __construct(public string $url) {}

    public function headers(): array
    {
        return [
            'X-Inertia-Location' => $this->url,
            'Vary' => 'X-Inertia',
        ];
    }

    public function statusCode(): int
    {
        return 409;
    }
}
