<?php
namespace Database;

use Database\Tableschema;
require_once "tableschema.php";

/**
 * Class Tablecolumnschema
 *
 * This class represents the schema of a table column in the database.
 * It is used to define the structure and properties of a column within a table.
 *
 * @package database
 */
class Tablecolumnschema
{
    protected Tableschema $tableSchema;
    protected string $columnName;
    protected int $ordinalPosition;
    protected ?string $datatype;
    protected ?string $columnDefault;
    protected ?string $characterMaximumLength;
    protected ?string $numericPrecision;
    protected ?string $extra;
    protected ?string $columnType;
    protected ?string $columnKey;
    protected ?string $isNullable;

    /**
     * Constructs a new Tablecolumnschema object.
     *
     * @param Tableschema $tableSchema The schema of the table to which the column belongs.
     * @param string $columnName The name of the column.
     */
    public function __construct(Tableschema &$tableSchema, string $columnName)
    {
        $this->tableSchema = $tableSchema;
        $this->columnName = $columnName;
        $this->ResolveTablecolumnschema();
    }

    /**
     * Resolves the schema of the table column and assigns the schema properties to the class properties.
     *
     * This method retrieves the column schema by calling the GetColumnShemaSelected method with a specific set of column attributes.
     * It then assigns the retrieved schema values to the corresponding class properties.
     *
     * @return void
     */
    public function ResolveTablecolumnschema() : void
    {
        $columnSchema = $this->GetColumnShemaSelected('ORDINAL_POSITION, DATA_TYPE, COLUMN_DEFAULT, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, EXTRA, COLUMN_TYPE, COLUMN_KEY, IS_NULLABLE');

        $this->ordinalPosition = $columnSchema['ORDINAL_POSITION'];
        $this->datatype = $columnSchema['DATA_TYPE'];
        $this->columnDefault = $columnSchema['COLUMN_DEFAULT'];
        $this->characterMaximumLength = $columnSchema['CHARACTER_MAXIMUM_LENGTH'];
        $this->numericPrecision = $columnSchema['NUMERIC_PRECISION'];
        $this->extra = $columnSchema['EXTRA'];
        $this->columnType = $columnSchema['COLUMN_TYPE'];
        $this->columnKey = $columnSchema['COLUMN_KEY'];
        $this->isNullable = $columnSchema['IS_NULLABLE'];
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
     * Retrieves the schema information of this column from the database.
     *
     * @param string $select The columns to select from the INFORMATION_SCHEMA.COLUMNS table. Defaults to '*'.
     * @return array The schema information of the selected column.
     */
    public function GetColumnShemaSelected(string $select = '*') : array
    {        
        $pdo = $this->tableSchema->GetDatabase()->connect();

        $stmt = $pdo->prepare(
            "SELECT $select
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = :table
            AND TABLE_SCHEMA = :schema
            AND COLUMN_NAME = :column"
        );

        $stmt->execute([
            'table' => $this->tableSchema->GetTableName(),
            'schema' => $this->tableSchema->GetDatabase()->GetDatabaseName(),
            'column' => $this->columnName
        ]);

        $columnSchema = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $columnSchema;
    }

    /**
     * Get the table schema of the column.
     *
     * @return Tableschema The table schema of the column.
     */
    public function GetTableSchema(): Tableschema
    {
        return $this->tableSchema;
    }
    
    /**
     * Get the name of the column.
     *
     * @return string The name of the column.
     */
    public function GetColumnName(): string
    {
        return $this->columnName;
    }

    /**
     * Get the ordinal position of the column.
     *
     * @return int The ordinal position of the column.
     */
    public function GetOrdinalPosition(): int
    {
        return $this->ordinalPosition;
    }

    /**
     * Get the data type of the table column.
     *
     * @return string The data type of the table column.
     */
    public function GetDatatype(): string
    {
        return $this->datatype;
    }

    /**
     * Get the default value of the column.
     *
     * @return string|null The default value of the column, or null if there is no default value.
     */
    public function GetColumnDefault(): string|null
    {
        return $this->columnDefault;
    }

    /**
     * Get the maximum length of characters allowed for the column.
     *
     * @return string|null The maximum length of characters, or null if the column is not a character type.
     */
    public function GetCharacterMaximumLength(): string|null
    {
        return $this->characterMaximumLength;
    }

    /**
     * Get the numeric precision of the column.
     *
     * @return string|null The numeric precision of the column, or null if the column is not a numeric type.
     */
    public function GetNumericPrecision(): string|null
    {
        return $this->numericPrecision;
    }

    /**
     * Get the extra information of the table column.
     *
     * @return string|null The extra information, or null if there is no extra information.
     */
    public function GetExtra(): string|null
    {
        return $this->extra;
    }

    /**
     * Get the type of the column.
     *
     * @return string The type of the column.
     */
    public function GetColumnType(): string
    {
        return $this->columnType;
    }

    /**
     * Check if the table column is nullable.
     *
     * @return string Returns 'YES' if the column is nullable, 'NO' otherwise.
     */
    public function IsNullable(): string
    {
        return $this->isNullable;
    }

    /**
     * Determines if the data type of the column is an integer type.
     *
     * @return bool Returns true if the column's data type is 'int' or contains 'int', otherwise false.
     */
    public function IsInteger(): bool
    {
        return $this->datatype === 'int' || strpos($this->datatype, 'int') !== false;
    }

    /**
     * Determines if the data type of the column is a decimal type.
     *
     * This method checks if the column's data type is one of the following:
     * - 'decimal'
     * - 'float'
     * - 'double'
     *
     * @return bool Returns true if the data type is a decimal type, otherwise false.
     */
    public function IsDecimal(): bool
    {
        return $this->datatype === 'decimal' || $this->datatype === 'float' || $this->datatype === 'double';
    }

    /**
     * Determines if the data type of the column is a string type.
     *
     * @return bool Returns true if the column's data type is 'varchar' or contains 'char', otherwise false.
     */
    public function IsString(): bool
    {
        return strpos($this->datatype, 'char') || strpos($this->datatype, 'text') !== false;
    }

    /**
     * Determines if the data type of the column is a date type.
     *
     * @return bool Returns true if the column's data type is 'date' or contains 'time', otherwise false.
     */
    public function IsDate(): bool
    {
        return strpos($this->datatype, 'date') || strpos($this->datatype, 'time') !== false || strpos($this->datatype, 'year') !== false;
    }
}