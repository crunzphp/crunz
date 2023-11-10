<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase;

use Crunz\Task\CollectionInterface;

final class FakeTaskCollection implements CollectionInterface
{
    /** @param \SplFileInfo[] $tasks */
    public function __construct(private readonly iterable $tasks = [])
    {
    }

    public function all(string $source): iterable
    {
        return $this->tasks;
    }
}
