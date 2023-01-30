<?php

namespace InspiredMinds\ContaoFileUsage\Provider;

use InspiredMinds\ContaoFileUsage\Result\Results;

interface FileUsageProviderInterface
{
    public function find(string $uuid): Results;
}
