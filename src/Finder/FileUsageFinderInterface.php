<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Finder;

use InspiredMinds\ContaoFileUsage\Result\Results;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;

interface FileUsageFinderInterface
{
    public function find(string $uuid, bool $useCache = true): Results;

    public function findAll(bool $useCache = true): ResultsCollection;
}
