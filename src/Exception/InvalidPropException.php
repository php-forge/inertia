<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Exception;

use InvalidArgumentException;

/**
 * Reports invalid prop configuration or a value that cannot be represented in a page payload.
 */
final class InvalidPropException extends InvalidArgumentException {}
