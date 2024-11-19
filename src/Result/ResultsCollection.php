<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Result;

/**
 * Stores Results per UUID.
 */
class ResultsCollection implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * @var array<string, Results>
     */
    private array $results = [];

    public function mergeCollection(self $collection): self
    {
        foreach ($collection as $uuid => $results) {
            $this->addResults($uuid, $results);
        }

        return $this;
    }

    public function addResults(string $uuid, Results $results): self
    {
        if ($results->hasResults()) {
            if (!isset($this->results[$uuid])) {
                $this->results[$uuid] = new Results($uuid);
            }

            $this->results[$uuid]->addResults($results);
        }

        return $this;
    }

    public function hasResults(): bool
    {
        return $this->count() > 0;
    }

    public function addResult(string $uuid, ResultInterface $result): self
    {
        if (!isset($this->results[$uuid])) {
            $this->results[$uuid] = new Results($uuid);
        }

        $this->results[$uuid]->addResult($result);

        return $this;
    }

    /**
     * @return \Traversable<string, Results>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->results);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->results[$offset]);
    }

    public function offsetGet($offset): Results|null
    {
        return $this->results[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if (!$value instanceof Results) {
            throw new \InvalidArgumentException('Value is not a Results instance.');
        }

        if (!$value->hasResults()) {
            return;
        }

        $this->results[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->results[$offset]);
    }

    public function count(): int
    {
        return \count($this->results);
    }
}
