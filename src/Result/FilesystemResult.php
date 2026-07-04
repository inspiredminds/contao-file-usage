<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Result;

/**
 * A file reference found by scanning the file system (upload folder, templates, source folders, ...).
 *
 * This result is read-only: it only points to the location of the reference (path + line) and is not
 * handled by the replace mechanism.
 */
class FilesystemResult implements ResultInterface
{
    public function __construct(
        private readonly string $path,
        private readonly int $line,
    ) {
    }

    public function getTemplate(): string
    {
        return '@ContaoFileUsage/result/filesystem_result.html.twig';
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getLine(): int
    {
        return $this->line;
    }
}
