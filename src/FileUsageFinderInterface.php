<?php

namespace InspiredMinds\ContaoFileUsage;

use InspiredMinds\ContaoFileUsage\Result\FileUsageResultsCollection;

interface FileUsageFinderInterface
{
    /** 
     * @param list<string>|string A single UUID or list of uuids to find the usages for.
     * @return FileUsageResultsCollection
     */
    public function find($uuids): FileUsageResultsCollection;
}
