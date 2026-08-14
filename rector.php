<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

use Contao\Rector\Set\SetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([SetList::CONTAO])
    ->withPaths([
        __DIR__.'/contao',
        __DIR__.'/src',
        __DIR__.'/templates',
        __DIR__.'/ecs.php',
        __DIR__.'/rector.php',
    ])
    ->withParallel()
    ->withCache(sys_get_temp_dir().'/rector_cache')
;
