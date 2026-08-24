<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

use PHPForge\Inertia\Page;

/**
 * Represents an initial page visit that a framework adapter renders as the root HTML document.
 */
final readonly class InitialPageResult implements PageResult
{
    /**
     * @param Page $page The resolved Inertia page to send as the initial HTML response.
     * @param list<RescuedPropFailure> $rescuedFailures Prop failures captured during resolution for adapter reporting.
     */
    public function __construct(private Page $page, private array $rescuedFailures = []) {}

    /**
     * Returns the initial-render response headers.
     *
     * @return array<string, string> HTTP response headers to send with the initial page response.
     */
    public function headers(): array
    {
        return ['Vary' => 'X-Inertia'];
    }

    /**
     * Returns the resolved Inertia page payload.
     *
     * @return Page The resolved Inertia page to send as the initial HTML response.
     */
    public function page(): Page
    {
        return $this->page;
    }

    /**
     * Returns prop failures that were rescued during resolution.
     *
     * @return list<RescuedPropFailure> Rescued prop failures, one per failed-and-rescued callback.
     */
    public function rescuedFailures(): array
    {
        return $this->rescuedFailures;
    }

    /**
     * Returns the HTTP status code (`200`).
     *
     * @return int HTTP status code for the initial page response.
     */
    public function statusCode(): int
    {
        return 200;
    }
}
