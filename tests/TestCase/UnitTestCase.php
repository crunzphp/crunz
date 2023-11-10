<?php

declare(strict_types=1);

namespace Crunz\Tests\TestCase;

use Crunz\Application\Service\ClosureSerializerInterface;
use Crunz\Infrastructure\Laravel\LaravelClosureSerializer;
use PHPUnit\Framework\TestCase;

abstract class UnitTestCase extends TestCase
{
    private ?ClosureSerializerInterface $closureSerializer = null;

    public function createClosureSerializer(): ClosureSerializerInterface
    {
        return $this->closureSerializer ??= new LaravelClosureSerializer();
    }

    protected static function encodeJson(mixed $data): string
    {
        return \json_encode($data, JSON_THROW_ON_ERROR);
    }
}
