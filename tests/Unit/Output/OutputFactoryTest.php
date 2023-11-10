<?php

declare(strict_types=1);

namespace Crunz\Tests\Unit\Output;

use Crunz\Output\OutputFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class OutputFactoryTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider inputProvider
     */
    public function input_defines_output_verbosity(InputInterface $input, int $expectedVerbosity): void
    {
        $factory = new OutputFactory($input);

        $output = $factory->createOutput();

        self::assertSame($expectedVerbosity, $output->getVerbosity());
    }

    /** @return iterable<string,array> */
    public static function inputProvider(): iterable
    {
        yield 'quietShort' => [
            self::createInput('-q'),
            OutputInterface::VERBOSITY_QUIET,
        ];

        yield 'quietLong' => [
            self::createInput('--quiet'),
            OutputInterface::VERBOSITY_QUIET,
        ];

        yield 'normal' => [
            self::createInput('--filter'),
            OutputInterface::VERBOSITY_NORMAL,
        ];

        yield 'verbose' => [
            self::createInput('-v'),
            OutputInterface::VERBOSITY_VERBOSE,
        ];

        yield 'veryVerbose' => [
            self::createInput('-vv'),
            OutputInterface::VERBOSITY_VERY_VERBOSE,
        ];

        yield 'debug' => [
            self::createInput('-vvv'),
            OutputInterface::VERBOSITY_DEBUG,
        ];
    }

    private static function createInput(string $option): InputInterface
    {
        return new ArgvInput(['', $option]);
    }
}
