<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Finder;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use InspiredMinds\ContaoFileUsage\Provider\FileUsageProviderInterface;
use InspiredMinds\ContaoFileUsage\Result\Results;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;
use Symfony\Component\Cache\Adapter\AdapterInterface;

class FileUsageFinder implements FileUsageFinderInterface
{
    /**
     * @param iterable<FileUsageProviderInterface> $provider
     */
    public function __construct(
        private readonly AdapterInterface $cache,
        private readonly iterable $provider,
        private readonly Connection $db,
    ) {
    }

    public function find(): ResultsCollection
    {
        $collection = new ResultsCollection();

        // Go through each provider and merge their collections
        foreach ($this->provider as $provider) {
            $collection->mergeCollection($provider->find());
        }

        $this->cache->clear();

        foreach ($collection as $results) {
            $item = $this->cache->getItem($results->getUuid());
            $item->set($results);
            $this->cache->save($item);
        }

        // Fill the cache with empty results for the files in the database
        $files = $this->db->fetchAllAssociative("SELECT uuid FROM tl_files WHERE type = 'file'");

        foreach ($files as $file) {
            $uuid = StringUtil::binToUuid($file['uuid']);
            $item = $this->cache->getItem($uuid);

            if (!$item->isHit()) {
                $item->set(new Results($uuid));
                $this->cache->save($item);
            }
        }

        return $collection;
    }
}
