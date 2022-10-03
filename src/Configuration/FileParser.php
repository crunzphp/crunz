<?php

declare(strict_types=1);

namespace Crunz\Configuration;

use Symfony\Component\Yaml\Yaml;

class FileParser
{
    public function __construct(private Yaml $yamlParser)
    {
    }

    /**
     * @return array<array>
     *
     * @throws ConfigFileNotExistsException
     * @throws ConfigFileNotReadableException
     */
    public function parse(string $configPath): array
    {
        if (!\file_exists($configPath)) {
            throw ConfigFileNotExistsException::fromFilePath($configPath);
        }

        if (!\is_readable($configPath)) {
            throw ConfigFileNotReadableException::fromFilePath($configPath);
        }

        $yamlParser = $this->yamlParser;
        $configContent = \file_get_contents($configPath);

        if (false === $configContent) {
            throw ConfigFileNotReadableException::fromFilePath($configPath);
        }

        return [$yamlParser::parse($configContent)];
    }
}
