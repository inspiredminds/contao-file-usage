<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Replace;

use InspiredMinds\ContaoFileUsage\Result\ResultInterface;

interface FileReferenceReplacerInterface
{
    public function replace(ResultInterface $result, string $oldUuid, string $newUuid): void;
}
