<?php

namespace InspiredMinds\ContaoFileUsage\Result;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Traversable;

class FileUsageResults implements IteratorAggregate, ArrayAccess
{
    /** 
     * @var list<FileUsageResultInterface>
     */
    private $results = [];

    /** 
     * @return list<FileUsageResultInterface>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    public function hasResults(): bool
    {
        return [] !== $this->results;
    }

    public function addResult(FileUsageResultInterface $result): self
    {
        $this->results[] = $result;

        return $this;
    }

    public function addResults(FileUsageResults $results): self
    {
        /** @var FileUsageResultInterface $result */
        foreach ($results as $result) {
            if (!$this->exists($result)) {
                $this->results[] = $result;
            }
        }

        return $this;
    }

    public function exists(FileUsageResultInterface $result): bool
    {
        foreach ($this->results as $existingResult) {
            if ($existingResult->isEqual($result)) {
                return true;
            }
        }

        return false;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->results[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->results[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->results[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->results[$offset]);
    }
}
