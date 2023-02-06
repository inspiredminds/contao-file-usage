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
use InspiredMinds\ContaoFileUsage\Provider\FileUsageProviderInterface;
use InspiredMinds\ContaoFileUsage\Result\Results;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;
use Symfony\Component\Cache\Adapter\AdapterInterface;

class FileUsageFinder implements FileUsageFinderInterface
{
    private $framework;
    private $cache;
    private $provider;

    /**
     * @param FileUsageProviderInterface[] $provider
     */
    public function __construct(ContaoFramework $framework, AdapterInterface $cache, iterable $provider)
    {
        $this->framework = $framework;
        $this->cache = $cache;
        $this->provider = $provider;
    }

    public function find(string $uuid, bool $useCache = true): Results
    {
        $results = new Results($uuid);

        $this->framework->initialize();

        if (!Validator::isStringUuid($uuid)) {
            throw new \InvalidArgumentException('"'.$uuid.'" is not a valid UUID.');
        }

        $cacheItem = $this->cache->getItem($uuid);

        if ($useCache && $cacheItem->isHit()) {
            return $cacheItem->get();
        }

        foreach ($this->provider as $provider) {
            $results->addResults($provider->find($uuid));
        }

        $cacheItem->set($results);
        $this->cache->save($cacheItem);

        return $results;
    }

    public function findAll(bool $useCache = true): ResultsCollection
    {
        $collection = new ResultsCollection();

        $this->framework->initialize();

        foreach (FilesModel::findByType('file') ?? [] as $file) {
            $uuid = StringUtil::binToUuid($file->uuid);
            $collection->addResults($uuid, $this->find(StringUtil::binToUuid($file->uuid), $useCache));
        }

        return $collection;
    }
}
