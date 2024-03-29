<?php

declare(strict_types=1);

namespace Crunz\Task;

use Crunz\Schedule;

final class WrongTaskInstanceException extends TaskException
{
    public static function fromFilePath(\SplFileInfo $filePath, mixed $schedule): self
    {
        $expectedInstance = Schedule::class;
        $type = \get_debug_type($schedule);
        $path = $filePath->getRealPath();

        return new self(
            "Task at path '{$path}' returned '{$type}', but '{$expectedInstance}' instance is required."
        );
    }
}
