<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Provider;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use InspiredMinds\ContaoFileUsage\InsertTag\InsertTagParser;
use InspiredMinds\ContaoFileUsage\Result\FilesystemResult;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Scans files within the file system for file references - both Contao insert tags and references to the
 * upload path - and reports where they are used (path + line). Which files are scanned is defined through
 * the paths as well as the include/exclude patterns of the bundle configuration.
 */
class FilesystemProvider implements FileUsageProviderInterface
{
    private readonly string $pathPattern;

    /**
     * @param list<string> $paths           Paths to scan, either absolute or relative to the project dir
     * @param list<string> $includePatterns Regular expressions the path of a file has to match
     * @param list<string> $excludePatterns Regular expressions excluding a file from being scanned
     */
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly string $projectDir,
        private readonly string $uploadPath,
        private readonly array $paths,
        private readonly array $includePatterns,
        private readonly array $excludePatterns,
    ) {
        // Match any occurrence of "<uploadPath>/<path>" (not only in href/src, but also url(), strings,
        // ...), stopping at the first character that cannot be part of a file path. The look-behind
        // avoids matching in the middle of a longer word.
        $this->pathPattern = '~(?<![A-Za-z0-9._/\-])'.preg_quote($this->uploadPath, '~').'/[A-Za-z0-9._/\-%]+~';
    }

    public function find(): ResultsCollection
    {
        $this->framework->initialize();

        $collection = new ResultsCollection();

        if (!$paths = $this->getPaths()) {
            return $collection;
        }

        $finder = (new Finder())
            ->files()
            ->in($paths)
            ->ignoreUnreadableDirs()
            ->filter(fn (SplFileInfo $file): bool => $this->isScannable($file->getPathname()))
        ;

        foreach ($finder as $file) {
            $relativePath = Path::makeRelative($file->getPathname(), $this->projectDir);
            $lineNumber = 0;

            $fileObject = $file->openFile('r');
            $fileObject->setFlags(\SplFileObject::DROP_NEW_LINE);

            foreach ($fileObject as $line) {
                $this->processLine($collection, $line, $relativePath, ++$lineNumber);
            }
        }

        return $collection;
    }

    /**
     * Determines whether the file of the given path has to be scanned.
     */
    private function isScannable(string $path): bool
    {
        // Normalize path (for Windows)
        $path = Path::normalize($path);

        foreach ($this->excludePatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return false;
            }
        }

        if (!$this->includePatterns) {
            return true;
        }

        foreach ($this->includePatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private function processLine(ResultsCollection $collection, string $line, string $path, int $lineNumber): void
    {
        // Insert tags carry the UUID directly.
        foreach (InsertTagParser::extractUuids($line) as $uuid) {
            $collection->addResult($uuid, new FilesystemResult($path, $lineNumber));
        }

        // Upload path references have to be resolved to a tracked file to obtain the UUID.
        if (preg_match_all($this->pathPattern, $line, $matches)) {
            foreach ($matches[0] as $reference) {
                $file = FilesModel::findByPath(urldecode($reference));

                if (null === $file || null === $file->uuid) {
                    continue;
                }

                $collection->addResult(StringUtil::binToUuid($file->uuid), new FilesystemResult($path, $lineNumber));
            }
        }
    }

    /**
     * The configured paths, made absolute and reduced to the ones that actually exist.
     *
     * @return list<string>
     */
    private function getPaths(): array
    {
        $paths = array_map(fn (string $path): string => Path::makeAbsolute($path, $this->projectDir), $this->paths);

        return array_values(array_filter(array_unique($paths), is_dir(...)));
    }
}
