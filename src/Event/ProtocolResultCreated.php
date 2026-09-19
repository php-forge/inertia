<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Event;

use PHPForge\Inertia\RequestContext;
use PHPForge\Inertia\Result\ProtocolResult;

/**
 * Announces a completed protocol operation, not the framework's eventual HTTP response.
 */
final readonly class ProtocolResultCreated
{
    /**
     * @param RequestContext $request Validated request context the protocol operation was resolved for.
     * @param ProtocolResult $result Protocol result produced for the request.
     */
    public function __construct(public RequestContext $request, public ProtocolResult $result) {}
}
