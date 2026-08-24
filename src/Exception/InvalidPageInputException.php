<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Exception;

use InvalidArgumentException;

/**
 * Reports invalid component, prop, error, or flash input for a page operation.
 */
final class InvalidPageInputException extends InvalidArgumentException {}
