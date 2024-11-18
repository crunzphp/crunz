<?php

declare(strict_types=1);

namespace Crunz\Tests\Unit\Console\Command;

use Crunz\Console\Command\ScheduleRunCommand;
use Crunz\Event;
use Crunz\EventRunner;
use Crunz\Schedule;
use Crunz\Schedule\ScheduleFactory;
use Crunz\Task\CollectionInterface;
use Crunz\Task\Loader;
use Crunz\Task\LoaderInterface;
use Crunz\Task\Timezone;
use Crunz\Tests\TestCase\FakeConfiguration;
use Crunz\Tests\TestCase\FakeTaskCollection;
use Crunz\Tests\TestCase\TemporaryFile;
use PHPUnit\Framework\MockObject\Generator\Generator as MockGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class ScheduleRunCommandTest extends TestCase
{
    /** @test */
    public function force_run_all_tasks(): void
    {
        $tempFile = new TemporaryFile();
        $filename = $this->createTaskFile($this->taskContent(), $tempFile);

        $mockInput = $this->mockInput(
            [
                'force' => true,
                'task' => null,
            ],
            ['source' => '']
        );
        $mockOutput = $this->createMock(OutputInterface::class);
        $mockTaskCollection = $this->mockTaskCollection($filename);
        $mockEventRunner = $this->mockEventRunner($mockOutput);

        $command = new ScheduleRunCommand(
            $mockTaskCollection,
            new FakeConfiguration(['source' => '']),
            $mockEventRunner,
            $this->createMock(Timezone::class),
            $this->createMock(ScheduleFactory::class),
            $this->createTaskLoader()
        );

        $command->run(
            $mockInput,
            $mockOutput
        );
    }

    /** @test */
    public function run_specific_task(): void
    {
        $tempFile1 = new TemporaryFile();
        $tempFile2 = new TemporaryFile();
        $filename1 = $this->createTaskFile($this->phpVersionTaskContent(), $tempFile1);
        $filename2 = $this->createTaskFile($this->phpVersionTaskContent(), $tempFile2);

        $mockInput = $this->mockInput(
            [
                'force' => false,
                'task' => '1',
            ],
            ['source' => '']
        );
        $mockOutput = $this->createMock(OutputInterface::class);
        $mockTaskCollection = $this->mockTaskCollection($filename1, $filename2);
        $mockEventRunner = $this->mockEventRunner($mockOutput);

        $command = new ScheduleRunCommand(
            $mockTaskCollection,
            new FakeConfiguration(['source' => '']),
            $mockEventRunner,
            self::mockTimezoneProvider(),
            $this->mockScheduleFactory(),
            $this->createTaskLoader()
        );

        $command->run(
            $mockInput,
            $mockOutput
        );
    }

    public static function mockTimezoneProvider(): MockObject&Timezone
    {
        $timeZone = new \DateTimeZone('UTC');
        /** @var MockObject&Timezone $timezoneProviderMock */
        $timezoneProviderMock = (new MockGenerator())->testDouble(
            Timezone::class,
            true,
            [],
            [],
            '',
            false,
            false,
            true,
            false,
            false,
            null,
            false,
        );
        $timezoneProviderMock->method('timezoneForComparisons')->willReturn($timeZone);

        return $timezoneProviderMock;
    }

    private function mockScheduleFactory(): ScheduleFactory
    {
        $mockEvent = $this->createMock(Event::class);
        $mockSchedule = $this->createConfiguredMock(Schedule::class, ['events' => [$mockEvent]]);
        $mockScheduleFactory = $this->createMock(ScheduleFactory::class);
        $mockScheduleFactory
            ->expects(self::once())
            ->method('singleTaskSchedule')
            ->willReturn([$mockSchedule])
        ;

        return $mockScheduleFactory;
    }

    /** @return EventRunner|MockObject */
    private function mockEventRunner(OutputInterface $output): EventRunner
    {
        $mockEventRunner = $this->createMock(EventRunner::class);
        $mockEventRunner
            ->expects(self::once())
            ->method('handle')
            ->with(
                $output,
                self::callback(
                    function ($schedules) {
                        $isArray = \is_array($schedules);
                        $count = \is_countable($schedules) ? \count($schedules) : 0;
                        $schedule = \reset($schedules);

                        return $isArray
                            && 1 === $count
                            && $schedule instanceof Schedule
                        ;
                    }
                )
            )
        ;

        return $mockEventRunner;
    }

    /**
     * @param array<string,bool|string|null> $options
     * @param array<string,bool|string|null> $arguments
     *
     * @return MockObject|InputInterface
     */
    private function mockInput(array $options, array $arguments = []): InputInterface
    {
        $mockInput = $this->createMock(InputInterface::class);
        $mockInput
            ->method('getOptions')
            ->willReturn($options)
        ;
        $mockInput
            ->method('getArguments')
            ->willReturn($arguments)
        ;

        return $mockInput;
    }

    private function mockTaskCollection(string ...$taskFiles): CollectionInterface
    {
        $mocksFileInfo = \array_map(
            fn ($taskFile) => $this->createConfiguredMock(\SplFileInfo::class, ['getRealPath' => $taskFile]),
            $taskFiles
        );

        return new FakeTaskCollection($mocksFileInfo);
    }

    private function createTaskFile(string $taskContent, TemporaryFile $file): string
    {
        $filesystem = new Filesystem();

        $filename = $file->filePath();
        $filesystem->touch($filename);
        $filesystem->dumpFile($filename, $taskContent);

        return $filename;
    }

    private function taskContent(): string
    {
        return <<<'PHP_WRAP'
            <?php
            
            use Crunz\Schedule;
            
            $schedule = new Schedule();
            
            $schedule->run('php -v')
                ->description('Show PHP version')
                // Always skip
                ->skip(static function () {return true;})
            ;
            
            return $schedule;
            PHP_WRAP;
    }

    private function phpVersionTaskContent(): string
    {
        return <<<'PHP_WRAP'
            <?php
            
            use Crunz\Schedule;
            
            $schedule = new Schedule();
            
            $schedule->run('php -v')
                ->everyMinute()
                ->description('Show PHP version')
            ;
            
            return $schedule;
            PHP_WRAP;
    }

    private function createTaskLoader(): LoaderInterface
    {
        return new Loader();
    }
}
