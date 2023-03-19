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

use Contao\Controller;
use Contao\CoreBundle\Config\ResourceFinder;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use InspiredMinds\ContaoFileUsage\Result\DatabaseInsertTagResult;
use InspiredMinds\ContaoFileUsage\Result\FileTreeMultipleResult;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;

/**
 * Searches the database for file references.
 */
class DatabaseProvider implements FileUsageProviderInterface
{
    private const INSERT_TAG_PATTERN = '~{{(file|picture|figure)::([a-f0-9]{8}-[a-f0-9]{4}-1[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12})((\||\?)[^}]+)?}}~';

    private $db;
    private $resourceFinder;
    private $framework;

    public function __construct(Connection $db, ResourceFinder $resourceFinder, ContaoFramework $framework)
    {
        $this->db = $db;
        $this->resourceFinder = $resourceFinder;
        $this->framework = $framework;
    }

    public function find(): ResultsCollection
    {
        $this->framework->initialize();

        $collection = new ResultsCollection();

        /** @var AbstractSchemaManager $schemaManager */
        $schemaManager = method_exists($this->db, 'createSchemaManager') ? $this->db->createSchemaManager() : $this->db->getSchemaManager();

        foreach ($schemaManager->listTables() as $table) {
            $tableName = $table->getName();

            $results = $this->db->createQueryBuilder()
                ->select('*')
                ->from($tableName)
                ->execute()
            ;

            if (!$results instanceof ResultStatement) {
                continue;
            }

            $pk = $this->getPrimaryKey($tableName, $schemaManager);

            foreach ($results as $result) {
                $this->findFileTreeReferences($collection, $tableName, $result, $pk);
                $this->findInsertTagReferences($collection, $tableName, $result, $pk);
            }
        }

        return $collection;
    }

    private function findFileTreeReferences(ResultsCollection $collection, string $table, array $row, string $pk = null): void
    {
        // Check if DCA actually exists
        $dca = $this->resourceFinder->findIn('dca')->depth(0)->files()->name($table.'.php');

        if (!$dca->hasResults()) {
            return;
        }

        Controller::loadDataContainer($table);

        $fields = $GLOBALS['TL_DCA'][$table]['fields'] ?? [];

        foreach ($fields as $field => $config) {
            if ('fileTree' !== ($config['inputType'] ?? '') || empty($row[$field])) {
                continue;
            }

            $id = $pk ? $row[$pk] : null;

            if ($config['eval']['multiple'] ?? false) {
                $this->addMultipleFileReferences($collection, $table, $row, $field, $id, $pk);

                if ($orderField = ($config['eval']['orderField'] ?? false)) {
                    $this->addMultipleFileReferences($collection, $table, $row, $orderField, $id, $pk);
                }
            } else {
                $uuid = $row[$field];

                if (Validator::isUuid($uuid)) {
                    if (Validator::isBinaryUuid($uuid)) {
                        $uuid = StringUtil::binToUuid($uuid);
                    }

                    $collection->addResult($uuid, new FileTreeMultipleResult($table, $field, $id, $pk));
                }
            }
        }
    }

    private function getPrimaryKey(string $table, AbstractSchemaManager $schemaManager): ?string
    {
        $table = $schemaManager->listTableDetails($table) ?? null;

        if (null === $table || !$table->hasPrimaryKey()) {
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

    private function addMultipleFileReferences(ResultsCollection $collection, string $table, array $row, string $field, $id = null, string $pk = null): void
    {
        // Ignore some fields
        if (\in_array($table, ['tl_user', 'tl_user_group'], true) && 'filemounts' === $field) {
            return;
        }

        $uuids = StringUtil::deserialize($row[$field], true);

        foreach ($uuids as $uuid) {
            if (!Validator::isUuid($uuid)) {
                continue;
            }

            if (Validator::isBinaryUuid($uuid)) {
                $uuid = StringUtil::binToUuid($uuid);
            }

            $collection->addResult($uuid, new FileTreeMultipleResult($table, $field, $id, $pk));

            // Also add children, if the reference is a folder
            $file = FilesModel::findByUuid($uuid);

            if (null !== $file && 'folder' === $file->type) {
                $files = FilesModel::findByPid($uuid);

                foreach ($files as $child) {
                    $collection->addResult(
                        StringUtil::binToUuid($child->uuid),
                        new FileTreeMultipleResult($table, $field, $id, $pk)
                    );
                }
            }
        }
    }

    private function findInsertTagReferences(ResultsCollection $collection, string $table, array $row, string $pk = null): void
    {
        $id = $pk ? $row[$pk] : null;

        foreach ($row as $field => $data) {
            if (empty($data) || !\is_string($data)) {
                continue;
            }

            if (preg_match_all(self::INSERT_TAG_PATTERN, $data, $matches)) {
                foreach ($matches[2] ?? [] as $uuid) {
                    if (Validator::isStringUuid($uuid)) {
                        $collection->addResult($uuid, new DatabaseInsertTagResult($table, $field, $id, $pk));
                    }
                }
            }
        }
    }
}