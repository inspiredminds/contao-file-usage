<?php

namespace InspiredMinds\ContaoFileUsage\Result;

class FileUsageDatabaseResult extends AbstractFileUsageResult implements FileUsageDatabaseResultInterface
{
    private string $table;
    private string $field;
    private $id;

    public function __construct(string $table, string $field, $id = null)
    {
        $this->table = $table;
        $this->field = $field;
        $this->id = $id;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getId()
    {
        return $this->id;
    }

    public function isEqual(FileUsageResultInterface $result): bool
    {
        if (!$result instanceof FileUsageDatabaseResultInterface) {
            return false;
        }

        return 
            $result->getTable() === $this->table &&
            $result->getField() === $this->field &&
            $result->getId() === $this->id
        ;
    }

    public function __toString(): string
    {
        return $this->table.'.'.$this->field.' (ID '.$this->id.')';
    }
}
