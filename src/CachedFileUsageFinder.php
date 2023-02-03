<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\Validator;
use InspiredMinds\ContaoFileUsage\Result\Results;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;
use Symfony\Component\Cache\Adapter\AdapterInterface;

class CachedFileUsageFinder implements FileUsageFinderInterface
{
    private $framework;
    private $fileUsagefinder;
    private $cache;

    public function __construct(ContaoFramework $framework, FileUsageFinder $fileUsagefinder, AdapterInterface $cache)
    {
        $this->framework = $framework;
        $this->fileUsagefinder = $fileUsagefinder;
        $this->cache = $cache;
    }

    public function find(string $uuid): Results
    {
        $this->framework->initialize();

        if (!Validator::isStringUuid($uuid)) {
            throw new \InvalidArgumentException('"'.$uuid.'" is not a valid UUID.');
        }

        $cacheItem = $this->cache->getItem($uuid);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $results = $this->fileUsagefinder->find($uuid);
        $cacheItem->set($results);
        $this->cache->save($cacheItem);

        return $results;
    }

    public function findAll(): ResultsCollection
    {
        $collection = new ResultsCollection();

        $this->framework->initialize();

        foreach (FilesModel::findByType('file') ?? [] as $file) {
            $uuid = StringUtil::binToUuid($file->uuid);
            $collection->addResults($uuid, $this->find(StringUtil::binToUuid($file->uuid)));
        }

        return $collection;
    }
}
