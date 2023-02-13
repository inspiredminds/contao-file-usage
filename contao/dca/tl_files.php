<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

use InspiredMinds\ContaoFileUsage\DataContainer\FolderDataContainer;

$GLOBALS['TL_DCA']['tl_files']['config']['dataContainer'] = FolderDataContainer::class;
$GLOBALS['TL_DCA']['tl_files']['list']['operations']['fileusage'] = [];

$GLOBALS['TL_DCA']['tl_files']['list']['global_operations'] = array_merge([
    'unused' => [
        'href' => 'unused=1',
        'icon' => 'bundles/contaofileusage/search_16px.svg',
    ],
], $GLOBALS['TL_DCA']['tl_files']['list']['global_operations']);
