<?php

declare(strict_types=1);

namespace Crunz\Task;

interface CollectionInterface
{
    /** @return \SplFileInfo[] */
    public function all(string $source): iterable;
}
