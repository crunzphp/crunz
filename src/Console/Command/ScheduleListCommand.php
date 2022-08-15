<?php

declare(strict_types=1);

namespace Crunz\Console\Command;

use Crunz\Application\Service\ConfigurationInterface;
use Crunz\Exception\CrunzException;
use Crunz\Task\CollectionInterface;
use Crunz\Task\LoaderInterface;
use Crunz\Task\WrongTaskInstanceException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScheduleListCommand extends \Symfony\Component\Console\Command\Command
{
    private const FORMAT_TEXT = 'text';
    private const FORMAT_JSON = 'json';
    private const FORMATS = [
        self::FORMAT_TEXT,
        self::FORMAT_JSON,
    ];

    /** @var ConfigurationInterface */
    private $configuration;
    /** @var CollectionInterface */
    private $taskCollection;
    /** @var LoaderInterface */
    private $taskLoader;

    public function __construct(
        ConfigurationInterface $configuration,
        CollectionInterface $taskCollection,
        LoaderInterface $taskLoader
    ) {
        $this->configuration = $configuration;
        $this->taskCollection = $taskCollection;
        $this->taskLoader = $taskLoader;

        parent::__construct();
    }

    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $possibleFormats = \implode('", "', self::FORMATS);
        $this
            ->setName('schedule:list')
            ->setDescription('Displays the list of scheduled tasks.')
            ->setDefinition(
                [
                    new InputArgument(
                        'source',
                        InputArgument::OPTIONAL,
                        'The source directory for collecting the tasks.',
                        $this->configuration
                            ->getSourcePath()
                    ),
                ]
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                "Tasks list format, possible formats: \"{$possibleFormats}\".",
                self::FORMAT_TEXT,
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @throws WrongTaskInstanceException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $source */
        $source = $input->getArgument('source');
        $format = $this->resolveFormat($input);
        $tasks = $this->tasks($source);
        if (!\count($tasks)) {
            $output->writeln('<comment>No task found!</comment>');

            return 0;
        }

        $this->printList(
            $output,
            $tasks,
            $format,
        );

        return 0;
    }

    /**
     * @return array<
     *     int,
     *     array{
     *         number: int,
     *         task: string,
     *         expression: string,
     *         command: string,
     *     },
     * >
     */
    private function tasks(string $source): array
    {
        /** @var \SplFileInfo[] $tasks */
        $tasks = $this->taskCollection
            ->all($source)
        ;
        $schedules = $this->taskLoader
            ->load(...\array_values($tasks))
        ;

        $tasksList = [];
        $number = 0;

        foreach ($schedules as $schedule) {
            $events = $schedule->events();
            foreach ($events as $event) {
                $tasksList[] = [
                    'number' => ++$number,
                    'task' => $event->description ?? '',
                    'expression' => $event->getExpression(),
                    'command' => $event->getCommandForDisplay(),
                ];
            }
        }

        return $tasksList;
    }

    private function resolveFormat(InputInterface $input): string
    {
        /** @var string $format */
        $format = $input->getOption('format');
        $isValidFormat = \in_array(
            $format,
            self::FORMATS,
            true,
        );

        if (false === $isValidFormat) {
            throw new CrunzException("Format '{$format}' is not supported.");
        }

        return $format;
    }

    /**
     * @param array<
     *     int,
     *     array{
     *         number: int,
     *         task: string,
     *         expression: string,
     *         command: string,
     *     },
     * > $tasks
     */
    private function printList(
        OutputInterface $output,
        array $tasks,
        string $format
    ): void {
        switch ($format) {
            case self::FORMAT_TEXT:
                $table = new Table($output);
                $table->setHeaders(
                    [
                        '#',
                        'Task',
                        'Expression',
                        'Command to Run',
                    ]
                );

                foreach ($tasks as $task) {
                    $table->addRow($task);
                }

                $table->render();

                break;

            case self::FORMAT_JSON:
                $output->writeln(
                    \json_encode(
                        $tasks,
                        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
                    ),
                );

                break;

            default:
                throw new CrunzException("Unable to print list in format '{$format}'.");
        }
    }
}
