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
use InspiredMinds\ContaoFileUsage\Result\FilesystemResult;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;
use Symfony\Component\Finder\Finder;

/**
 * Scans template and source folders (upload folder, templates, src, ...) for file references - both
 * Contao insert tags and upload path references - and reports the location (path + line) where they
 * are used. The scanned folders and file extensions can be extended/reduced via the bundle config.
 */
class FilesystemProvider implements FileUsageProviderInterface
{
    private const DEFAULT_FOLDERS = ['templates', 'src'];

    private const DEFAULT_EXTENSIONS = ['html5', 'twig', 'css', 'scss', 'php', 'js'];

    // phpcs:disable
    private const INSERT_TAG_PATTERN = '~{{(?:file|picture|figure)::([a-f0-9]{8}-[a-f0-9]{4}-1[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12})(?:[|?][^}]+)?}}~';
    // phpcs:enable

    private readonly string $pathPattern;

    /**
     * @param list<string> $includeFolders
     * @param list<string> $excludeFolders
     * @param list<string> $includeExtensions
     * @param list<string> $excludeExtensions
     */
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly string $projectDir,
        private readonly string $uploadPath,
        private readonly array $includeFolders,
        private readonly array $excludeFolders,
        private readonly array $includeExtensions,
        private readonly array $excludeExtensions,
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

        $folders = $this->getFolders();
        $extensions = $this->getExtensions();

        if ([] === $folders || [] === $extensions) {
            return $collection;
        }

        $finder = new Finder();
        $finder->files()->in($folders)->ignoreUnreadableDirs();

        foreach ($extensions as $extension) {
            $finder->name('*.'.$extension);
        }

        foreach ($finder as $file) {
            $relativePath = $this->getRelativePath($file->getPathname());
            $lineNumber = 0;

            foreach (preg_split('/\R/', $file->getContents()) ?: [] as $line) {
                ++$lineNumber;
                $this->processLine($collection, $line, $relativePath, $lineNumber);
            }
        }

        return $collection;
    }

    private function processLine(ResultsCollection $collection, string $line, string $path, int $lineNumber): void
    {
        // Insert tags carry the UUID directly.
        if (preg_match_all(self::INSERT_TAG_PATTERN, $line, $matches)) {
            foreach ($matches[1] as $uuid) {
                $collection->addResult($uuid, new FilesystemResult($path, $lineNumber));
            }
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
     * The scanned folders: the defaults (upload folder, templates, src) plus the configured includes,
     * minus the configured excludes, reduced to the folders that actually exist.
     *
     * @return list<string> Absolute paths of existing folders to scan.
     */
    private function getFolders(): array
    {
        $base = array_merge([$this->uploadPath], self::DEFAULT_FOLDERS);
        $names = array_diff(array_unique(array_merge($base, $this->includeFolders)), $this->excludeFolders);

        $folders = [];

        foreach ($names as $name) {
            $path = $this->projectDir.'/'.trim($name, '/');

            if (is_dir($path)) {
                $folders[] = $path;
            }
        }

        return $folders;
    }

    /**
     * The scanned extensions: the defaults plus the configured includes, minus the configured excludes.
     *
     * @return list<string>
     */
    private function getExtensions(): array
    {
        $normalize = static fn (string $ext): string => ltrim($ext, '.');

        $extensions = array_map($normalize, array_merge(self::DEFAULT_EXTENSIONS, $this->includeExtensions));
        $exclude = array_map($normalize, $this->excludeExtensions);

        return array_values(array_diff(array_unique($extensions), $exclude));
    }

    private function getRelativePath(string $pathname): string
    {
        if (str_starts_with($pathname, $this->projectDir.'/')) {
            return substr($pathname, \strlen($this->projectDir) + 1);
        }

        return $pathname;
    }
}
