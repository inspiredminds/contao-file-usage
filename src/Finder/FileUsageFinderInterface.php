<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Finder;

use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;

interface FileUsageFinderInterface
{
    public function find(): ResultsCollection;
}
