<?php

use Database\Tableschema;

class RelationPair
{
    private Tableschema $tableSchema;
    private string $relationColumnName;

    public function __construct(Tableschema $tableSchema, string $relationColumnName)
    {
        $this->tableSchema = $tableSchema;
        $this->relationColumnName = $relationColumnName;
    }
}