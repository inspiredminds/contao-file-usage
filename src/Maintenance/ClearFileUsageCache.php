<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Maintenance;

use Symfony\Component\Cache\Adapter\AdapterInterface;

class ClearFileUsageCache
{
    public function __construct(private readonly AdapterInterface $cache)
    {
    }

    public function __invoke(): void
    {
        $this->cache->clear();
    }
}
