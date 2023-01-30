<?php

namespace InspiredMinds\ContaoFileUsage\Result;

interface FileUsageDatabaseResultInterface extends FileUsageResultInterface
{
    public function getTable(): string;

    public function getField(): string;
    
    public function getId();
}
