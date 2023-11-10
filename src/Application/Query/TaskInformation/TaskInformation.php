<?php

declare(strict_types=1);

namespace Crunz\Application\Query\TaskInformation;

use Crunz\Task\TaskNumber;

final class TaskInformation
{
    public function __construct(private readonly TaskNumber $taskNumber)
    {
    }

    public function taskNumber(): TaskNumber
    {
        return $this->taskNumber;
    }
}
