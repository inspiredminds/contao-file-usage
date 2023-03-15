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
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use InspiredMinds\ContaoFileUsage\Result\DatabaseInsertTagResult;
use InspiredMinds\ContaoFileUsage\Result\Results;

/**
 * Searches for insert tag file references (file, picture, figure) in the database.
 */
class DatabaseInsertTagProvider implements FileUsageProviderInterface
{
    use DatabaseProviderTrait;

    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function find(string $uuid): Results
    {
        $results = new Results($uuid);

        /** @var AbstractSchemaManager $schemaManager */
        $schemaManager = method_exists($this->db, 'createSchemaManager') ? $this->db->createSchemaManager() : $this->db->getSchemaManager();

        foreach ($schemaManager->listTables() as $table) {
            $tableName = $table->getName();
            $pk = $this->getPrimaryKey($table->getName(), $schemaManager);

            foreach ($table->getColumns() as $column) {
                $field = $column->getName();
                $type = $column->getType();

                if (!$type instanceof StringType && !$type instanceof TextType) {
                    continue;
                }

                $regex = $this->db->getDatabasePlatform()->getRegexpExpression();

                $occurrences = $this->db->fetchAllAssociative('
                    SELECT * FROM '.$this->db->quoteIdentifier($tableName).'
                     WHERE '.$this->db->quoteIdentifier($field).' '.$regex.' ?',
                    ['\{\{(file|picture|figure)::'.$uuid.'((\||\?)[^}]+)?\}\}']
                );

                foreach ($occurrences as $occurrence) {
                    $results->addResult(new DatabaseInsertTagResult($tableName, $field, $occurrence[$pk] ?? null, $pk));
                }
            }
        }

        return $results;
    }
}
