<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase;

use Crunz\Application\Service\ClosureSerializerInterface;
use Crunz\Infrastructure\Laravel\LaravelClosureSerializer;
use PHPUnit\Framework\TestCase;

abstract class UnitTestCase extends TestCase
{
    use PolyfillAssertTrait;

    /** @var ClosureSerializerInterface|null */
    private $closureSerializer = null;

    public function createClosureSerializer(): ClosureSerializerInterface
    {
        if (null === $this->closureSerializer) {
            $this->closureSerializer = new LaravelClosureSerializer();
        }

        return $this->closureSerializer;
    }
}
