<?php
namespace Database;

use Database\Tablecolumnschema;
use Database\IDschema;
use Composer\Composer;

include_once 'tablecolumnschema.php';
include_once 'idschema.php';

final class Primarykeyschema extends Tablecolumnschema
{
    private string|null $value;

    /**
     * Constructor for the PrimaryKeySchema class.
     *
     * Initializes the primary key schema by extracting relevant information
     * from the provided TableColumnSchema instance.
     *
     * @param TableColumnSchema $from Reference to a TableColumnSchema object
     *                                from which the table schema, column name,
     *                                column type, default value, nullable status,
     *                                and extra information are retrieved.
     */
    public function __construct(Tablecolumnschema &$from)
    {
        $this->tableSchema = $from->GetTableSchema();
        $this->columnName = $from->GetColumnName();
        $this->columnType = $from->GetColumnType();
        $this->columnDefault = $from->GetColumnDefault();
        $this->isNullable = $from->IsNullable();
        $this->extra = $from->GetExtra();
        $this->characterMaximumLength = $from->GetCharacterMaximumLength();
        $this->numericPrecision = $from->GetNumericPrecision();
        $this->datatype = $from->GetDatatype();
        $this->value = null;
    }

    /**
     * Sets the primary key value for the current schema.
     *
     * @param string|null $value The value to set as the primary key. Can be a string or null.
     * 
     * @return bool Returns true if the primary key value is successfully set.
     * 
     * @throws \Exception Throws an exception if the provided ID value is not valid 
     *                    for the primary key column of the table.
     */
    public function SetValue(string|null $value): bool
    {
        if($this->ValidateAssignValue($value))
        {
            $this->value = $value;
            return true;
        }

        Composer::Throw("The provided ID value is not valid for the primary key column, for the table " . $this->tableSchema->GetTableName() . ".");
        return false;
    }

    
    /**
     * Validates if the provided ID is appropriate for assignment based on the primary key schema type.
     *
     * This method checks the primary key configuration and validates the given ID according
     * to the specific type (auto-increment, numeric, or UUID). It ensures that the ID conforms
     * to the constraints defined by the schema before assignment.
     *
     * @param string|null $id The ID value to validate for assignment
     *
     * @return bool True if the ID is valid for assignment according to the schema type,
     *              false otherwise. Returns true by default if no specific validation is required.
     *
     * @see IDschema::ValidNumericAutoIncrementID()
     * @see IDschema::ValidNumericID()
     * @see IDschema::ValidUUID()
     */
    public function ValidateAssignValue(string|null $id): bool
    {
        if($this->IsAutoIncremental())
        {
            return IDschema::ValidNumericAutoIncrementID($id);
        }
        elseif ($this->IsNumeric())
        {
            return IDschema::ValidNumericID($id);
        } 
        elseif ($this->IsUUID())
        {
            return $id === "DEFAULT" || IDschema::ValidUUID($id);
        }
        
        return true; // Default to true if no specific validation is required.
    }


    /**
    *
  ________        __    __                       
 /  _____/  _____/  |__/  |_  ___________  ______
/   \  ____/ __ \   __\   __\/ __ \_  __ \/  ___/
\    \_\  \  ___/|  |  |  | \  ___/|  | \/\___ \ 
 \______  /\___  >__|  |__|  \___  >__|  /____  >
        \/     \/                \/           \/ 
     */
    
    /**
     * Retrieves the value of the primary key.
     *
     * @return string|null The value of the primary key, or null if not set.
     */
    public function GetValue(): string|null
    {
        return $this->value;
    }

    /**
     * Checks if the primary key datatype is numeric.
     *
     * This method retrieves the primary key datatype and checks if it is an integer type.
     *
     * @return bool Returns true if the primary key datatype is 'int' or contains 'int', otherwise false.
     */
    public function IsNumeric(): bool
    {
        $datatype = $this->GetDatatype();
        return $datatype == 'int' || strpos($datatype, 'int') !== false;
    }

    /**
     * Checks if the primary key is using UUID.
     *
     * This method determines if the primary key of the database table is of type 'char'
     * and has a default value of 'uuid()'.
     *
     * @return bool Returns true if the primary key is using UUID, false otherwise.
     */
    public function IsUUID(): bool
    {
        return strpos($this->GetDatatype(), 'char') !== false && $this->GetColumnDefault() == 'uuid()';
    }

    /**
     * Checks if the primary key is using a short UUID.
     *
     * This method determines if the primary key column is using a short UUID
     * by verifying if the primary key datatype is numeric and if the default
     * value of the primary key column is 'uuid_short()'.
     *
     * @return bool True if the primary key is using a short UUID, false otherwise.
     */
    public function IsUsingShortUUID(): bool
    {
        return $this->IsNumeric() && $this->GetColumnDefault() == 'uuid_short()';
    }

    /**
     * Checks if the primary key is using auto-increment.
     *
     * This method determines if the primary key of the table is set to auto-increment.
     * It does this by checking if the 'auto_increment' keyword is present in the primary key's extra attributes
     * and if the primary key is numeric.
     *
     * @return bool Returns true if the primary key is using auto-increment, false otherwise.
     */
    public function IsAutoIncremental(): bool
    {
        $extra = $this->GetExtra();
        return strpos($extra, 'auto_increment') !== false && $this->IsNumeric();
    }

    /**
     * Retrieve the last ID on the table.
     *
     * This method retrieves the maximum ID value from the primary key column of the table.
     *
     * @return int The last ID on the table.
     * @throws \Exception If the primary key column is not numeric.
     */
    public function LastPrimaryKeyNumericValueOnTable(): int
    {
        if(!$this->IsNumeric())
        {
            Composer::Throw("Cannot retrieve the last primary key value from the table because the primary key column is not numeric.");
            return -1; // Return -1 to indicate an error condition.
        }

        $pdo = $this->tableSchema->GetDatabase()->connect();
    
        $query = "SELECT MAX(" . $this->GetColumnName() . ") AS max_id FROM " . $this->tableSchema->GetTableName();
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $maxID = $result['max_id'];
    
        return (int)$maxID;
    }
}
