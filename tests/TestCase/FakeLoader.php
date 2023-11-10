<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase;

use Crunz\Schedule;
use Crunz\Task\LoaderInterface;

final class FakeLoader implements LoaderInterface
{
    /** @param Schedule[] $schedules */
    public function __construct(private readonly array $schedules = [])
    {
    }

    public function load(\SplFileInfo ...$files): array
    {
        return $this->schedules;
    }
}
