<?php

declare(strict_types=1);

namespace Crunz\HttpClient;

use Crunz\Logger\ConsoleLoggerInterface;

final class HttpClientLoggerDecorator implements HttpClientInterface
{
    public function __construct(private HttpClientInterface $httpClient, private ConsoleLoggerInterface $logger)
    {
    }

    public function ping($url): void
    {
        $this->logger
            ->verbose("Trying to ping <info>{$url}</info>.");

        $this->httpClient
            ->ping($url);

        $this->logger
            ->verbose("Pinging url: <info>{$url}</info> was <info>successful</info>.");
    }
}
