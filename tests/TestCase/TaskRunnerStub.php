<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase;

final class TaskRunnerStub
{
    public string $taskName = 'daily-report';

    /** @var \Closure[] */
    public array $filters = [];

    public function createTask(): \Closure
    {
        $this->filters[] = static function (): bool {
            return true;
        };

        return function (): string {
            return "running {$this->taskName}";
        };
    }
}
