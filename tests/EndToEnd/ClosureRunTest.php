<?php

declare(strict_types=1);

namespace Crunz\Tests\EndToEnd;

use Crunz\Tests\TestCase\EndToEndTestCase;

final class ClosureRunTest extends EndToEndTestCase
{
    /** @test */
    public function closure_tasks(): void
    {
        $envBuilder = $this->createEnvironmentBuilder();
        $envBuilder
            ->addTask('ClosureTasks')
            ->withConfig(['timezone' => 'UTC'])
        ;

        $environment = $envBuilder->createEnvironment();

        $process = $environment->runCrunzCommand('schedule:run');

        self::assertStringContainsString(
            'Closure output Var: 153',
            \str_replace(
                PHP_EOL,
                ' ',
                $process->getOutput()
            )
        );
    }

    public function test_prevent_overlapping_works_on_closures(): void
    {
        $envBuilder = $this->createEnvironmentBuilder();
        $envBuilder
            ->addTask('NoOverlappingClosureTasks')
            ->withConfig(['timezone' => 'UTC'])
        ;

        $environment = $envBuilder->createEnvironment();

        // Warmup Crunz to avoid container's cache race condition
        $environment->runCrunzCommand('schedule:list');

        $firstCall = $environment->runCrunzCommand(
            'schedule:run',
            null,
            false
        );
        \usleep(50 * 1000); // wait 50ms
        $secondCall = $environment->runCrunzCommand('schedule:run');
        $firstCall->wait();

        // preventOverlapping() guarantees that of two overlapping runs exactly
        // one executes the task and the other is skipped as "not due". Which of
        // the two processes wins the lock is NOT deterministic: PHP process
        // startup latency varies (notably on Windows CI), so the second-spawned
        // run can acquire the lock before the first. Assert the invariant over
        // the combined output rather than assuming the first call always wins.
        $combinedOutput = $firstCall->getOutput() . $secondCall->getOutput();

        self::assertStringContainsString(
            'Done',
            $combinedOutput,
            'Exactly one of the overlapping runs should execute the task.'
        );
        self::assertStringContainsString(
            'No event is due!',
            $combinedOutput,
            'The overlapping run should be skipped because the lock is held.'
        );
    }
}
