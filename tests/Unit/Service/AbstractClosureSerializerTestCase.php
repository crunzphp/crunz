<?php

declare(strict_types=1);

namespace Crunz\Tests\Unit\Service;

use Crunz\Application\Service\ClosureSerializerInterface;
use Crunz\Tests\TestCase\UnitTestCase;

abstract class AbstractClosureSerializerTestCase extends UnitTestCase
{
    public function test_closure_code_can_be_extracted(): void
    {
        // Arrange
        $testClosure = static fn (): \stdClass => new \stdClass();
        $serializer = $this->createSerializer();

        // Act
        $code = $serializer->closureCode($testClosure);

        // Assert
        self::assertSame('static fn (): \stdClass => new \stdClass()', $code);
    }

    abstract protected function createSerializer(): ClosureSerializerInterface;
}
