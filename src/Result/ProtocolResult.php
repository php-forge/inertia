<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

/**
 * Defines the status and headers that a framework adapter applies to an HTTP response.
 */
interface ProtocolResult
{
    /**
     * Returns the response headers to apply to the framework HTTP response.
     *
     * @return array<string, string> Response headers keyed by header name.
     */
    public function headers(): array;

    /**
     * Returns the HTTP status code to apply to the framework response.
     *
     * @return int HTTP status code.
     */
    public function statusCode(): int;
}
