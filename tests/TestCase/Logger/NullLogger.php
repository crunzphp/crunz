<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase\Logger;

use Crunz\Logger\ConsoleLoggerInterface;

final class NullLogger implements ConsoleLoggerInterface
{
    public function normal($message): void
    {
        // No-op
    }

    public function verbose($message): void
    {
        // No-op
    }

    public function veryVerbose($message): void
    {
        // No-op
    }

    public function debug($message): void
    {
        // No-op
    }
}
