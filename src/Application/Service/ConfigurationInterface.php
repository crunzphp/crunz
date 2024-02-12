<?php

declare(strict_types=1);

namespace Crunz\Application\Service;

interface ConfigurationInterface
{
    /**
     * Return a parameter based on a key.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a parameter based on a key.
     */
    public function withNewEntry(string $key, mixed $value): ConfigurationInterface;

    public function getSourcePath(): string;
}
