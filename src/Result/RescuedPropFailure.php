<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Result;

use Throwable;

/**
 * Records a prop callback failure that was rescued during page resolution.
 */
final readonly class RescuedPropFailure
{
    /**
     * @param string $propPath Dot-notation path of the prop whose callback was rescued.
     * @param Throwable $failure  The original failure from the callback.
     */
    public function __construct(public string $propPath, public Throwable $failure) {}
}
