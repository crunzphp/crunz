<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase;

final class SerializableTaskRunnerStub
{
    public string $taskName = 'daily-report';

    /** @var \Closure[] */
    public array $filters = [];

    /** @return array{taskName: string, filters: array<\Closure>} */
    public function __serialize(): array
    {
        return [
            'taskName' => $this->taskName,
            'filters' => $this->filters,
        ];
    }

    /** @param array{taskName: string, filters: array<\Closure>} $data */
    public function __unserialize(array $data): void
    {
        $this->taskName = $data['taskName'];
        $this->filters = $data['filters'];
    }

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
