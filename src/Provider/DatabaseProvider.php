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
use InspiredMinds\ContaoFileUsage\InsertTag\InsertTagParser;
use InspiredMinds\ContaoFileUsage\Result\DatabaseInsertTagResult;
use InspiredMinds\ContaoFileUsage\Result\FileTreeMultipleResult;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;

/**
 * Searches the database for file references.
 */
class DatabaseProvider extends AbstractDatabaseProvider
{
    public function __construct(
        private readonly ResourceFinder $resourceFinder,
        private readonly ContaoFramework $framework,
    ) {
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
            $hasFineUploader = false;

            if ($dcaExists) {
                Controller::loadDataContainer($tableName);
                $fields = $GLOBALS['TL_DCA'][$tableName]['fields'] ?? [];

                foreach ($fields as $config) {
                    if ('fileTree' === ($config['inputType'] ?? '')) {
                        $hasFileTree = true;
                    }

                    if ('fineUploader' === ($config['inputType'] ?? '')) {
                        $hasFineUploader = true;
                    }
                }
            }

            foreach ($results->iterateAssociative() as $result) {
                if ($hasFileTree) {
                    $this->findFileTreeReferences($collection, $tableName, $result, $pk);
                }

                if ($hasFineUploader) {
                    $this->findFineUploaderReferences($collection, $tableName, $result, $pk);
                }

                $this->findInsertTagReferences($collection, $tableName, $result, $pk);
            }
        }

        return $collection;
    }

    private function findFileTreeReferences(ResultsCollection $collection, string $table, array $row, string|null $pk = null): void
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

    private function addMultipleFileReferences(ResultsCollection $collection, string $table, array $row, string $field, $id = null, string|null $pk = null): void
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
                        new FileTreeMultipleResult($table, $field, $id, $pk),
                    );
                }
            }
        }
    }

    private function findFineUploaderReferences(ResultsCollection $collection, string $table, array $row, string|null $pk = null): void
    {
        $fields = $GLOBALS['TL_DCA'][$table]['fields'] ?? [];
        $id = $pk ? $row[$pk] : null;

        foreach ($fields as $field => $config) {
            if ('fineUploader' !== ($config['inputType'] ?? '') || empty($row[$field])) {
                continue;
            }

            foreach (StringUtil::deserialize($row[$field], true) as $reference) {
                if (\is_array($reference)) {
                    $reference = $reference['uuid'] ?? $reference['tmp_name'] ?? null;
                }

                if (!\is_string($reference) || '' === $reference) {
                    continue;
                }

                if (Validator::isUuid($reference)) {
                    $file = FilesModel::findByUuid($reference);
                } else {
                    $file = FilesModel::findByPath($reference);
                }

                if (null === $file || null === $file->uuid) {
                    continue;
                }

                $collection->addResult(
                    StringUtil::binToUuid($file->uuid),
                    new FileTreeMultipleResult($table, $field, $id, $pk),
                );
            }
        }
    }

    private function findInsertTagReferences(ResultsCollection $collection, string $table, array $row, string|null $pk = null): void
    {
        $id = $pk ? $row[$pk] : null;

        foreach ($row as $field => $data) {
            if (empty($data) || !\is_string($data)) {
                continue;
            }

            foreach (InsertTagParser::extractUuids($data) as $uuid) {
                $collection->addResult($uuid, new DatabaseInsertTagResult($table, $field, $id, $pk));
            }
        }
    }
}
