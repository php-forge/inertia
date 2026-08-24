<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

use PHPForge\Inertia\Page;

/**
 * Represents an Inertia visit that a framework adapter serializes as a JSON page response.
 */
final readonly class InertiaPageResult implements PageResult
{
    /**
     * @param Page $page The resolved Inertia page to send as a JSON response.
     * @param list<RescuedPropFailure> $rescuedFailures Prop failures captured during resolution for adapter reporting.
     */
    public function __construct(private Page $page, private array $rescuedFailures = []) {}

    /**
     * Returns the Inertia JSON-response headers.
     *
     * @return array<string, string> HTTP response headers to send with the Inertia page response.
     */
    public function headers(): array
    {
        return [
            'X-Inertia' => 'true',
            'Vary' => 'X-Inertia',
        ];
    }

    /**
     * Returns the resolved Inertia page payload.
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
     * @return int HTTP status code for the Inertia page response.
     */
    public function statusCode(): int
    {
        return 200;
    }
}
