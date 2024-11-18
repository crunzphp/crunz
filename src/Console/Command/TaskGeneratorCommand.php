<?php

declare(strict_types=1);

namespace Crunz\Console\Command;

use Crunz\Application\Service\ConfigurationInterface;
use Crunz\Filesystem\FilesystemInterface;
use Crunz\Path\Path;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class TaskGeneratorCommand extends Command
{
    /**
     * Default option values.
     *
     * @var array<string,string>
     */
    final public const DEFAULTS = [
        'frequency' => 'everyThirtyMinutes',
        'constraint' => 'weekdays',
        'in' => 'path/to/your/command',
        'run' => 'command/to/execute',
        'description' => 'Task description',
        'type' => 'basic',
    ];
    /**
     * Stub content.
     *
     * @var string
     */
    protected $stub;

    public function __construct(
        private readonly ConfigurationInterface $config,
        private readonly FilesystemInterface $filesystem,
    ) {
        parent::__construct();
    }

    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this
            ->setName('make:task')
            ->setDescription('Generates a task file with one task.')
            ->setDefinition(
                [
                    new InputArgument(
                        'taskfile',
                        InputArgument::REQUIRED,
                        'The task file name'
                    ),
                    new InputOption(
                        'frequency',
                        'f',
                        InputOption::VALUE_OPTIONAL,
                        "The task's frequency",
                        self::DEFAULTS['frequency']
                    ),
                    new InputOption(
                        'constraint',
                        'c',
                        InputOption::VALUE_OPTIONAL,
                        "The task's constraint",
                        self::DEFAULTS['constraint']
                    ),
                    new InputOption(
                        'in',
                        'i',
                        InputOption::VALUE_OPTIONAL,
                        "The command's path",
                        self::DEFAULTS['in']
                    ),
                    new InputOption(
                        'run',
                        'r',
                        InputOption::VALUE_OPTIONAL,
                        "The task's command",
                        self::DEFAULTS['run']
                    ),
                    new InputOption(
                        'description',
                        'd',
                        InputOption::VALUE_OPTIONAL,
                        "The task's description",
                        self::DEFAULTS['description']
                    ),
                    new InputOption(
                        'type',
                        't',
                        InputOption::VALUE_OPTIONAL,
                        'The task type',
                        self::DEFAULTS['type']
                    ),
                ]
            )
            ->setHelp('This command makes a task file skeleton.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        $this->arguments = $input->getArguments();
        $this->options = $input->getOptions();
        $this->stub = $this->getStub();

        if ($this->stub) {
            $this
                ->replaceFrequency()
                ->replaceConstraint()
                ->replaceCommand()
                ->replacePath()
                ->replaceDescription()
            ;
        }

        if ($this->save()) {
            $output->writeln('<info>The task file generated successfully</info>');
        } else {
            $output->writeln('<comment>There was a problem when generating the file. Please check your command.</comment>');
        }

        return 0;
    }

    /**
     * Save the generate task skeleton into a file.
     *
     * @return bool
     */
    protected function save()
    {
        $filename = Path::create([$this->outputPath(), $this->outputFile()]);

        return (bool) \file_put_contents($filename->toString(), $this->stub);
    }

    /**
     * Ask a question.
     *
     * @param string $question
     *
     * @return ?string
     */
    protected function ask($question)
    {
        $helper = $this->getHelper('question');
        $question = new Question("<question>{$question}</question>");

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Return the output path.
     *
     * @return string
     */
    protected function outputPath()
    {
        $source = $this->config
            ->getSourcePath()
        ;
        $destination = $this->ask('Where do you want to save the file? (Press enter for the current directory)');
        $outputPath = $destination ?? $source;

        if (!\file_exists($outputPath)) {
            \mkdir($outputPath, 0744, true);
        }

        return $outputPath;
    }

    /**
     * Populate the output filename.
     *
     * @return string
     */
    protected function outputFile()
    {
        /** @var string $suffix */
        $suffix = $this->config
            ->get('suffix')
        ;
        /** @var string $taskFile */
        $taskFile = $this->arguments['taskfile'];

        return \preg_replace('/Tasks|\.php$/', '', $taskFile) . $suffix;
    }

    /**
     * Get the task stub.
     *
     * @return string
     */
    protected function getStub()
    {
        $projectRootDirectory = $this->filesystem
            ->projectRootDirectory();
        $path = Path::fromStrings(
            $projectRootDirectory,
            'src',
            'Stubs',
            \ucfirst($this->type() . 'Task.php')
        );

        return $this->filesystem
            ->readContent($path->toString());
    }

    /**
     * Get the task type.
     *
     * @return string
     */
    protected function type()
    {
        return $this->options['type'];
    }

    /**
     * Replace frequency.
     */
    protected function replaceFrequency(): self
    {
        $this->stub = \str_replace('DummyFrequency', \rtrim($this->options['frequency'], '()'), $this->stub);

        return $this;
    }

    /**
     * Replace constraint.
     */
    protected function replaceConstraint(): self
    {
        $this->stub = \str_replace('DummyConstraint', \rtrim($this->options['constraint'], '()'), $this->stub);

        return $this;
    }

    protected function replaceCommand(): self
    {
        $run = $this->optionString('run');
        $this->stub = \str_replace('DummyCommand', $run, $this->stub);

        return $this;
    }

    protected function replacePath(): self
    {
        $in = $this->optionString('in');
        $this->stub = \str_replace('DummyPath', $in, $this->stub);

        return $this;
    }

    protected function replaceDescription(): self
    {
        $description = $this->optionString('description');
        $this->stub = \str_replace('DummyDescription', $description, $this->stub);

        return $this;
    }

    private function optionString(string $name): string
    {
        $option = $this->options[$name] ?? throw new \RuntimeException("Missing option '{$name}'.");
        if (false === \is_string($option)) {
            throw new \RuntimeException("Option must be of type 'string'.");
        }

        return $option;
    }
}
