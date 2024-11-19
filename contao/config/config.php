<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

use InspiredMinds\ContaoFileUsage\Widget\FileTreeWidget;

$GLOBALS['TL_PURGE']['custom']['fileusage'] = [
    'callback' => ['contao_file_usage.maintenance.clear_file_usage_cache', '__invoke'],
];

$GLOBALS['BE_FFL']['fileTree'] = FileTreeWidget::class;
