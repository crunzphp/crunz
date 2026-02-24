<?php

declare(strict_types=1);

namespace Crunz\Tests\Unit\Service;

use Crunz\Infrastructure\Laravel\LaravelClosureSerializer;
use Crunz\Tests\TestCase\UnitTestCase;

final class LaravelClosureSerializerTest extends UnitTestCase
{
    private LaravelClosureSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new LaravelClosureSerializer();
    }

    public function test_serialize_simple_closure(): void
    {
        $closure = static function (): string {
            return 'hello';
        };

        $serialized = $this->serializer->serialize($closure);
        $result = $this->serializer->unserialize($serialized);

        self::assertSame('hello', $result());
    }

    public function test_serialize_closure_with_use_variable(): void
    {
        $name = 'crunz';
        $closure = static function () use ($name): string {
            return "hello {$name}";
        };

        $serialized = $this->serializer->serialize($closure);
        $result = $this->serializer->unserialize($serialized);

        self::assertSame('hello crunz', $result());
    }

    public function test_serialize_closure_bound_to_object_with_closure_properties(): void
    {
        $runner = new TaskRunnerStub();
        $closure = $runner->createTask();

        $serialized = $this->serializer->serialize($closure);
        $result = $this->serializer->unserialize($serialized);

        self::assertSame('running daily-report', $result());
    }

    /**
     * Regression test for laravel/serializable-closure#126.
     *
     * v2.0.9 skips walking properties of objects that implement __serialize,
     * leaving nested closures unwrapped and causing "Serialization of 'Closure'
     * is not allowed".
     */
    public function test_serialize_closure_bound_to_object_with_serialize_and_closure_properties(): void
    {
        $runner = new TaskRunnerWithSerializeStub();
        $closure = $runner->createTask();

        $serialized = $this->serializer->serialize($closure);
        $result = $this->serializer->unserialize($serialized);

        self::assertSame('running daily-report', $result());
    }

    public function test_closure_code_can_be_extracted(): void
    {
        $testClosure = static fn (): \stdClass => new \stdClass();

        $code = $this->serializer->closureCode($testClosure);

        self::assertSame('static fn (): \stdClass => new \stdClass()', $code);
    }
}

/**
 * @internal
 */
class TaskRunnerStub
{
    public string $taskName = 'daily-report';
    /** @var \Closure[] */
    public array $filters = [];

    public function createTask(): \Closure
    {
        $this->filters[] = static function (): bool { return true; };

        return function (): string {
            return "running {$this->taskName}";
        };
    }
}

/**
 * @internal
 */
class TaskRunnerWithSerializeStub
{
    public string $taskName = 'daily-report';
    /** @var \Closure[] */
    public array $filters = [];

    /** @return array{taskName: string, filters: array<\Closure>} */
    public function __serialize(): array
    {
        return [
            'taskName' => $this->taskName,
            'filters' => $this->filters,
        ];
    }

    /** @param array{taskName: string, filters: array<\Closure>} $data */
    public function __unserialize(array $data): void
    {
        $this->taskName = $data['taskName'];
        $this->filters = $data['filters'];
    }

    public function createTask(): \Closure
    {
        $this->filters[] = static function (): bool { return true; };

        return function (): string {
            return "running {$this->taskName}";
        };
    }
}
