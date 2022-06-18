<?php

declare(strict_types=1);

namespace Crunz\Tests\Unit\Configuration;

use Crunz\Configuration\Configuration;
use Crunz\Configuration\ConfigurationParserInterface;
use Crunz\Filesystem\FilesystemInterface;
use Crunz\Path\Path;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    /** @test */
    public function get_can_return_path_split_by_dot(): void
    {
        $configuration = $this->createConfiguration(
            [
                'smtp' => [
                    'port' => 1234,
                ],
            ]
        );

        self::assertSame(1234, $configuration->get('smtp.port'));
    }

    /** @test */
    public function get_return_default_value_if_path_not_exists(): void
    {
        $configuration = $this->createConfiguration();

        self::assertNull($configuration->get('wrong'));
        self::assertSame('anon', $configuration->get('notExist', 'anon'));
    }

    /** @test */
    public function source_path_is_relative_to_cwd(): void
    {
        $cwd = \sys_get_temp_dir();
        $sourcePath = Path::fromStrings('app', 'tasks');
        $expectedPath = Path::fromStrings($cwd, $sourcePath->toString());
        $configuration = $this->createConfiguration(['source' => $sourcePath->toString()], $cwd);

        self::assertSame($expectedPath->toString(), $configuration->getSourcePath());
    }

    /** @test */
    public function source_path_fallback_to_tasks_directory(): void
    {
        $cwd = \sys_get_temp_dir();
        $expectedPath = Path::fromStrings($cwd, 'tasks');
        $configuration = $this->createConfiguration([], $cwd);

        self::assertSame($expectedPath->toString(), $configuration->getSourcePath());
    }

    /** @test */
    public function set_configuration_key_value(): void
    {
        $cwd = \sys_get_temp_dir();
        $sourcePath = Path::fromStrings('app', 'tasks');
        $configuration = $this->createConfiguration(['source' => $sourcePath->toString()], $cwd);

        $keyName = 'test_key';
        $expectedValue = 'test_value';

        $newConfiguration = $configuration->withNewEntry($keyName, $expectedValue);

        self::assertSame($newConfiguration->get($keyName), $expectedValue);
    }

    /** @test */
    public function set_configuration_key_array(): void
    {
        $cwd = \sys_get_temp_dir();
        $sourcePath = Path::fromStrings('app', 'tasks');
        $configuration = $this->createConfiguration(['source' => $sourcePath->toString()], $cwd);

        $arrayName = 'test_array';
        $keyName = 'test_key';
        $expectedValue = 'test_value';

        $newConfiguration = $configuration->withNewEntry("{$arrayName}.{$keyName}", $expectedValue);
        $expectedArray = $newConfiguration->get($arrayName);

        self::assertIsArray($expectedArray);
        self::assertArrayHasKey($keyName, $expectedArray);
        self::assertSame($expectedArray[$keyName], $expectedValue);
    }

    /** @param array<string,string|array> $config */
    private function createConfiguration(array $config = [], string $cwd = ''): Configuration
    {
        $mockConfigurationParser = $this->createMock(ConfigurationParserInterface::class);
        $mockConfigurationParser
            ->method('parseConfig')
            ->willReturn($config)
        ;

        $mockFilesystem = $this->createMock(FilesystemInterface::class);
        $mockFilesystem
            ->method('getCwd')
            ->willReturn($cwd)
        ;

        return new Configuration($mockConfigurationParser, $mockFilesystem);
    }
}
