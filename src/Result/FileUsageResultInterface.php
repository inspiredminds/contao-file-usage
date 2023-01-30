<?php

namespace InspiredMinds\ContaoFileUsage\Result;

interface FileUsageResultInterface
{
    /**
     * Sets an optional edit URL for the back end for this file usage result.
     */
    public function setEditUrl(string $editUrl): self;

    /** 
     * Returns the optional edit URL for the back end for this file usage result.
     */
    public function getEditUrl(): ?string;

    public function isEqual(FileUsageResultInterface $result): bool;

    public function __toString(): string;
}
