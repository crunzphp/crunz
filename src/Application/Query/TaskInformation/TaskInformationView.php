<?php

declare(strict_types=1);

namespace Crunz\Application\Query\TaskInformation;

final class TaskInformationView
{
    /** @var \DateTimeImmutable[] */
    private array $nextRuns;

    public function __construct(
        private string|object $command,
        private string $description,
        private string $cronExpression,
        private bool $preventOverlapping,
        private ?\DateTimeZone $timeZone,
        private \DateTimeZone $configTimeZone,
        \DateTimeImmutable ...$nextRuns
    ) {
        $this->nextRuns = $nextRuns;
    }

    public function command(): string|object
    {
        return $this->command;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function cronExpression(): string
    {
        return $this->cronExpression;
    }

    public function timeZone(): ?\DateTimeZone
    {
        return $this->timeZone;
    }

    public function configTimeZone(): \DateTimeZone
    {
        return $this->configTimeZone;
    }

    /** @return \DateTimeImmutable[] */
    public function nextRuns(): array
    {
        return $this->nextRuns;
    }

    public function preventOverlapping(): bool
    {
        return $this->preventOverlapping;
    }
}
