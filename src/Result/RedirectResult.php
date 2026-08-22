<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

final readonly class RedirectResult implements ProtocolResult
{
    public function __construct(
        public string $url,
        private int $statusCode = 302,
    ) {}

    public function headers(): array
    {
        return [
            'Location' => $this->url,
            'Vary' => 'X-Inertia',
        ];
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
