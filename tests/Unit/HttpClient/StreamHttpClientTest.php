<?php

declare(strict_types=1);

namespace Crunz\Tests\Unit\HttpClient;

use Crunz\HttpClient\HttpClientException;
use Crunz\HttpClient\StreamHttpClient;
use PHPUnit\Framework\TestCase;

final class StreamHttpClientTest extends TestCase
{
    /** @test */
    public function ping_fail_with_invalid_address(): void
    {
        // Arrange
        $url = 'http://www.wrong-address.tld';
        $client = new StreamHttpClient();
        $expectedExceptionMessage = 'Ping failed.';

        // Expect
        $this->expectException(HttpClientException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        // Act
        $client->ping($url);
    }
}
