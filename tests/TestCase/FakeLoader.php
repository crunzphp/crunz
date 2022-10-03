<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase;

use Crunz\Schedule;
use Crunz\Task\LoaderInterface;

final class FakeLoader implements LoaderInterface
{
    /** @var Schedule[] */
    private array $schedules;

    /** @param Schedule[] $schedules */
    public function __construct(array $schedules = [])
    {
        $this->schedules = $schedules;
    }

    public function load(\SplFileInfo ...$files): array
    {
        return $this->schedules;
    }
}
