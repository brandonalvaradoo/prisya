<?php
namespace Database;

class ForeignValue
{
    private string $foreignTable;
    private string $foreignColumn;
    private string $localColumn;
    private Datamodel $relatedModel;

    public function __construct(string $foreign_table, string $foreign_column, string $local_column, Datamodel &$related_model)
    {
        $this->foreignTable = $foreign_table;
        $this->foreignColumn = $foreign_column;
        $this->localColumn = $local_column;
        $this->relatedModel = &$related_model;
    }
}