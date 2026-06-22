<?php

declare(strict_types=1);

namespace Crunz\Tests\EndToEnd;

use Crunz\Process\Process;
use Crunz\Tests\TestCase\EndToEndTestCase;

final class RealtimeOutputTest extends EndToEndTestCase
{
    /** Seconds to wait for the first marker before giving up. */
    private const POLL_TIMEOUT_SECONDS = 15.0;

    public function test_output_is_streamed_before_task_finishes(): void
    {
        $envBuilder = $this->createEnvironmentBuilder()
            ->addTask('RealtimeOutputTasks')
            ->withConfig(['output_realtime' => true])
        ;
        $environment = $envBuilder->createEnvironment();

        $process = $environment->runCrunzCommand('schedule:run --force', wait: false);

        // The task emits the first marker, sleeps 3s, then emits the second.
        // In realtime mode each chunk is streamed as produced, so there is a
        // window where the first marker is visible but the second is not yet.
        $streamedIncrementally = $this->sawFirstMarkerBeforeSecond($process);

        $process->wait();

        self::assertTrue(
            $streamedIncrementally,
            'The first marker should be streamed before the second is produced (realtime streaming).'
        );
        // Sanity check: both markers are present once the task completes.
        self::assertStringContainsString('REALTIME_FIRST', $process->getOutput());
        self::assertStringContainsString('REALTIME_SECOND', $process->getOutput());
    }

    public function test_output_is_not_streamed_when_realtime_disabled(): void
    {
        $envBuilder = $this->createEnvironmentBuilder()
            ->addTask('RealtimeOutputTasks')
            ->withConfig(
                [
                    'output_realtime' => false,
                    // Route the end-of-job output to stdout so we can confirm it
                    // arrives all at once at the end, not streamed during the run.
                    'log_output' => true,
                    'output_log_file' => 'php://stdout',
                ]
            )
        ;
        $environment = $envBuilder->createEnvironment();

        $process = $environment->runCrunzCommand('schedule:run --force', wait: false);

        // With realtime off, both markers are buffered and written together in a
        // single end-of-job record, so the "first without second" window never
        // exists.
        $streamedIncrementally = $this->sawFirstMarkerBeforeSecond($process);

        $process->wait();

        self::assertFalse(
            $streamedIncrementally,
            'With realtime disabled the markers must be emitted together at the end, not streamed.'
        );
        // The output is still emitted at the end of the job.
        self::assertStringContainsString('REALTIME_FIRST', $process->getOutput());
        self::assertStringContainsString('REALTIME_SECOND', $process->getOutput());
    }

    public function test_output_is_streamed_to_file_before_task_finishes(): void
    {
        $envBuilder = $this->createEnvironmentBuilder()
            ->addTask('RealtimeFileOutputTasks')
            ->withConfig(['output_realtime' => true])
        ;
        $environment = $envBuilder->createEnvironment();
        $logPath = $environment->rootDirectory() . DIRECTORY_SEPARATOR . 'realtime.log';

        $process = $environment->runCrunzCommand('schedule:run --force', wait: false);

        // The task (configured with appendOutputTo('realtime.log')) emits the
        // first marker, sleeps 3s, then emits the second. With realtime output
        // the file receives the first marker before the second is produced.
        $streamedIncrementally = $this->fileSawFirstMarkerBeforeSecond($logPath, $process);

        $process->wait();

        self::assertTrue(
            $streamedIncrementally,
            'The log file should receive the first marker before the second is produced (realtime file streaming).'
        );
        // Both markers are present in the file once the task completes, and the
        // raw output is streamed (not wrapped in the framed log record).
        $contents = (string) \file_get_contents($logPath);
        self::assertStringContainsString('FILE_FIRST', $contents);
        self::assertStringContainsString('FILE_SECOND', $contents);
        self::assertStringNotContainsString('crunz.INFO', $contents);
    }

    /**
     * Poll the process output while it runs, returning true if we ever observe
     * the first marker present while the second is still absent. That transient
     * state only occurs when output is streamed incrementally; when output is
     * buffered, both markers appear together in one write and the state is never
     * observed.
     */
    private function sawFirstMarkerBeforeSecond(Process $process): bool
    {
        $deadline = \microtime(true) + self::POLL_TIMEOUT_SECONDS;

        while ($process->isRunning()) {
            $output = $process->getOutput();
            if (
                \str_contains($output, 'REALTIME_FIRST')
                && !\str_contains($output, 'REALTIME_SECOND')
            ) {
                return true;
            }

            if (\microtime(true) > $deadline) {
                break;
            }

            \usleep(50000); // 50 ms
        }

        return false;
    }

    /**
     * Poll a log file while the process runs, returning true if we ever observe
     * the first marker written to the file while the second is still absent —
     * proving the file is written incrementally rather than only at the end.
     */
    private function fileSawFirstMarkerBeforeSecond(string $logPath, Process $process): bool
    {
        $deadline = \microtime(true) + self::POLL_TIMEOUT_SECONDS;

        while ($process->isRunning()) {
            $contents = \is_file($logPath)
                ? (string) \file_get_contents($logPath)
                : ''
            ;
            if (
                \str_contains($contents, 'FILE_FIRST')
                && !\str_contains($contents, 'FILE_SECOND')
            ) {
                return true;
            }

            if (\microtime(true) > $deadline) {
                break;
            }

            \usleep(50000); // 50 ms
        }

        return false;
    }
}
