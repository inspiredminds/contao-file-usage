<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Finder;

use InspiredMinds\ContaoFileUsage\Provider\FileUsageProviderInterface;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;
use Symfony\Component\Cache\Adapter\AdapterInterface;

class FileUsageFinder implements FileUsageFinderInterface
{
    private $cache;
    private $provider;

    /**
     * @param FileUsageProviderInterface[] $provider
     */
    public function __construct(AdapterInterface $cache, iterable $provider)
    {
        $this->cache = $cache;
        $this->provider = $provider;
    }

    public function find(): ResultsCollection
    {
        $collection = new ResultsCollection();

        foreach ($this->provider as $provider) {
            $collection->mergeCollection($provider->find());
        }

        $this->cache->clear();

        foreach ($collection as $results) {
            $item = $this->cache->getItem($results->getUuid());
            $item->set($results);
            $this->cache->save($item);
        }

        return $collection;
    }
}
