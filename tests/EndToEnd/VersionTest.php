<?php

declare(strict_types=1);

namespace Crunz\Tests\EndToEnd;

use Composer\InstalledVersions;
use Crunz\Tests\TestCase\EndToEndTestCase;

final class VersionTest extends EndToEndTestCase
{
    public function test_version(): void
    {
        // Arrange
        $envBuilder = $this->createEnvironmentBuilder();
        $envBuilder->withConfig(['timezone' => 'UTC']);
        $environment = $envBuilder->createEnvironment();
        $version = InstalledVersions::getPrettyVersion('crunzphp/crunz');
        $expectedVersion = "Crunz Command Line Interface {$version}";

        // Act
        $process = $environment->runCrunzCommand('--version');

        // Assert
        self::assertSame(
            $expectedVersion,
            \trim(
                \str_replace(
                    PHP_EOL,
                    ' ',
                    $process->getOutput()
                )
            )
        );
    }
}
