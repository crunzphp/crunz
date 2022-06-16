<?php

declare(strict_types=1);

namespace Crunz\Tests\Unit\Process;

use Crunz\Process\Process;
use Crunz\Tests\TestCase\UnitTestCase;

final class ProcessTest extends UnitTestCase
{
    public function test_command_line_built_from_array(): void
    {
        // Arrange
        $expectedCommandLine = "'php' '-v' '--ini' '-d' 'memory_limit=123M'";

        // Act
        $process = Process::fromArrayCommand(
            [
                'php',
                '-v',
                '--ini',
                '-d',
                'memory_limit=123M',
            ],
        );

        // Assert
        self::assertSame($expectedCommandLine, $process->commandLine());
    }
}
