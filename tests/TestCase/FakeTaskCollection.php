<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase;

use Crunz\Task\CollectionInterface;

final class FakeTaskCollection implements CollectionInterface
{
    /** @var \SplFileInfo[] */
    private iterable $tasks;

    /** @param \SplFileInfo[] $tasks */
    public function __construct(iterable $tasks = [])
    {
        $this->tasks = $tasks;
    }

    public function all(string $source): iterable
    {
        return $this->tasks;
    }
}
