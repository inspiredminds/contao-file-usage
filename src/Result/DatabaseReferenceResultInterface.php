<?php

namespace InspiredMinds\ContaoFileUsage\Result;

interface DatabaseReferenceResultInterface extends ResultInterface
{
    public function getTable(): string;

    public function getField(): string;
    
    public function getId();
}
