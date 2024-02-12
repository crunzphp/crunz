<?php

declare(strict_types=1);

namespace Crunz\Configuration;

use Crunz\Console\Command\ConfigGeneratorCommand;
use Crunz\Filesystem\FilesystemInterface;
use Crunz\Logger\ConsoleLoggerInterface;
use Crunz\Path\Path;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationParser implements ConfigurationParserInterface
{
    public function __construct(
        private ConfigurationInterface $configurationDefinition,
        private Processor $definitionProcessor,
        private FileParser $fileParser,
        private ConsoleLoggerInterface $consoleLogger,
        private FilesystemInterface $filesystem
    ) {
    }

    public function parseConfig(): array
    {
        $configFile = null;
        $parsedConfig = [];
        $configFileParsed = false;

        try {
            $configFile = $this->configFilePath();
            $parsedConfig = $this->fileParser
                ->parse($configFile);

            $configFileParsed = true;
        } catch (ConfigFileNotExistsException $exception) {
            $this->consoleLogger
                ->debug("Config file not found, exception message: '<error>{$exception->getMessage()}</error>'.");
        } catch (ConfigFileNotReadableException $exception) {
            $this->consoleLogger
                ->debug("Config file is not readable, exception message: '<error>{$exception->getMessage()}</error>'.");
        }

        if (false === $configFileParsed) {
            $this->consoleLogger
                ->verbose('Unable to find/parse config file, fallback to default values.');
        } else {
            $this->consoleLogger
                ->verbose("Using config file <info>{$configFile}</info>.");
        }

        return $this->definitionProcessor
            ->processConfiguration(
                $this->configurationDefinition,
                $parsedConfig
            );
    }

    /** @throws ConfigFileNotExistsException */
    private function configFilePath(): string
    {
        $cwd = $this->filesystem
            ->getCwd();
        $configPath = Path::fromStrings($cwd ?? '', ConfigGeneratorCommand::CONFIG_FILE_NAME)->toString();
        $configExists = $this->filesystem
            ->fileExists($configPath);

        if ($configExists) {
            return $configPath;
        }

        throw new ConfigFileNotExistsException(
            \sprintf(
                'Unable to find config file "%s".',
                $configPath
            )
        );
    }
}
