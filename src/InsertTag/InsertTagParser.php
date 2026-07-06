<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\InsertTag;

/**
 * Central definition for detecting file references through Contao insert tags.
 *
 * Any insert tag whose first parameter is a Contao file UUID references that file - regardless of the
 * tag name (file, picture, figure, image, svg or custom tags like foo_bar, ...) and regardless of any
 * following parameters (which may even span multiple lines). Providers (database, file system,
 * MetaModels, ...) should rely on this pattern instead of hard-coding their own.
 */
final class InsertTagParser
{
    // phpcs:disable
    public const PATTERN = '~{{[a-z0-9_]+::([a-f0-9]{8}-[a-f0-9]{4}-1[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12})[^}]*}}~';
    // phpcs:enable

    /**
     * Extracts all file UUIDs referenced through insert tags in the given content.
     *
     * @return list<string>
     */
    public static function extractUuids(string $content): array
    {
        if (!preg_match_all(self::PATTERN, $content, $matches)) {
            return [];
        }

        return $matches[1];
    }
}
