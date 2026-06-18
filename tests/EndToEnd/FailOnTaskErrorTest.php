<?php

declare(strict_types=1);

namespace Crunz\Tests\EndToEnd;

use Crunz\Tests\TestCase\EndToEndTestCase;

final class FailOnTaskErrorTest extends EndToEndTestCase
{
    /** @test */
    public function test_success_return_code_when_task_fails_and_flag_is_not_present(): void
    {
        $envBuilder = $this->createEnvironmentBuilder();
        $envBuilder
            ->addTask('FailTasks')
            ->withConfig(['timezone' => 'UTC'])
        ;

        $environment = $envBuilder->createEnvironment();

        $process = $environment->runCrunzCommand('schedule:run --force');

        self::assertTrue($process->isSuccessful(), 'Should be successful even if task fails when flag is not present.');
    }

    /** @test */
    public function test_not_successful_return_code_when_closure_task_fails_and_flag_is_present(): void
    {
        $envBuilder = $this->createEnvironmentBuilder();
        $envBuilder
            ->addTask('FailTasks')
            ->withConfig(['timezone' => 'UTC'])
        ;

        $environment = $envBuilder->createEnvironment();

        $process = $environment->runCrunzCommand('schedule:run --force --fail-on-task-error');

        self::assertFalse($process->isSuccessful(), 'Should not be successful when closure task fails and flag is present.');
    }

    /** @test */
    public function test_not_successful_return_code_when_command_task_fails_and_flag_is_present(): void
    {
        $envBuilder = $this->createEnvironmentBuilder();
        $envBuilder
            ->addTask('FailExitCodeTasks')
            ->withConfig(['timezone' => 'UTC'])
        ;

        $environment = $envBuilder->createEnvironment();

        $process = $environment->runCrunzCommand('schedule:run --force --fail-on-task-error');

        self::assertFalse($process->isSuccessful(), 'Should not be successful when command task fails and flag is present.');
    }
}
