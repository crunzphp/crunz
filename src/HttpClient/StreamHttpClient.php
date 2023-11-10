<?php

declare(strict_types=1);

namespace Crunz\HttpClient;

final class StreamHttpClient implements HttpClientInterface
{
    /**
     * @param string $url
     *
     * @throws HttpClientException
     */
    public function ping($url): void
    {
        $context = \stream_context_create(
            [
                'http' => [
                    'user_agent' => 'Crunz StreamHttpClient',
                    'timeout' => 5,
                ],
            ]
        );
        $resource = @\fopen(
            $url,
            'rb',
            false,
            $context
        );

        if (false === $resource) {
            throw new HttpClientException('Ping failed.');
        }

        \fclose($resource);
    }
}
