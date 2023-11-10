<?php

declare(strict_types=1);

namespace Crunz\Task;

use Crunz\Application\Service\ConfigurationInterface;
use Crunz\Finder\FinderInterface;
use Crunz\Logger\ConsoleLoggerInterface;
use Crunz\Path\Path;

class Collection implements CollectionInterface
{
    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly FinderInterface $finder,
        private readonly ConsoleLoggerInterface $consoleLogger,
    ) {
    }

    public function all(string $source): iterable
    {
        $this->consoleLogger
            ->debug("Task source path '<info>{$source}</info>'");

        if (!\file_exists($source)) {
            return [];
        }

        $suffix = $this->configuration
            ->get('suffix')
        ;

        $this->consoleLogger
            ->debug("Task finder suffix: '<info>{$suffix}</info>'");

        $realPath = \realpath($source);
        if (false !== $realPath) {
            $this->consoleLogger
                ->verbose("Realpath for '<info>{$source}</info>' is '<info>{$realPath}</info>'");
        } else {
            $this->consoleLogger
                ->verbose("Realpath resolve for '<info>{$source}</info>' failed.");
        }

        $tasks = $this->finder
            ->find(Path::fromStrings($source), $suffix)
        ;
        $tasksCount = \count($tasks);

        $this->consoleLogger
            ->debug("Found <info>{$tasksCount}</info> task(s) at path '<info>{$source}</info>'");

        return $tasks;
    }
}
