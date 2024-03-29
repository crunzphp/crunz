<?php

declare(strict_types=1);

namespace Crunz\Tests\Unit\Timezone;

use Crunz\Timezone\Provider;
use PHPUnit\Framework\TestCase;

class ProviderTest extends TestCase
{
    /**
     * @runInSeparateProcess
     *
     * @test
     */
    public function default_timezone_is_returned(): void
    {
        $timezoneName = 'Europe/Warsaw';
        \date_default_timezone_set($timezoneName);

        $provider = new Provider();
        $timezone = $provider->defaultTimezone();

        self::assertSame($timezoneName, $timezone->getName());
    }
}
