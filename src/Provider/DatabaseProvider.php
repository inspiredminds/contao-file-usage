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
use InspiredMinds\ContaoFileUsage\Result\DatabaseInsertTagResult;
use InspiredMinds\ContaoFileUsage\Result\FileTreeMultipleResult;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;

/**
 * Searches the database for file references.
 */
class DatabaseProvider extends AbstractDatabaseProvider
{
    private const INSERT_TAG_PATTERN = '~{{(file|picture|figure)::([a-f0-9]{8}-[a-f0-9]{4}-1[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12})((\||\?)[^}]+)?}}~';

    private ResourceFinder $resourceFinder;
    private ContaoFramework $framework;

    public function __construct(Connection $db, ResourceFinder $resourceFinder, ContaoFramework $framework)
    {
        $this->resourceFinder = $resourceFinder;
        $this->framework = $framework;

        parent::__construct($db);
    }

    public function find(): ResultsCollection
    {
        $this->framework->initialize();

        $collection = new ResultsCollection();

        foreach ($this->getTablesWithResults() as $tableName => $results) {
            $pk = $this->getPrimaryKey($tableName, $this->getSchemaManager());

            // Check if DCA exists
            $dcaExists = $this->resourceFinder->findIn('dca')->depth(0)->files()->name($tableName.'.php')->hasResults();
            $hasFileTree = false;

            if ($dcaExists) {
                Controller::loadDataContainer($tableName);
                $fields = $GLOBALS['TL_DCA'][$tableName]['fields'] ?? [];

                foreach ($fields as $config) {
                    if ('fileTree' === ($config['inputType'] ?? '')) {
                        $hasFileTree = true;
                        break;
                    }
                }
            }

            foreach ($results->iterateAssociative() as $result) {
                if ($hasFileTree) {
                    $this->findFileTreeReferences($collection, $tableName, $result, $pk);
                }

                $this->findInsertTagReferences($collection, $tableName, $result, $pk);
            }
        }

        return $collection;
    }

    private function findFileTreeReferences(ResultsCollection $collection, string $table, array $row, ?string $pk = null): void
    {
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

    private function addMultipleFileReferences(ResultsCollection $collection, string $table, array $row, string $field, $id = null, ?string $pk = null): void
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
                foreach (FilesModel::findByPid($uuid) ?? [] as $child) {
                    $collection->addResult(
                        StringUtil::binToUuid($child->uuid),
                        new FileTreeMultipleResult($table, $field, $id, $pk)
                    );
                }
            }
        }
    }

    private function findInsertTagReferences(ResultsCollection $collection, string $table, array $row, ?string $pk = null): void
    {
        $id = $pk ? $row[$pk] : null;

        foreach ($row as $field => $data) {
            if (empty($data) || !\is_string($data)) {
                continue;
            }

            if (preg_match_all(self::INSERT_TAG_PATTERN, $data, $matches)) {
                foreach ($matches[2] ?? [] as $uuid) {
                    $collection->addResult($uuid, new DatabaseInsertTagResult($table, $field, $id, $pk));
                }
            }
        }
    }
}
