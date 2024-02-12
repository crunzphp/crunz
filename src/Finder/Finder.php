<?php

declare(strict_types=1);

namespace Crunz\Finder;

use Crunz\Path\Path;

final class Finder implements FinderInterface
{
    public function find(Path $directory, $suffix)
    {
        $quotedSuffix = \preg_quote($suffix, '/');
        $directoryIteratorFlags = \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS;
        $directoryIterator = new \RecursiveDirectoryIterator($directory->toString(), $directoryIteratorFlags);
        $recursiveIterator = new \RecursiveIteratorIterator($directoryIterator);

        $regexIterator = new \RegexIterator(
            $recursiveIterator,
            "/^.+{$quotedSuffix}$/i",
            \RecursiveRegexIterator::GET_MATCH
        );

        /** @var \SplFileInfo[] $files */
        $files = \array_map(
            static fn (array $file) => new \SplFileInfo(\reset($file)),
            \iterator_to_array($regexIterator)
        );

        return $files;
    }
}
