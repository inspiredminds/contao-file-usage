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

class Results implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * @var list<ResultInterface>
     */
    private array $results = [];

    public function __construct(private readonly string $uuid)
    {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @return list<ResultInterface>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    public function hasResults(): bool
    {
        return [] !== $this->results;
    }

    public function addResult(ResultInterface $result): self
    {
        if (!$this->exists($result)) {
            $this->results[] = $result;
        }

        return $this;
    }

    public function addResults(self $results): self
    {
        foreach ($results as $result) {
            $this->addResult($result);
        }

        return $this;
    }

    public function exists(ResultInterface $result): bool
    {
        foreach ($this->results as $existingResult) {
            if ($existingResult === $result) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \Traversable<ResultInterface>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->results);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->results[$offset]);
    }

    public function offsetGet($offset): ResultInterface|null
    {
        return $this->results[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if (!$value instanceof ResultInterface) {
            throw new \InvalidArgumentException('Value is not a ResultInterface instance.');
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
