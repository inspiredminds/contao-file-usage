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

use Doctrine\DBAL\Schema\AbstractSchemaManager;

trait DatabaseProviderTrait
{
    private function getPrimaryKey(string $table, AbstractSchemaManager $schemaManager): ?string
    {
        $table = $schemaManager->listTableDetails($table) ?? null;

        if (null === $table || !$table->hasPrimaryKey()) {
            return null;
        }

        return $table->getPrimaryKeyColumns()[0];
    }
}
