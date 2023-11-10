<?php

declare(strict_types=1);

namespace Crunz\Tests\Unit\Console\Command;

use Crunz\Console\Command\ScheduleListCommand;
use Crunz\Exception\CrunzException;
use Crunz\Schedule;
use Crunz\Tests\TestCase\FakeConfiguration;
use Crunz\Tests\TestCase\FakeLoader;
use Crunz\Tests\TestCase\Faker;
use Crunz\Tests\TestCase\FakeTaskCollection;
use Crunz\Tests\TestCase\UnitTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

final class ScheduleListCommandTest extends UnitTestCase
{
    public function test_passing_unsupported_format_fails(): void
    {
        // Arrange
        $format = Faker::word();
        $command = $this->createCommand();

        // Expect
        $this->expectException(CrunzException::class);
        $this->expectExceptionMessage("Format '{$format}' is not supported.");

        // Act
        $command->run(
            $this->createInput($format),
            new NullOutput(),
        );
    }

    /** @dataProvider formatProvider */
    public function test_list_output_format(\Closure $paramsGenerator): void
    {
        // Arrange
        /**
         * @var string   $format
         * @var string   $expectedOutput
         * @var \Closure $assert
         */
        [
            'format' => $format,
            'expectedOutput' => $expectedOutput,
            'assert' => $assert,
        ] = $paramsGenerator();
        $output = new BufferedOutput();
        $commandString = 'php -v';
        $cronExpression = '15 3 * * 1,3,5';
        $description = 'PHP version';
        $schedule = self::createScheduleWithTask(
            $commandString,
            $description,
            $cronExpression,
        );
        $command = $this->createCommand([$schedule]);

        // Act
        $command->run(
            $this->createInput($format),
            $output,
        );

        // Assert
        $assert($expectedOutput, $output->fetch());
    }

    /** @return iterable<string,array{\Closure}> */
    public static function formatProvider(): iterable
    {
        yield 'text' => [
            function (): array {
                $commandString = 'php -v';
                $cronExpression = '15 3 * * 1,3,5';
                $description = 'PHP version';
                $schedule = self::createScheduleWithTask(
                    $commandString,
                    $description,
                    $cronExpression,
                );

                return [
                    'format' => 'text',
                    'schedule' => $schedule,
                    'expectedOutput' => <<<TXT
                        +---+-------------+----------------+----------------+
                        | # | Task        | Expression     | Command to Run |
                        +---+-------------+----------------+----------------+
                        | 1 | PHP version | 15 3 * * 1,3,5 | php -v         |
                        +---+-------------+----------------+----------------+
                        
                        TXT,
                    'assert' => static function (string $expectedOutput, string $actualOutput): void {
                        self::assertSame($expectedOutput, $actualOutput);
                    },
                ];
            },
        ];

        yield 'json' => [
            function (): array {
                $commandString = 'php -v';
                $cronExpression = '15 3 * * 1,3,5';
                $description = 'PHP version';
                $schedule = self::createScheduleWithTask(
                    $commandString,
                    $description,
                    $cronExpression,
                );

                return [
                    'format' => 'json',
                    'schedule' => $schedule,
                    'expectedOutput' => self::encodeJson(
                        [
                            [
                                'command' => $commandString,
                                'expression' => $cronExpression,
                                'number' => 1,
                                'task' => $description,
                            ],
                        ],
                    ),
                    'assert' => static function (string $expectedOutput, string $actualOutput): void {
                        self::assertJsonStringEqualsJsonString($expectedOutput, $actualOutput);
                    },
                ];
            },
        ];
    }

    /** @param Schedule[] $schedules */
    private function createCommand(array $schedules = []): ScheduleListCommand
    {
        return new ScheduleListCommand(
            new FakeConfiguration(),
            new FakeTaskCollection(),
            new FakeLoader($schedules),
        );
    }

    private function createInput(string $format): InputInterface
    {
        return new ArrayInput(
            [
                '--format' => $format,
            ]
        );
    }

    private static function createScheduleWithTask(
        string $command,
        string $description,
        string $cronExpression,
    ): Schedule {
        $schedule = new Schedule();
        $schedule
            ->run($command)
            ->description($description)
            ->cron($cronExpression)
        ;

        return $schedule;
    }
}
