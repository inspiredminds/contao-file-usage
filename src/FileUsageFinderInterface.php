<?php

namespace InspiredMinds\ContaoFileUsage;

use InspiredMinds\ContaoFileUsage\Result\Results;
use InspiredMinds\ContaoFileUsage\Result\ResultsCollection;

interface FileUsageFinderInterface
{
    /**
     * @param string|list<string>
     */
    public function find($uuid): ResultsCollection;

    public function findAll(): ResultsCollection;
}
