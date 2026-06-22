<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A {@see ConsoleOutputInterface} test double that captures the main output and
 * the error output into separate buffers, so tests can assert how output is
 * routed between stdout and stderr (the real {@see BufferedOutput} used
 * elsewhere is not a console output and collapses both streams into one).
 */
final class SpyConsoleOutput extends BufferedOutput implements ConsoleOutputInterface
{
    private BufferedOutput $stderr;

    public function __construct()
    {
        parent::__construct();
        $this->stderr = new BufferedOutput();
    }

    public function getErrorOutput(): OutputInterface
    {
        return $this->stderr;
    }

    public function setErrorOutput(OutputInterface $error): void
    {
        // Not needed for tests.
    }

    public function section(): ConsoleSectionOutput
    {
        throw new \BadMethodCallException('section() is not supported by the test double.');
    }

    /** Return everything written to the error (stderr) stream. */
    public function fetchErrorOutput(): string
    {
        return $this->stderr
            ->fetch();
    }
}
