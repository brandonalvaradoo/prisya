<?php
namespace Database\Attributes;

use Attribute;
use Attributes\IHandler;
use Attributes\Invocation;
use Attributes\ExcludeFromFullProcess;

#[Attribute(Attribute::TARGET_PROPERTY)]
#[ExcludeFromFullProcess]
class Foreign implements IHandler
{
    private string $referencedTable;
    private string $referencedColumn;

    public function __construct(string $referencedTable, string $referencedColumn)
    {
        $this->referencedTable = $referencedTable;
        $this->referencedColumn = $referencedColumn;
    }

    public function handle(Invocation $invocation)
    {
        return $invocation->proceed();
    }


    public function GetReferencedTable() : string
    {
        return $this->referencedTable;
    }

    public function GetReferencedColumn() : string
    {
        return $this->referencedColumn;
    }
}