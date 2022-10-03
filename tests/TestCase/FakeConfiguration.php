<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase;

use Crunz\Application\Service\ConfigurationInterface;
use Crunz\Infrastructure\Psr\Logger\PsrStreamLoggerFactory;

final class FakeConfiguration implements ConfigurationInterface
{
    private const DEFAULT_CONFIG = [
        'source' => 'tasks',
        'suffix' => 'Tasks.php',
        'timezone' => 'UTC',
        'timezone_log' => false,
        'log_errors' => false,
        'errors_log_file' => null,
        'logger_factory' => PsrStreamLoggerFactory::class,
        'log_output' => false,
        'output_log_file' => null,
        'log_allow_line_breaks' => false,
        'log_ignore_empty_context' => false,
        'email_output' => false,
        'email_errors' => false,
    ];

    /** @var array<string|int,string|array|bool|null> */
    private array $config;

    /** @param array<string|int,string|array|bool|null> $config */
    public function __construct(array $config = [])
    {
        $this->config = \array_merge(self::DEFAULT_CONFIG, $config);
    }

    /** {@inheritdoc} */
    public function get(string $key, $default = null)
    {
        if (\array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        $parts = \explode('.', $key);
        $value = $this->config;
        foreach ($parts as $part) {
            if (!\is_array($value) || !\array_key_exists($part, $value)) {
                return $default;
            }

            $value = $value[$part];
        }

        return $value;
    }

    /** {@inheritdoc} */
    public function withNewEntry(string $key, $value): ConfigurationInterface
    {
        $newConfiguration = clone $this;

        $parts = \explode('.', $key);

        if (\count($parts) > 1) {
            if (\array_key_exists($parts[0], $newConfiguration->config) && \is_array($newConfiguration->config[$parts[0]])) {
                $newConfiguration->config[$parts[0]][$parts[1]] = $value;
            } else {
                $newConfiguration->config[$parts[0]] = [$parts[1] => $value];
            }
        } else {
            $newConfiguration->config[$key] = $value;
        }

        return $newConfiguration;
    }

    public function getSourcePath(): string
    {
        return (string) $this->get('source', 'tasks');
    }
}
