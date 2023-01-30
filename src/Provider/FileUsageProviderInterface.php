<?php

namespace InspiredMinds\ContaoFileUsage\Provider;

use InspiredMinds\ContaoFileUsage\Result\FileUsageResults;

interface FileUsageProviderInterface
{
    public function find(string $uuid): FileUsageResults;
}
