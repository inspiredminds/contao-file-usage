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
use Doctrine\DBAL\Connection;
use InspiredMinds\ContaoFileUsage\Result\DatabaseInsertTagResult;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;

/**
 * Searches the database for file references.
 */
class DatabasePathProvider extends AbstractDatabaseProvider
{
    private ResourceFinder $resourceFinder;
    private ContaoFramework $framework;
    private string $pathPattern = '~src\s*=\s*"(__contao_upload_path__/.+?)"~';

    public function __construct(Connection $db, ResourceFinder $resourceFinder, ContaoFramework $framework, string $uploadPath)
    {
        $this->resourceFinder = $resourceFinder;
        $this->framework = $framework;
        $this->pathPattern = str_replace('__contao_upload_path__', $uploadPath, $this->pathPattern);

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
            $hasRteField = false;

            if ($dcaExists) {
                Controller::loadDataContainer($tableName);
                $fields = $GLOBALS['TL_DCA'][$tableName]['fields'] ?? [];

                foreach ($fields as $config) {
                    if ('textarea' === ($config['inputType'] ?? '') && false !== ($config['eval']['rte'] ?? false)) {
                        $hasRteField = true;
                        break;
                    }
                }
            }

            if ($hasRteField) {
                foreach ($results->iterateAssociative() as $result) {
                    $this->findPathReferences($collection, $tableName, $result, $pk);
                }
            }
        }

        return $collection;
    }

    private function findPathReferences(ResultsCollection $collection, string $table, array $row, ?string $pk = null): void
    {
        $id = $pk ? $row[$pk] : null;

        foreach ($row as $field => $data) {
            if (empty($data) || !\is_string($data)) {
                continue;
            }

            if (preg_match_all($this->pathPattern, $data, $matches)) {
                foreach ($matches[1] ?? [] as $path) {
                    $file = FilesModel::findByPath(urldecode($path));

                    if (null === $file) {
                        continue;
                    }

                    $collection->addResult(StringUtil::binToUuid($file->uuid), new DatabaseInsertTagResult($table, $field, $id, $pk));
                }
            }
        }
    }
}
