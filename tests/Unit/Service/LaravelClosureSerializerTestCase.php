<?php

declare(strict_types=1);

namespace Crunz\Tests\Unit\Service;

use Crunz\Infrastructure\Laravel\LaravelClosureSerializer;

final class LaravelClosureSerializerTestCase extends AbstractClosureSerializerTestCase
{
    protected function createSerializer(): LaravelClosureSerializer
    {
        return new LaravelClosureSerializer();
    }
}
