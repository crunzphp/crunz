<?php

declare(strict_types=1);

namespace Crunz\Logger;

use Crunz\Application\Service\ConfigurationInterface;
use Crunz\Application\Service\LoggerFactoryInterface;
use Crunz\Clock\ClockInterface;
use Crunz\Exception\CrunzException;
use Crunz\Infrastructure\Psr\Logger\PsrStreamLoggerFactory;
use Crunz\Task\Timezone;

class LoggerFactory
{
    private ?LoggerFactoryInterface $loggerFactory = null;

    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly Timezone $timezoneProvider,
        private readonly ConsoleLoggerInterface $consoleLogger,
        private readonly ClockInterface $clock,
    ) {
    }

    public function create(): Logger
    {
        $loggerFactory = $this->loggerFactory();
        $configuration = $this->configuration;
        $innerLogger = $loggerFactory->create($configuration);

        return new Logger($innerLogger);
    }

    public function createEvent(string $output): Logger
    {
        $loggerFactory = $this->loggerFactory();
        $eventConfiguration = $this->configuration->withNewEntry('output_log_file', $output);
        $innerLogger = $loggerFactory->create($eventConfiguration);

        return new Logger($innerLogger);
    }

    private function loggerFactory(): LoggerFactoryInterface
    {
        return $this->loggerFactory ??= $this->initializeLoggerFactory();
    }

    private function initializeLoggerFactory(): LoggerFactoryInterface
    {
        $timezoneLog = $this->configuration
            ->get('timezone_log')
        ;

        if ($timezoneLog) {
            $timezone = $this->timezoneProvider
                ->timezoneForComparisons()
            ;

            $this->consoleLogger
                ->veryVerbose("Timezone for '<info>timezone_log</info>': '<info>{$timezone->getName()}</info>'")
            ;
        }

        $this->loggerFactory = $this->createLoggerFactory(
            $this->configuration,
            $this->timezoneProvider,
            $this->clock
        );

        return $this->loggerFactory;
    }

    private function createLoggerFactory(
        ConfigurationInterface $configuration,
        Timezone $timezoneProvider,
        ClockInterface $clock,
    ): LoggerFactoryInterface {
        $params = [];
        $loggerFactoryClass = $configuration->get('logger_factory');

        $this->consoleLogger
            ->veryVerbose("Class for '<info>logger_factory</info>': '<info>{$loggerFactoryClass}</info>'.")
        ;

        if (!\class_exists($loggerFactoryClass)) {
            throw new CrunzException("Class '{$loggerFactoryClass}' does not exists.");
        }

        $isPsrStreamLoggerFactory = \is_a(
            $loggerFactoryClass,
            PsrStreamLoggerFactory::class,
            true
        );
        if ($isPsrStreamLoggerFactory) {
            $params[] = $timezoneProvider;
            $params[] = $clock;
        }

        return new $loggerFactoryClass(...$params);
    }
}
