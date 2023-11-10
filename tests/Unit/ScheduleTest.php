<?php

declare(strict_types=1);

namespace Crunz\Tests\Unit;

use Crunz\Event;
use Crunz\Schedule;
use Crunz\Tests\TestCase\UnitTestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

final class ScheduleTest extends UnitTestCase
{
    use ExpectDeprecationTrait;

    /** @dataProvider runProvider */
    public function test_run(\Closure $paramsGenerator): void
    {
        // Arrange
        /**
         * @var string   $command
         * @var string[] $parameters
         * @var string   $expectedCommand
         */
        [
            'command' => $command,
            'parameters' => $parameters,
            'expectedCommand' => $expectedCommand,
        ] = $paramsGenerator();
        $schedule = new Schedule();

        // Act
        $event = $schedule->run($command, $parameters);

        // Assert
        $this->assertCommand($expectedCommand, $event);
    }

    /**
     * @group legacy
     *
     * @dataProvider nonStringParametersProvider
     */
    public function test_run_with_non_string_parameters(\Closure $paramsGenerator): void
    {
        // Arrange
        /**
         * @var string   $command
         * @var string[] $parameters
         * @var string   $expectedCommand
         */
        [
            'command' => $command,
            'parameters' => $parameters,
            'expectedCommand' => $expectedCommand,
        ] = $paramsGenerator();
        $schedule = new Schedule();

        // Expect
        $this->expectDeprecation('Passing non-string parameters is deprecated since v3.3, convert all parameters to string.');

        // Act
        $event = $schedule->run($command, $parameters);

        // Assert
        $this->assertCommand($expectedCommand, $event);
    }

    /** @return iterable<string, array{\Closure}> */
    public static function runProvider(): iterable
    {
        yield 'simple command' => [
            static fn (): array => [
                'command' => '/usr/bin/php',
                'parameters' => [],
                'expectedCommand' => '/usr/bin/php',
            ],
        ];

        yield 'command with inline argument' => [
            static fn (): array => [
                'command' => '/usr/bin/php -v',
                'parameters' => [],
                'expectedCommand' => '/usr/bin/php -v',
            ],
        ];

        yield 'command with argument' => [
            static fn (): array => [
                'command' => '/usr/bin/php',
                'parameters' => ['-v'],
                'expectedCommand' => "/usr/bin/php '-v'",
            ],
        ];

        yield 'command with option' => [
            static fn (): array => [
                'command' => '/usr/bin/php',
                'parameters' => ['--ini' => 'php.ini'],
                'expectedCommand' => "/usr/bin/php '--ini' 'php.ini'",
            ],
        ];

        yield 'command with mixed parameters' => [
            static fn (): array => [
                'command' => '/usr/bin/php',
                'parameters' => ['--ini' => 'php.ini', '-v'],
                'expectedCommand' => "/usr/bin/php '--ini' 'php.ini' '-v'",
            ],
        ];
    }

    /** @return iterable<string, array{\Closure}> */
    public static function nonStringParametersProvider(): iterable
    {
        yield 'boolean true parameter' => [
            static fn (): array => [
                'command' => '/usr/bin/php',
                'parameters' => ['-v' => true],
                'expectedCommand' => "/usr/bin/php '-v' '1'",
            ],
        ];

        yield 'boolean false parameter' => [
            static fn (): array => [
                'command' => '/usr/bin/php',
                'parameters' => ['-v' => false],
                'expectedCommand' => "/usr/bin/php '-v' '0'",
            ],
        ];

        yield 'int parameter' => [
            static fn (): array => [
                'command' => '/usr/bin/php',
                'parameters' => ['-v' => 4],
                'expectedCommand' => "/usr/bin/php '-v' '4'",
            ],
        ];

        yield 'float parameter' => [
            static fn (): array => [
                'command' => '/usr/bin/php',
                'parameters' => ['-v' => 3.14],
                'expectedCommand' => "/usr/bin/php '-v' '3.14'",
            ],
        ];
    }

    private function assertCommand(string $expectedCommand, Event $event): void
    {
        if (IS_WINDOWS === true) {
            $expectedCommand = \str_replace(
                "'",
                '',
                $expectedCommand,
            );
        }

        self::assertSame($expectedCommand, $event->getCommand());
    }
}
