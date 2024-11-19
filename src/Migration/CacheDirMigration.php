<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class CacheDirMigration extends AbstractMigration
{
    private readonly string $oldDir;

    private readonly string $newDir;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $environment,
        string $oldCacheDir,
        string $newCacheDir,
    ) {
        $this->oldDir = Path::join($oldCacheDir, 'fileusage');
        $this->newDir = Path::join($newCacheDir, 'fileusage');
    }

    public function shouldRun(): bool
    {
        if ('prod' !== $this->environment) {
            return false;
        }

        return !$this->filesystem->exists($this->newDir) && $this->filesystem->exists($this->oldDir);
    }

    public function run(): MigrationResult
    {
        $this->filesystem->rename($this->oldDir, $this->newDir);

        return $this->createResult(true, 'Moved var/cache/prod/fileusage/ to var/fileusage/');
    }
}
