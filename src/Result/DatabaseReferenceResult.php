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

    public function __construct(string $table, string $field, $id = null)
    {
        $this->table = $table;
        $this->field = $field;
        $this->id = $id;
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
}
