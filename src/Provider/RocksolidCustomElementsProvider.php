<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Provider;

use Contao\FilesModel;
use Contao\StringUtil;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use InspiredMinds\ContaoFileUsage\Result\FileTreeMultipleResult;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;
use MadeYourDay\RockSolidCustomElements\CustomElements;
use MadeYourDay\RockSolidCustomElements\RockSolidCustomElementsBundle;

class RocksolidCustomElementsProvider implements FileUsageProviderInterface
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function find(): ResultsCollection
    {
        $collection = new ResultsCollection();

        if (!class_exists(RockSolidCustomElementsBundle::class)) {
            return $collection;
        }

        /** @var AbstractSchemaManager $schemaManager */
        $schemaManager = method_exists($this->db, 'createSchemaManager') ? $this->db->createSchemaManager() : $this->db->getSchemaManager();
        $tableNames = $schemaManager->listTableNames();

        foreach (['tl_content', 'tl_module'] as $fragmentTable) {
            if (!\in_array($fragmentTable, $tableNames, true)) {
                continue;
            }

            if (!isset($schemaManager->listTableColumns($fragmentTable)['rsce_data'])) {
                continue;
            }

            $rsceRecords = $this->db->fetchAllAssociative("SELECT * FROM $fragmentTable WHERE type LIKE 'rsce_%' AND rsce_data IS NOT NULL");

            $this->processRsceRecords($rsceRecords, $fragmentTable, $collection);
        }

        return $collection;
    }

    private function processRsceRecords(array $records, string $table, ResultsCollection $collection): void
    {
        foreach ($records as $record) {
            $config = CustomElements::getConfigByType($record['type']);

            if (null === $config || empty($config['fields'])) {
                continue;
            }

            $rsceData = json_decode((string) $record['rsce_data'], true, 512, JSON_THROW_ON_ERROR);

            foreach ($config['fields'] as $field => $fieldConfig) {
                if (empty($rsceData[$field])) {
                    continue;
                }

                if ('fileTree' === ($fieldConfig['inputType'] ?? '')) {
                    if ($fieldConfig['eval']['multiple'] ?? false) {
                        $this->addMultipleFileReferences($collection, $table, $record, $field, $rsceData);
                    } else {
                        $uuid = $rsceData[$field] ?? null;

                        if (Validator::isUuid($uuid)) {
                            if (Validator::isBinaryUuid($uuid)) {
                                $uuid = StringUtil::binToUuid($uuid);
                            }

                            $collection->addResult($uuid, new FileTreeMultipleResult($table, 'rsce_data', $record['id'], 'id'));
                        }
                    }
                } elseif ('list' === ($fieldConfig['inputType'] ?? '')) {
                    foreach ($rsceData[$field] as $rsceSubData) {
                        foreach ($fieldConfig['fields'] as $subfield => $subfieldConfig) {
                            if ($subfieldConfig['eval']['multiple'] ?? false) {
                                $this->addMultipleFileReferences($collection, $table, $record, $subfield, $rsceSubData);
                            } else {
                                $uuid = $rsceSubData[$subfield] ?? null;

                                if (Validator::isUuid($uuid)) {
                                    if (Validator::isBinaryUuid($uuid)) {
                                        $uuid = StringUtil::binToUuid($uuid);
                                    }

                                    $collection->addResult($uuid, new FileTreeMultipleResult($table, 'rsce_data', $record['id'], 'id'));
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function addMultipleFileReferences(ResultsCollection $collection, string $table, array $row, string $field, array $rsceData): void
    {
        $uuids = StringUtil::deserialize($rsceData[$field] ?? '', true);

        foreach ($uuids as $uuid) {
            if (!Validator::isUuid($uuid)) {
                continue;
            }

            if (Validator::isBinaryUuid($uuid)) {
                $uuid = StringUtil::binToUuid($uuid);
            }

            $collection->addResult($uuid, new FileTreeMultipleResult($table, 'rsce_data', $row['id'], 'id'));

            // Also add children, if the reference is a folder
            $file = FilesModel::findByUuid($uuid);

            if (null !== $file && 'folder' === $file->type) {
                $files = FilesModel::findByPid($uuid);

                foreach ($files as $child) {
                    $collection->addResult(
                        StringUtil::binToUuid($child->uuid),
                        new FileTreeMultipleResult($table, 'rsce_data', $row['id'], 'id'),
                    );
                }
            }
        }
    }
}
