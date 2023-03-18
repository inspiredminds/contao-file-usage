<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Provider;

use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;

interface FileUsageProviderInterface
{
    public function find(): ResultsCollection;
}
