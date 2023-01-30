<?php

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
