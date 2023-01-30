<?php

namespace InspiredMinds\ContaoFileUsage\Provider;

use Contao\Controller;
use Contao\CoreBundle\Config\ResourceFinder;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use InspiredMinds\ContaoFileUsage\Result\DatabaseReferenceResult;
use InspiredMinds\ContaoFileUsage\Result\Results;

/** 
 * Searches for "fileTree" references in the database.
 */
class FileTreeProvider implements FileUsageProviderInterface
{
    use DatabaseProviderTrait;

    private $framework;
    private $db;
    private $resourceFinder;

    public function __construct(ContaoFramework $framework, Connection $db, ResourceFinder $resourceFinder)
    {
        $this->framework = $framework;
        $this->db = $db;
        $this->resourceFinder = $resourceFinder;
    }

    public function find(string $uuid): Results
    {
        $this->framework->initialize();

        /** @var AbstractSchemaManager $schemaManager */
        $schemaManager = method_exists($this->db, 'createSchemaManager') ? $this->db->createSchemaManager() : $this->db->getSchemaManager();

        $results = new Results($uuid);
        $dcaFiles = $this->resourceFinder->findIn('dca')->depth(0)->files()->name('*.php');
        $processed = [];

        foreach ($dcaFiles as $file) {
            $tableName = $file->getBasename('.php');

            if (\in_array($tableName, $processed, true)) {
                continue;
            }

            $processed[] = $tableName;
            $pk = $this->getPrimaryKey($tableName, $schemaManager);
            Controller::loadDataContainer($tableName);
            $fields = $GLOBALS['TL_DCA'][$tableName]['fields'] ?? [];

            foreach ($fields as $field => $config) {
                if ('fileTree' !== ($config['inputType'] ?? '')) {
                    continue;
                }

                if ($config['eval']['multiple'] ?? false) {
                    $results->addResults($this->searchForMultiple($tableName, $field, $uuid, $pk));
                    $results->addResults($this->searchForFolders($tableName, $field, $uuid, $pk));

                    if ($config['eval']['orderSRC'] ?? false) {
                        $results->addResults($this->searchForMultiple($tableName, $config['eval']['orderSRC'], $uuid, $pk));
                    }
                } else {
                    $results->addResults($this->searchForSingle($tableName, $field, $uuid, $pk));
                }
            }
        }

        return $results;
    }

    private function searchForSingle(string $table, string $field, string $uuid, string $pk = null): Results
    {
        $results = new Results($uuid);
        
        $occurrences = $this->db->fetchAllAssociative('
            SELECT * FROM '.$this->db->quoteIdentifier($table).' 
             WHERE '.$this->db->quoteIdentifier($field). ' = ?
                OR '.$this->db->quoteIdentifier($field). ' = ?',
            [$uuid, StringUtil::uuidToBin($uuid)]
        );

        foreach ($occurrences as $occurrence) {
            $results->addResult(new DatabaseReferenceResult($table, $field, $occurrence[$pk] ?? null));
        }

        return $results;
    }

    private function searchForMultiple(string $table, string $field, string $uuid, string $pk = null): Results
    {
        $results = new Results($uuid);

        $occurrences = $this->db->fetchAllAssociative('
            SELECT * FROM '.$this->db->quoteIdentifier($table).' 
             WHERE '.$this->db->quoteIdentifier($field). ' LIKE ?
                OR '.$this->db->quoteIdentifier($field). ' LIKE ?',
            ['%s:16:"'.$uuid.'";%', '%s:16:"'.StringUtil::uuidToBin($uuid).'";%']
        );

        foreach ($occurrences as $occurrence) {
            $results->addResult(new DatabaseReferenceResult($table, $field, $occurrence[$pk] ?? null));
        }

        return $results;
    }

    private function searchForFolders(string $table, string $field, string $uuid, string $pk = null): Results
    {
        $file = FilesModel::findByUuid($uuid);

        return $this->searchForMultiple($table, $field, StringUtil::binToUuid($file->pid), $pk);
    }
}
