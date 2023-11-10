<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase\Logger;

use Psr\Log\AbstractLogger;

final class SpyPsrLogger extends AbstractLogger
{
    /** @var array<int,array> */
    private array $logs = [];

    public function log($level, string|\Stringable $message, array $context = []): void
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
