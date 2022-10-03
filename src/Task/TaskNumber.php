<?php

declare(strict_types=1);

namespace Crunz\Task;

use Crunz\Exception\WrongTaskNumberException;

class TaskNumber
{
    public const MIN_VALUE = 1;
    private int $number;

    /** @throws WrongTaskNumberException */
    private function __construct(int $number)
    {
        if ($number < self::MIN_VALUE) {
            throw new WrongTaskNumberException('Passed task number must be greater or equal to 1.');
        }

        $this->number = $number;
    }

    /**
     * @param string $value
     *
     * @return TaskNumber
     *
     * @throws WrongTaskNumberException
     */
    public static function fromString($value)
    {
        if (!\is_string($value)) {
            throw new WrongTaskNumberException('Passed task number is not string.');
        }

        if (!\is_numeric($value)) {
            throw new WrongTaskNumberException("Task number '{$value}' is not numeric.");
        }

        $number = (int) $value;

        return new self($number);
    }

    public function asInt(): int
    {
        return $this->number;
    }

    public function asArrayIndex(): int
    {
        return $this->number - 1;
    }
}
