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

class DatabaseReferenceResult implements ResultInterface
{
    private string $table;
    private string $field;
    private $id;
    private ?string $pk = null;
    private ?string $editUrl = null;
    private ?string $module = null;
    private ?string $title = null;
    private ?string $parentTitle = null;
    private ?string $parentEditUrl = null;

    /**
     * @param string      $table The database table
     * @param string      $field The database field
     * @param mixed       $id    The ID of the primary key of the database record
     * @param string|null $pk    The primary key of the database table
     */
    public function __construct(string $table, string $field, $id = null, string $pk = null)
    {
        $this->table = $table;
        $this->field = $field;
        $this->id = $id;
        $this->pk = $pk;
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

    public function getPk(): ?string
    {
        return $this->pk;
    }

    public function setEditUrl(string $editUrl): self
    {
        $this->editUrl = $editUrl;

        return $this;
    }

    public function getEditUrl(): ?string
    {
        return $this->editUrl;
    }

    public function setModule(string $module): self
    {
        $this->module = $module;

        return $this;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setParentTitle(string $parentTitle): self
    {
        $this->parentTitle = $parentTitle;

        return $this;
    }

    public function getParentTitle(): ?string
    {
        return $this->parentTitle;
    }

    public function setParentEditUrl(string $parentEditUrl): self
    {
        $this->parentEditUrl = $parentEditUrl;

        return $this;
    }

    public function getParentEditUrl(): ?string
    {
        return $this->parentEditUrl;
    }
}
