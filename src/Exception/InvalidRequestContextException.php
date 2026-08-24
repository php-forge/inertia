<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Exception;

use InvalidArgumentException;

/**
 * Reports invalid request data supplied by a framework adapter.
 */
final class InvalidRequestContextException extends InvalidArgumentException {}
