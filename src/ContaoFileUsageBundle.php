<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ContaoFileUsageBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
