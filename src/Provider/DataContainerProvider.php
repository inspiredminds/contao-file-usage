<?php

namespace InspiredMinds\ContaoFileUsage\Provider;

use Contao\Controller;
use Contao\CoreBundle\Config\ResourceFinder;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use InspiredMinds\ContaoFileUsage\Result\FileUsageDatabaseResult;
use InspiredMinds\ContaoFileUsage\Result\FileUsageResults;

/** 
 * Searches for "fileTree" references in the database.
 */
class DataContainerProvider implements FileUsageProviderInterface
{
    private $framework;
    private $db;
    private $resourceFinder;

    /** 
     * @var AbstractSchemaManager
     */
    private $schemaManager;

    public function __construct(ContaoFramework $framework, Connection $db, ResourceFinder $resourceFinder)
    {
        $this->framework = $framework;
        $this->db = $db;
        $this->resourceFinder = $resourceFinder;
    }

    public function find(string $uuid): FileUsageResults
    {
        $this->framework->initialize();
        /** @var AbstractSchemaManager $schemaManager */
        $schemaManager = method_exists($this->db, 'createSchemaManager') ? $this->db->createSchemaManager() : $this->db->getSchemaManager();
        $results = new FileUsageResults();

        $dcaFiles = $this->resourceFinder->findIn('dca')->depth(0)->files()->name('*.php');
        $processed = [];

        foreach ($dcaFiles as $file) {
            $tableName = $file->getBasename('.php');

            if (\in_array($tableName, $processed, true)) {
                continue;
            }

            $processed[] = $tableName;

            Controller::loadDataContainer($tableName);

            $fields = $GLOBALS['TL_DCA'][$tableName]['fields'] ?? [];

            foreach ($fields as $field => $config) {
                if ('fileTree' !== ($config['inputType'] ?? '')) {
                    continue;
                }

                $pk = $this->getPrimaryKey($tableName, $schemaManager);

                if ($config['eval']['multiple'] ?? false) {
                    //
                } else {
                    $results->addResults($this->searchForSingle($tableName, $field, $uuid, $pk));
                }
            }
        }

        return $results;
    }

    private function searchForSingle(string $table, string $field, string $uuid, string $pk = null): FileUsageResults
    {
        $results = new FileUsageResults();
        
        $occurrences = $this->db->fetchAllAssociative('
            SELECT * FROM '.$this->db->quoteIdentifier($table).' 
                WHERE '.$this->db->quoteIdentifier($field). ' = ?
                OR '.$this->db->quoteIdentifier($field). ' = ?',
            [$uuid, StringUtil::uuidToBin($uuid)]
        );

        foreach ($occurrences as $occurrence) {
            $results->addResult(new FileUsageDatabaseResult($table, $field, $occurrence[$pk] ?? null));
        }

        return $results;
    }

    private function getPrimaryKey(string $table, AbstractSchemaManager $schemaManager): ?string
    {
        $table = $schemaManager->listTableDetails($table) ?? null;

        if (null === $table || !$table->hasPrimaryKey()) {
            return null;
        }

        return $table->getPrimaryKeyColumns()[0];
    }
}
