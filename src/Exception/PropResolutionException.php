<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Exception;

use RuntimeException;
use Throwable;

/**
 * Identifies a prop callback that failed during resolution.
 */
final class PropResolutionException extends RuntimeException
{
    /**
     * @param string $propPath Dot-notation path of the prop whose callback threw.
     * @param Throwable $previous The original failure from the callback.
     */
    public function __construct(private readonly string $propPath, Throwable $previous)
    {
        parent::__construct(
            Message::PROP_RESOLUTION_FAILED->getMessage($propPath, $previous->getMessage()),
            previous: $previous,
        );
    }

    /**
     * Returns the dot-notation path of the prop whose callback failed.
     *
     * @return string The dot-notation path of the prop whose callback failed.
     */
    public function propPath(): string
    {
        return $this->propPath;
    }
}
