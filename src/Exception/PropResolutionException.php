<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Exception;

use RuntimeException;
use Throwable;

final class PropResolutionException extends RuntimeException
{
    public function __construct(
        private readonly string $propPath,
        Throwable $previous,
    ) {
        parent::__construct(
            Message::PROP_RESOLUTION_FAILED->getMessage($propPath, $previous->getMessage()),
            previous: $previous,
        );
    }

    public function propPath(): string
    {
        return $this->propPath;
    }
}
