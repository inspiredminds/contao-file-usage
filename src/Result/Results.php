<?php

namespace InspiredMinds\ContaoFileUsage\Result;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

class Results implements IteratorAggregate, Countable
{
    private string $uuid;

    /** 
     * @var list<ResultInterface>
     */
    private $results = [];

    public function __construct(string $uuid)
    {
        $this->uuid = $uuid;
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

    public function addResults(Results $results): self
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
     * @return Traversable<ResultInterface>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }

    public function count(): int
    {
        return count($this->results);
    }
}
