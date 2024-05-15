<?php

namespace InspiredMinds\ContaoFileUsage\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\KernelInterface;

class CacheDirMigration extends AbstractMigration
{
    private Filesystem $filesystem;
    private string $environment;
    private string $oldDir;
    private string $newDir;

    public function __construct(
        Filesystem $filesystem,
        string $environment,
        string $oldCacheDir,
        string $newCacheDir
    )
    {
        $this->filesystem = $filesystem;
        $this->environment = $environment;
        $this->oldDir = Path::join($oldCacheDir, 'fileusage');
        $this->newDir = Path::join($newCacheDir, 'fileusage');
    }


    public function shouldRun(): bool
    {
        if ('prod' !== $this->environment) {
            return false;
        }

        if ($this->filesystem->exists($this->newDir) || !$this->filesystem->exists($this->oldDir)) {
            return false;
        }

        return true;
    }

    public function run(): MigrationResult
    {
        $this->filesystem->rename($this->oldDir, $this->newDir);

        return $this->createResult(true, 'Moved var/cache/prod/fileusage/ to var/fileusage/');
    }
}
