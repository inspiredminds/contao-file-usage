<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Maintenance;

use Symfony\Component\Cache\Adapter\AdapterInterface;

class ClearFileUsageCache
{
    private $cache;

    public function __construct(AdapterInterface $cache)
    {
        $this->cache = $cache;
    }

    public function __invoke(): void
    {
        $this->cache->clear();
    }
}
