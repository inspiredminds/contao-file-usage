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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Exception\TableDoesNotExist;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Provides common used methods to search the database for file references.
 */
abstract class AbstractDatabaseProvider implements FileUsageProviderInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    protected array $ignoreTables = [];

    private AbstractSchemaManager|null $schemaManager = null;

    public function setIgnoreTables(array $ignoreTables): void
    {
        $this->ignoreTables = $ignoreTables;
    }

    #[SubscribedService]
    protected function connection(): Connection
    {
        return $this->container->get(__METHOD__);
    }

    protected function getSchemaManager(): AbstractSchemaManager
    {
        return $this->schemaManager ??= $this->connection()->createSchemaManager();
    }

    protected function getTablesWithResults(): array
    {
        $tablesWithResults = [];

        foreach ($this->getSchemaManager()->listTables() as $table) {
            $tableName = $table->getName();

            if (\in_array($tableName, $this->ignoreTables, true)) {
                continue;
            }

            $results = $this->connection()->createQueryBuilder()
                ->select('*')
                ->from($tableName)
                ->executeQuery()
            ;

            if (!$results instanceof Result) {
                continue;
            }

            $tablesWithResults[$tableName] = $results;
        }

        return $tablesWithResults;
    }

    protected function getPrimaryKey(string $table, AbstractSchemaManager $schemaManager): string|null
    {
        try {
            $table = $this->getSchemaManager()->introspectTable($table);
        } catch (TableDoesNotExist) {
            return null;
        }

        if (!$table || !$table->getPrimaryKey()) {
            return null;
        }

        $primaryKey = $table->getPrimaryKey();

        if (!$primaryKey) {
            return null;
        }

        $columns = $primaryKey->getColumns();

        if ([] === $columns) {
            return null;
        }

        return reset($columns);
    }
}
