<?php

declare(strict_types=1);

namespace Swis\Bitbucket\Reports;

/**
 * Copied from https://github.com/phpstan/phpstan-src/blob/1.11.x/src/File/ParentDirectoryRelativePathHelper.php
 */
class ParentDirectoryRelativePathHelper
{
    public function __construct(private string $parentDirectory)
    {
    }

    public function getRelativePath(string $filename): string
    {
        return implode('/', $this->getFilenameParts($filename));
    }

    /**
     * @return string[]
     */
    public function getFilenameParts(string $filename): array
    {
        $schemePosition = strpos($filename, '://');
        if ($schemePosition !== \false) {
            $filename = substr($filename, $schemePosition + 3);
        }
        $parentParts = explode('/', trim(str_replace('\\', '/', $this->parentDirectory), '/'));
        $parentPartsCount = count($parentParts);
        $filenameParts = explode('/', trim(str_replace('\\', '/', $filename), '/'));
        $filenamePartsCount = count($filenameParts);
        $i = 0;
        for (; $i < $filenamePartsCount; $i++) {
            if ($parentPartsCount < $i + 1) {
                break;
            }
            $parentPath = implode('/', array_slice($parentParts, 0, $i + 1));
            $filenamePath = implode('/', array_slice($filenameParts, 0, $i + 1));
            if ($parentPath !== $filenamePath) {
                break;
            }
        }
        if ($i === 0) {
            return [$filename];
        }
        $dotsCount = $parentPartsCount - $i;
        if ($dotsCount < 0) {
            throw new \RuntimeException('This should not happen.');
        }

        return array_merge(array_fill(0, $dotsCount, '..'), array_slice($filenameParts, $i));
    }
}
