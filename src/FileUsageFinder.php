<?php

namespace InspiredMinds\ContaoFileUsage;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Validator;
use InspiredMinds\ContaoFileUsage\Provider\FileUsageProviderInterface;
use InspiredMinds\ContaoFileUsage\Result\FileUsageResultsCollection;

class FileUsageFinder implements FileUsageFinderInterface
{
    private $framework;
    private $provider;

    /** 
     * @param FileUsageProviderInterface[] $provider
     */
    public function __construct(ContaoFramework $framework, iterable $provider)
    {
        $this->framework = $framework;
        $this->provider = $provider;
    }

    public function find($uuids): FileUsageResultsCollection
    {
        if (!\is_array($uuids)) {
            $uuids = [$uuids];
        }

        $collection = new FileUsageResultsCollection();

        $this->framework->initialize();

        foreach ($uuids as $uuid) {
            if (!Validator::isStringUuid($uuid)) {
                throw new \InvalidArgumentException('"'.$uuid.'" is not a valid UUID.');
            }

            foreach ($this->provider as $provider) {
                $collection->addResults($uuid, $provider->find($uuid));
            }
        }

        return $collection;
    }
}
