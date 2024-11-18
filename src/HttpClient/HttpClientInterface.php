<?php

declare(strict_types=1);

namespace Crunz\HttpClient;

interface HttpClientInterface
{
    /**
     * @param non-empty-string $url
     *
     * @throws HttpClientException
     */
    public function ping($url): void;
}
