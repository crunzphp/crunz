<?php

require_once __DIR__ . '/vendor/autoload.php';

if (!\defined('IS_WINDOWS')) {
    \define('IS_WINDOWS', PHP_OS_FAMILY === "Windows");
}

// Disable deprecation helper
$envFlags = new \Crunz\EnvFlags\EnvFlags();
$envFlags->disableDeprecationHandler();

// Make sure current working directory is "tests"
$filesystem = new \Crunz\Filesystem\Filesystem();
if (\strpos($filesystem->getCwd(), 'tests') !== false) {
    return;
}

if (!\chdir('tests')) {
    throw new RuntimeException("Unable to change current directory to 'tests'.");
}
