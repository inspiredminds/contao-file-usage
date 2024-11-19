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
    private string|null $editUrl = null;

    private string|null $module = null;

    private string|null $title = null;

    private string|null $parentTitle = null;

    private string|null $parentEditUrl = null;

    /**
     * @param string      $table The database table
     * @param string      $field The database field
     * @param mixed       $id    The ID of the primary key of the database record
     * @param string|null $pk    The primary key of the database table
     */
    public function __construct(
        private readonly string $table,
        private readonly string $field,
        private readonly mixed $id = null,
        private readonly string|null $pk = null,
    ) {
    }

    public function getTemplate(): string
    {
        return '@ContaoFileUsage/result/database_result.html.twig';
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

    public function getPk(): string|null
    {
        return $this->pk;
    }

    public function setEditUrl(string $editUrl): self
    {
        $this->editUrl = $editUrl;

        return $this;
    }

    public function getEditUrl(): string|null
    {
        return $this->editUrl;
    }

    public function setModule(string $module): self
    {
        $this->module = $module;

        return $this;
    }

    public function getModule(): string|null
    {
        return $this->module;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string|null
    {
        return $this->title;
    }

    public function setParentTitle(string $parentTitle): self
    {
        $this->parentTitle = $parentTitle;

        return $this;
    }

    public function getParentTitle(): string|null
    {
        return $this->parentTitle;
    }

    public function setParentEditUrl(string $parentEditUrl): self
    {
        $this->parentEditUrl = $parentEditUrl;

        return $this;
    }

    public function getParentEditUrl(): string|null
    {
        return $this->parentEditUrl;
    }
}
