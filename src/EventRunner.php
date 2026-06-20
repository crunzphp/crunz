<?php

declare(strict_types=1);

namespace Crunz;

use Crunz\Application\Service\ConfigurationInterface;
use Crunz\HttpClient\HttpClientInterface;
use Crunz\Logger\ConsoleLoggerInterface;
use Crunz\Logger\Logger;
use Crunz\Logger\LoggerFactory;
use Crunz\Pinger\PingableInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

class EventRunner
{
    /** @var Schedule[] */
    protected array $schedules = [];
    /** @var Logger|null */
    protected $logger;
    private ?OutputInterface $output = null;

    public function __construct(
        protected Invoker $invoker,
        private readonly ConfigurationInterface $configuration,
        protected Mailer $mailer,
        private readonly LoggerFactory $loggerFactory,
        private readonly HttpClientInterface $httpClient,
        private readonly ConsoleLoggerInterface $consoleLogger,
    ) {
    }

    /** @param Schedule[] $schedules */
    public function handle(OutputInterface $output, array $schedules = []): void
    {
        $this->schedules = $schedules;
        $this->output = $output;

        foreach ($this->schedules as $schedule) {
            $this->consoleLogger
                ->debug("Invoke Schedule's ping before");

            $this->pingBefore($schedule);

            // Running the before-callbacks of the current schedule
            $this->invoke($schedule->beforeCallbacks());

            $events = $schedule->events();
            foreach ($events as $event) {
                $this->start($event);
            }
        }

        // Watch events until they are finished
        $this->manageStartedEvents();
    }

    protected function start(Event $event): void
    {
        $this->logger = $this->loggerFactory
            ->create()
        ;

        // if sendOutputTo or appendOutputTo have been specified
        if (!$event->nullOutput()) {
            // if sendOutputTo then truncate the log file if it exists
            if (!$event->shouldAppendOutput) {
                $f = @\fopen($event->output, 'r+');
                if (false !== $f) {
                    \ftruncate($f, 0);
                    \fclose($f);
                }
            }
            // Create an instance of the Logger specific to the event
            $event->logger = $this->loggerFactory->createEvent($event->output);
        }

        // When realtime output is enabled, stream the task's output to the
        // console as it is produced instead of only after the process exits.
        if ($this->realtimeOutputEnabled()) {
            $event->setRealtimeCallback($this->createRealtimeCallback());
        }

        $this->consoleLogger
            ->debug("Invoke Event's ping before.");

        $this->pingBefore($event);

        // Running the before-callbacks
        $event->outputStream = $this->invoke($event->beforeCallbacks());
        $event->start();
    }

    protected function manageStartedEvents(): void
    {
        while ($this->schedules) {
            foreach ($this->schedules as $scheduleKey => $schedule) {
                $events = $schedule->events();
                // 10% chance that refresh will be called
                $refreshLocks = (\random_int(1, 100) <= 10);

                /** @var Event $event */
                foreach ($events as $eventKey => $event) {
                    if ($refreshLocks) {
                        $event->refreshLock();
                    }

                    $proc = $event->getProcess();
                    if ($proc->isRunning()) {
                        continue;
                    }

                    $runStatus = '';

                    if ($proc->isSuccessful()) {
                        $this->consoleLogger
                            ->debug("Invoke Event's ping after.");
                        $this->pingAfter($event);

                        $runStatus = '<info>success</info>';

                        $event->outputStream .= $event->wholeOutput();
                        $event->outputStream .= $this->invoke($event->afterCallbacks());

                        $this->handleOutput($event);
                    } else {
                        $runStatus = '<error>fail</error>';

                        // Invoke error callbacks
                        $this->invoke($event->errorCallbacks());
                        // Calling registered error callbacks with an instance of $event as argument
                        $this->invoke($schedule->errorCallbacks(), [$event]);
                        $this->handleError($event);
                    }

                    $id = $event->description ?: $event->getId();

                    $this->consoleLogger
                        ->debug("Task <info>{$id}</info> status: {$runStatus}.");

                    // Dismiss the event if it's finished
                    $schedule->dismissEvent($eventKey);
                }

                // If there's no event left for the Schedule instance,
                // run the schedule's after-callbacks and remove
                // the Schedule from list of active schedules.                                                                                                                           zzzwwscxqqqAAAQ11
                if (!\count($schedule->events())) {
                    $this->consoleLogger
                        ->debug("Invoke Schedule's ping after.");

                    $this->pingAfter($schedule);
                    $this->invoke($schedule->afterCallbacks());
                    unset($this->schedules[$scheduleKey]);
                }
            }

            \usleep(250000);
        }
    }

    /**
     * @param \Closure[]         $callbacks
     * @param array<mixed,mixed> $parameters
     *
     * @return string
     */
    protected function invoke(array $callbacks = [], array $parameters = [])
    {
        $output = '';
        foreach ($callbacks as $callback) {
            /** @var string $callResult */
            $callResult = $this->invoker->call($callback, $parameters, true);
            // Invoke the callback with buffering enabled
            $output .= $callResult;
        }

        return $output;
    }

    protected function handleOutput(Event $event): void
    {
        // In realtime mode the task's successful output has already been
        // streamed live to the console, so the end-of-job emission (per-event
        // log, global log_output, and the console display fallback) is
        // suppressed to avoid writing the same output twice. The buffer is
        // still retained for email_output, and the error path is untouched.
        if ($this->realtimeOutputEnabled()) {
            $this->emailOutput($event);

            return;
        }

        $logged = false;
        $logOutput = $this->configuration
            ->get('log_output')
        ;

        if (!$event->nullOutput()) {
            $event->logger->info($this->formatEventOutput($event));
            $logged = true;
        }

        if ($logOutput && !$logged) {
            $this->logger()
                ->info($this->formatEventOutput($event))
            ;
            $logged = true;
        }

        if (!$logged) {
            $this->display($event->getOutputStream());
        }

        $this->emailOutput($event);
    }

    protected function handleError(Event $event): void
    {
        $logErrors = $this->configuration
            ->get('log_errors')
        ;
        $emailErrors = $this->configuration
            ->get('email_errors')
        ;

        if ($logErrors) {
            $this->logger()
                ->error($this->formatEventError($event))
            ;
        } else {
            $output = $event->wholeOutput();

            $this->output
                ?->write("<error>{$output}</error>")
            ;
        }

        // Send error as email as configured
        if ($emailErrors) {
            $this->mailer->send(
                'Crunz: reporting error for event:' . ($event->description ?? $event->getId()),
                $this->formatEventError($event)
            );
        }
    }

    /** @return string */
    protected function formatEventOutput(Event $event)
    {
        return $event->description
            . '('
            . $event->getCommandForDisplay()
            . ') '
            . PHP_EOL
            . PHP_EOL
            . $event->outputStream
            . PHP_EOL;
    }

    /** @return string */
    protected function formatEventError(Event $event)
    {
        return $event->description
            . '('
            . $event->getCommandForDisplay()
            . ') '
            . PHP_EOL
            . $event->wholeOutput()
            . PHP_EOL;
    }

    /** @param string|null $output */
    protected function display($output): void
    {
        $this->output
            ?->write(\is_string($output) ? $output : '')
        ;
    }

    /**
     * Email the event's output when email_output is enabled.
     *
     * Kept separate from the logging/display logic so it can run unchanged in
     * realtime mode, where the live stream replaces the end-of-job logging but
     * the retained buffer is still emailed.
     */
    private function emailOutput(Event $event): void
    {
        $emailOutput = $this->configuration
            ->get('email_output')
        ;
        if ($emailOutput && !empty($event->getOutputStream())) {
            $this->mailer->send(
                'Crunz: output for event: ' . ($event->description ?? $event->getId()),
                $this->formatEventOutput($event)
            );
        }
    }

    private function pingBefore(PingableInterface $schedule): void
    {
        if (!$schedule->hasPingBefore()) {
            $this->consoleLogger
                ->debug('There is no ping before url.');

            return;
        }

        /** @var non-empty-string $pingBeforeUrl */
        $pingBeforeUrl = $schedule->getPingBeforeUrl();
        $this->httpClient
            ->ping($pingBeforeUrl)
        ;
    }

    private function pingAfter(PingableInterface $schedule): void
    {
        if (!$schedule->hasPingAfter()) {
            $this->consoleLogger
                ->debug('There is no ping after url.');

            return;
        }

        /** @var non-empty-string $pingAfterUrl */
        $pingAfterUrl = $schedule->getPingAfterUrl();
        $this->httpClient
            ->ping($pingAfterUrl)
        ;
    }

    private function logger(): Logger
    {
        if (null === $this->logger) {
            $this->logger = $this->loggerFactory
                ->create()
            ;
        }

        return $this->logger;
    }

    /** Whether realtime output streaming is enabled via configuration. */
    private function realtimeOutputEnabled(): bool
    {
        return (bool) $this->configuration
            ->get('output_realtime')
        ;
    }

    /**
     * Build a sink that writes each output chunk straight to the console as it
     * is produced. Standard output goes to the main stream and error output to
     * the console's error stream (when available). Chunks are written raw and
     * without an added newline so task output is forwarded verbatim.
     *
     * @return \Closure(string, string):void
     */
    private function createRealtimeCallback(): \Closure
    {
        $output = $this->output;
        if (null === $output) {
            return static function (): void {};
        }

        $errorOutput = $output instanceof ConsoleOutputInterface
            ? $output->getErrorOutput()
            : $output
        ;

        return static function (string $type, string $content) use ($output, $errorOutput): void {
            $stream = SymfonyProcess::ERR === $type
                ? $errorOutput
                : $output
            ;
            $stream->write($content, false, OutputInterface::OUTPUT_RAW);
        };
    }
}
