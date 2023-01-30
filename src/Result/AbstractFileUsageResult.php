<?php

namespace InspiredMinds\ContaoFileUsage\Result;

abstract class AbstractFileUsageResult implements FileUsageResultInterface
{
    private string $editUrl;

    public function setEditUrl(string $editUrl): self
    {
        $this->setEditUrl($editUrl);

        return $this;
    }

    public function getEditUrl(): ?string
    {
        return $this->editUrl;
    }

    abstract public function __toString(): string;
}
