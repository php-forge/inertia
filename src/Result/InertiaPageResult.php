<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

use PHPForge\Inertia\Page;

final readonly class InertiaPageResult implements PageResult
{
    /**
     * @param list<RescuedPropFailure> $rescuedFailures
     */
    public function __construct(
        private Page $page,
        private array $rescuedFailures = [],
    ) {}

    public function headers(): array
    {
        return [
            'X-Inertia' => 'true',
            'Vary' => 'X-Inertia',
        ];
    }

    public function page(): Page
    {
        return $this->page;
    }

    public function rescuedFailures(): array
    {
        return $this->rescuedFailures;
    }

    public function statusCode(): int
    {
        return 200;
    }
}
