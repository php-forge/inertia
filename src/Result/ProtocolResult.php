<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

interface ProtocolResult
{
    /**
     * @return array<string, string>
     */
    public function headers(): array;

    public function statusCode(): int;
}
