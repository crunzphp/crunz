<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase;

use Crunz\Clock\ClockInterface;

final class TestClock implements ClockInterface
{
    public function __construct(private readonly \DateTimeImmutable $now)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
