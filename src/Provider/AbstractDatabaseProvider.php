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

/**
 * Provides common used methods to search the database for file references.
 */
abstract class AbstractDatabaseProvider implements FileUsageProviderInterface
{
    private const IGNORE_TABLES = ['tl_version', 'tl_log', 'tl_undo', 'tl_search_index'];

    private Connection $db;
    private ?AbstractSchemaManager $schemaManager = null;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    protected function getSchemaManager(): AbstractSchemaManager
    {
        if (null === $this->schemaManager) {
            $this->schemaManager = method_exists($this->db, 'createSchemaManager') ? $this->db->createSchemaManager() : $this->db->getSchemaManager();
        }

        return $this->schemaManager;
    }

    protected function getTablesWithResults(): array
    {
        $tablesWithResults = [];

        foreach ($this->getSchemaManager()->listTables() as $table) {
            $tableName = $table->getName();

            if (\in_array($tableName, self::IGNORE_TABLES, true)) {
                continue;
            }

            $results = $this->db->createQueryBuilder()
                ->select('*')
                ->from($tableName)
                ->execute()
            ;

            if (!$results instanceof Result) {
                continue;
            }

            $tablesWithResults[$tableName] = $results;
        }

        return $tablesWithResults;
    }

    protected function getPrimaryKey(string $table, AbstractSchemaManager $schemaManager): ?string
    {
        $table = $this->getSchemaManager()->listTableDetails($table) ?? null;

        if (null === $table || null === $table->getPrimaryKey()) {
            return null;
        }

        $primaryKey = $table->getPrimaryKey();

        if (null === $primaryKey) {
            return null;
        }

        $columns = $primaryKey->getColumns();

        if (empty($columns)) {
            return null;
        }

        return reset($columns);
    }
}
