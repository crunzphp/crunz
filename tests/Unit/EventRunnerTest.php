<?php

declare(strict_types=1);

namespace Crunz\Tests\Unit;

use Crunz\EventRunner;
use Crunz\HttpClient\HttpClientInterface;
use Crunz\Invoker;
use Crunz\Logger\ConsoleLoggerInterface;
use Crunz\Logger\Logger;
use Crunz\Logger\LoggerFactory;
use Crunz\Mailer;
use Crunz\Schedule;
use Crunz\Tests\TestCase\FakeConfiguration;
use Crunz\Tests\TestCase\Logger\SpyPsrLogger;
use Crunz\Tests\TestCase\SpyConsoleOutput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\StoreInterface;

final class EventRunnerTest extends TestCase
{
    /** @test */
    public function url_is_pinged_before(): void
    {
        $url = 'https://ping-befo.re/';
        $output = $this->createMock(OutputInterface::class);

        $eventRunner = $this->createEventRunnerForPing($url);

        $schedule = new Schedule();
        $event = $schedule->run('php -v');
        $event->pingBefore($url);

        $eventRunner->handle($output, [$schedule]);
    }

    /** @test */
    public function url_is_pinged_after(): void
    {
        $url = 'https://ping-aft.er/';
        $output = $this->createMock(OutputInterface::class);

        $eventRunner = $this->createEventRunnerForPing($url);

        $schedule = new Schedule();
        $event = $schedule->run('php -v');
        $event->thenPing($url);

        $eventRunner->handle($output, [$schedule]);
    }

    public function test_realtime_output_is_streamed_raw_and_once(): void
    {
        // Output contains a Symfony style tag. The realtime path writes raw, so
        // the tag survives verbatim; the end-of-job display() path runs through
        // the output formatter, which would strip the tag. Asserting the literal
        // tag survives proves the realtime path produced the output.
        $taskOutput = '<info>RUNNER_REALTIME_MARKER</info>';
        $command = "php -r \"echo '{$taskOutput}';\"";

        $schedule = new Schedule();
        $schedule->run($command)
            ->everyMinute()
        ;

        $output = new BufferedOutput();
        $eventRunner = $this->createEventRunner(
            realInvoker: true,
            configuration: new FakeConfiguration(['output_realtime' => true]),
        );

        $eventRunner->handle($output, [$schedule]);

        $captured = $output->fetch();

        // Streamed verbatim (raw), not formatter-stripped.
        self::assertStringContainsString($taskOutput, $captured);
        // Exactly once: the realtime stream replaces the end-of-job console
        // emission, so display() must not also write the same output.
        self::assertSame(
            1,
            \substr_count($captured, 'RUNNER_REALTIME_MARKER'),
            'Realtime output must not be duplicated by the end-of-job emission.'
        );
    }

    public function test_output_is_not_streamed_raw_when_realtime_disabled(): void
    {
        $taskOutput = '<info>RUNNER_BUFFERED_MARKER</info>';
        $command = "php -r \"echo '{$taskOutput}';\"";

        $schedule = new Schedule();
        $schedule->run($command)
            ->everyMinute()
        ;

        $output = new BufferedOutput();
        $eventRunner = $this->createEventRunner(
            realInvoker: true,
            configuration: new FakeConfiguration(['output_realtime' => false]),
        );

        $eventRunner->handle($output, [$schedule]);

        $captured = $output->fetch();

        // With realtime off, output still reaches the console once, via the
        // existing end-of-job display() path (which strips the style tag).
        self::assertStringContainsString('RUNNER_BUFFERED_MARKER', $captured);
        self::assertStringNotContainsString($taskOutput, $captured);
        self::assertSame(1, \substr_count($captured, 'RUNNER_BUFFERED_MARKER'));
    }

    public function test_realtime_output_still_writes_global_log_output_record(): void
    {
        // Logger-based sinks write to their own configured destination, so they
        // do NOT duplicate the live console stream and must keep working in
        // realtime mode. Only the console display() fallback is suppressed.
        $command = "php -r \"echo 'GLOBAL_LOG';\"";

        $schedule = new Schedule();
        $schedule->run($command)
            ->everyMinute()
        ;

        $spyLogger = new SpyPsrLogger();
        $loggerFactory = $this->createMock(LoggerFactory::class);
        $loggerFactory->method('create')
            ->willReturn(new Logger($spyLogger))
        ;

        $eventRunner = new EventRunner(
            new Invoker(),
            new FakeConfiguration(
                [
                    'output_realtime' => true,
                    'log_output' => true,
                    'output_log_file' => 'main.log',
                ]
            ),
            $this->createMock(Mailer::class),
            $loggerFactory,
            $this->createMock(HttpClientInterface::class),
            $this->createMock(ConsoleLoggerInterface::class)
        );

        $eventRunner->handle(new BufferedOutput(), [$schedule]);

        $infoLogs = \array_filter(
            $spyLogger->getLogs(),
            static fn (array $log): bool => 'info' === $log['level']
        );
        self::assertCount(
            1,
            $infoLogs,
            'Global log_output record must still be written in realtime mode.'
        );
    }

    public function test_realtime_output_still_writes_per_event_log_file(): void
    {
        // A task with appendOutputTo()/sendOutputTo() writes to a dedicated file
        // via its own logger; that file is a separate sink from the console, so
        // realtime mode must not drop it.
        $command = "php -r \"echo 'PER_EVENT';\"";

        $schedule = new Schedule();
        $schedule->run($command)
            ->everyMinute()
            ->appendOutputTo('event.log')
        ;

        $eventSpyLogger = new SpyPsrLogger();
        $loggerFactory = $this->createMock(LoggerFactory::class);
        $loggerFactory->method('create')
            ->willReturn(new Logger(new SpyPsrLogger()))
        ;
        $loggerFactory->method('createEvent')
            ->willReturn(new Logger($eventSpyLogger))
        ;

        $eventRunner = new EventRunner(
            new Invoker(),
            new FakeConfiguration(['output_realtime' => true]),
            $this->createMock(Mailer::class),
            $loggerFactory,
            $this->createMock(HttpClientInterface::class),
            $this->createMock(ConsoleLoggerInterface::class)
        );

        $eventRunner->handle(new BufferedOutput(), [$schedule]);

        $infoLogs = \array_filter(
            $eventSpyLogger->getLogs(),
            static fn (array $log): bool => 'info' === $log['level']
        );
        self::assertCount(
            1,
            $infoLogs,
            'Per-event log file must still be written in realtime mode.'
        );
        self::assertStringContainsString('PER_EVENT', (string) \reset($infoLogs)['message']);
    }

    public function test_realtime_output_still_sends_email(): void
    {
        $command = "php -r \"echo 'MAIL_BODY';\"";

        $schedule = new Schedule();
        $schedule->run($command)
            ->everyMinute()
        ;

        $mailer = $this->createMock(Mailer::class);
        $mailer->expects(self::once())
            ->method('send')
        ;

        $eventRunner = new EventRunner(
            new Invoker(),
            new FakeConfiguration(
                [
                    'output_realtime' => true,
                    'email_output' => true,
                ]
            ),
            $mailer,
            $this->createMock(LoggerFactory::class),
            $this->createMock(HttpClientInterface::class),
            $this->createMock(ConsoleLoggerInterface::class)
        );

        $eventRunner->handle(new BufferedOutput(), [$schedule]);
    }

    public function test_realtime_routes_stderr_to_error_stream(): void
    {
        // With a real console output, stdout chunks go to the main stream and
        // stderr chunks to the error stream.
        $command = "php -r \"echo 'OUTCHUNK'; fwrite(STDERR, 'ERRCHUNK');\"";

        $schedule = new Schedule();
        $schedule->run($command)
            ->everyMinute()
        ;

        $output = new SpyConsoleOutput();
        $eventRunner = $this->createEventRunner(
            realInvoker: true,
            configuration: new FakeConfiguration(['output_realtime' => true]),
        );

        $eventRunner->handle($output, [$schedule]);

        $stdout = $output->fetch();
        $stderr = $output->fetchErrorOutput();

        self::assertStringContainsString('OUTCHUNK', $stdout);
        self::assertStringNotContainsString('ERRCHUNK', $stdout);
        self::assertStringContainsString('ERRCHUNK', $stderr);
    }

    public function test_realtime_output_does_not_duplicate_failed_task_output(): void
    {
        // A failing task streams its output live; handleError must NOT also write
        // the <error> blob in realtime mode, otherwise the output appears twice.
        $command = "php -r \"fwrite(STDERR, 'BOOM'); exit(1);\"";

        $schedule = new Schedule();
        $schedule->run($command)
            ->everyMinute()
        ;

        $output = new BufferedOutput();
        $eventRunner = $this->createEventRunner(
            realInvoker: true,
            configuration: new FakeConfiguration(
                [
                    'output_realtime' => true,
                    'log_errors' => false,
                ]
            ),
        );

        $eventRunner->handle($output, [$schedule]);

        $captured = $output->fetch();

        self::assertStringContainsString('BOOM', $captured);
        self::assertSame(
            1,
            \substr_count($captured, 'BOOM'),
            'Failed task output must not be duplicated by handleError in realtime mode.'
        );
    }

    public function test_event_logging_configuration(): void
    {
        $logTarget = 'event.log';

        // create schedule with event that changes logging configuration
        $schedule = new Schedule();
        $schedule->run('php -v')
            ->appendOutputTo($logTarget)
        ;

        // mock the LoggerFactory
        $loggerFactory = $this->createMock(LoggerFactory::class);
        $loggerFactory->expects(self::once())
            ->method('createEvent')
            ->with($logTarget);

        // create an EventRunner to handle the Schedule
        $eventRunner = new EventRunner(
            $this->createMock(Invoker::class),
            new FakeConfiguration(),
            $this->createMock(Mailer::class),
            $loggerFactory,
            $this->createMock(HttpClientInterface::class),
            $this->createMock(ConsoleLoggerInterface::class)
        );

        $output = $this->createMock(OutputInterface::class);
        $eventRunner->handle($output, [$schedule]);
    }

    public function test_lock_is_released_on_error(): void
    {
        $output = $this->createMock(OutputInterface::class);

        if (\interface_exists(StoreInterface::class)) {
            $mockStore = $this->createMock(StoreInterface::class);
        } else {
            $mockStore = $this->createMock(BlockingStoreInterface::class);
        }

        $mockStore
            ->expects(self::once())
            ->method('delete')
        ;
        $schedule = new Schedule();
        $event = $schedule->run('wrong-command');
        $event->preventOverlapping($mockStore);

        $eventRunner = $this->createEventRunner(true);
        $eventRunner->handle($output, [$schedule]);
    }

    /**
     * @param string $url
     *
     * @return EventRunner
     */
    private function createEventRunnerForPing($url)
    {
        $invoker = $this->createMock(Invoker::class);
        $mailer = $this->createMock(Mailer::class);
        $loggerFactory = $this->createMock(LoggerFactory::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $consoleLogger = $this->createMock(ConsoleLoggerInterface::class);

        $httpClient
            ->expects(self::once())
            ->method('ping')
            ->with($url)
        ;

        return new EventRunner(
            $invoker,
            new FakeConfiguration(),
            $mailer,
            $loggerFactory,
            $httpClient,
            $consoleLogger
        );
    }

    private function createEventRunner(
        bool $realInvoker = false,
        ?FakeConfiguration $configuration = null,
    ): EventRunner {
        $invoker = true === $realInvoker
            ? new Invoker()
            : $this->createMock(Invoker::class)
        ;
        $mailer = $this->createMock(Mailer::class);
        $loggerFactory = $this->createMock(LoggerFactory::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $consoleLogger = $this->createMock(ConsoleLoggerInterface::class);

        return new EventRunner(
            $invoker,
            $configuration ?? new FakeConfiguration(),
            $mailer,
            $loggerFactory,
            $httpClient,
            $consoleLogger
        );
    }
}
