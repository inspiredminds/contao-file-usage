<?php

namespace InspiredMinds\ContaoFileUsage\Result;

use ArrayAccess;
use ArrayIterator;
use InspiredMinds\ContaoFileUsage\Result\FileUsageResults;
use IteratorAggregate;
use Traversable;

/** 
 * Stores FileUsageResults per UUID.
 */
class FileUsageResultsCollection implements IteratorAggregate, ArrayAccess
{
    /** 
     * @var array<string, FileUsageResults>
     */
    private $results = [];

    public function addResults(string $uuid, FileUsageResults $results): self
    {
        if (!$results->hasResults()) {
            return $this;
        }

        if (!isset($this->results[$uuid])) {
            $this->results[$uuid] = new FileUsageResults();
        }

        $this->results[$uuid]->addResults($results);

        return $this;
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
