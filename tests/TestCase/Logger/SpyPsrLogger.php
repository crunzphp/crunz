<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase\Logger;

use Psr\Log\AbstractLogger;

final class SpyPsrLogger extends AbstractLogger
{
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level   The log level (e.g., 'error', 'info').
     * @param string $message The log message.
     * @param array  $context Additional context for the log message.
     */

    private array $logs = [];

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    /** @return array<int,array> */
    public function getLogs(): array
    {
        return $this->logs;
    }
}
