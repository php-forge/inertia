<?php

declare(strict_types=1);

namespace PHPForge\Inertia\Tests\Fixture;

final class CallRecorder
{
    /**
     * @var list<string>
     */
    private array $calls = [];

    public function count(): int
    {
        return count($this->calls);
    }

    public function record(string $name = 'call'): void
    {
        $this->calls[] = $name;
    }

    /**
     * @return list<string>
     */
    public function recordedCalls(): array
    {
        return $this->calls;
    }
}
