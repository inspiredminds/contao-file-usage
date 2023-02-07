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
    private string $pk;

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
}
