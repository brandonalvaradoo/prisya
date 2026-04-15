<?php
namespace Database;

use Composer\Composer;
use Database\Database;

enum Conditions
{
    case EQUALS;
    case DIFFERENT;
    case GREATER;
    case LESS;
    case GREATER_OR_EQUALS;
    case LESS_OR_EQUALS;
    case BETWEEN;
    case NOT_BETWEEN;
    case LIKE;
    case NOT_LIKE;
    case IN;
    case NOT_IN;
}

class Datafetch
{
    /**
     * @var Tableschema $tableSchema The table schema to fetch data from.
     */
    protected Tableschema $tableSchema;
    protected Datamodel $datamodel;

    protected array $selectRows;
    protected array $fetchConditions;
    protected array $bringForeignProperties;
    protected array $queryModifiers;
    protected array $workingColumns;

    public function __construct(Tableschema &$tableSchema, Datamodel &$datamodel)
    {
        $this->tableSchema = &$tableSchema;
        $this->datamodel = &$datamodel;

        $this->selectRows = [];
        $this->fetchConditions = [];
        $this->bringForeignProperties = [];
        $this->queryModifiers = [];
        $this->workingColumns = array_merge($this->tableSchema->GetIDColumnNames(), $this->datamodel->GetWorkingProperties());
    }




/*
 _____                                             _ _  __ _               
|  _  |                                           | (_)/ _(_)              
| | | |_   _  ___ _ __ _   _   _ __ ___   ___   __| |_| |_ _  ___ _ __ ___ 
| | | | | | |/ _ \ '__| | | | | '_ ` _ \ / _ \ / _` | |  _| |/ _ \ '__/ __|
\ \/' / |_| |  __/ |  | |_| | | | | | | | (_) | (_| | | | | |  __/ |  \__ \
 \_/\_\\__,_|\___|_|   \__, | |_| |_| |_|\___/ \__,_|_|_| |_|\___|_|  |___/
                        __/ |                                              
                       |___/                                               
 */
    public function SetQueryModifier(string $modifier) : void
    {
        // Restriction 1. The query modifier must be a valid SQL clause (e.g., GROUP BY, ORDER BY, HAVING) followed by appropriate syntax.
        $validModifiers = [
            'GROUP BY',
            'ORDER BY',
            'HAVING',
            'LIMIT',
            'OFFSET',
        ];

        //Modifier must start with one of the valid modifiers
        if(!preg_match('/^(' . implode('|', $validModifiers) . ')\b/i', $modifier))
        {
            Composer::Throw("Invalid query modifier: $modifier. The modifier must start with a valid SQL clause such as GROUP BY, ORDER BY, HAVING, LIMIT, or OFFSET.");
        }

        $this->queryModifiers[] = $modifier;
    }

    public function GetQueryModifiers() : string
    {
        if(empty($this->queryModifiers))
        {
            return "";
        }

        return implode(" ", $this->queryModifiers);
    }

    public function ClearQueryModifiers() : void
    {
        $this->queryModifiers = [];
    }

    public function SetCondition(string $property, Conditions $condition, ...$value) : void
    {
        $operator = match ($condition) {
            Conditions::EQUALS => '=',
            Conditions::DIFFERENT => '!=',
            Conditions::GREATER => '>',
            Conditions::LESS => '<',
            Conditions::GREATER_OR_EQUALS => '>=',
            Conditions::LESS_OR_EQUALS => '<=',
            Conditions::BETWEEN => 'BETWEEN',
            Conditions::NOT_BETWEEN => 'NOT BETWEEN',
            Conditions::LIKE => 'LIKE',
            Conditions::NOT_LIKE => 'NOT LIKE',
            Conditions::IN => 'IN',
            Conditions::NOT_IN => 'NOT IN',
        };

        $conditionString = "";

        if(in_array($condition, [Conditions::BETWEEN, Conditions::NOT_BETWEEN]))
        {
            if(!is_array($value) || count($value) !== 2)
            {
                Composer::Throw("The value for BETWEEN and NOT BETWEEN conditions must be an array with exactly two elements representing the range.");
            }

            $conditionString = "$property $operator '$value[0]' AND '$value[1]'";
        }
        else if(in_array($condition, [Conditions::IN, Conditions::NOT_IN]))
        {
            if(!is_array($value) || count($value) === 0)
            {
                Composer::Throw("The value for IN and NOT IN conditions must be a non-empty array.");
            }

            $placeholders = implode(", ", array_fill(0, count($value), "'$value[0]'"));
            $conditionString = "$property $operator ($placeholders)";
        }
        else
        {
            $conditionString = "$property $operator '$value[0]'";
        }

        $this->fetchConditions[] = $conditionString;
    }

    public function GetConditionsQuery() : string
    {
        if(empty($this->fetchConditions))
        {
            return "";
        }

        $conditions = implode(" AND ", $this->fetchConditions);
        return " WHERE $conditions";
    }

    public function ClearConditions() : void
    {
        $this->fetchConditions = [];
    }

    public function Select(string ...$rows) : void
    {
        foreach($rows as $row)
        {
            // Restriction 1. The row must exist in the table schema
            if(!$this->tableSchema->ColumnExists($row))
            {
                //self::Throw("Column $row does not exist in the table " . $this->tableSchema->GetTableName());
            }

            // Restriction 2. Query (a column name in this case)
            // can only contain letters, numbers, underscores.
            // Also permit SQL functions and aliases with dot notation (e.g., "COUNT(column_name) AS count").
            if (!preg_match('/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)?(\s+AS\s+[a-zA-Z0-9_]+)?$/i', $row) && !preg_match('/^[a-zA-Z0-9_]+\s*\(.*\)(\s+AS\s+[a-zA-Z0-9_]+)?$/i', $row))
            {
                Composer::Throw("Column name $row contains invalid characters. Only letters, numbers and underscores are allowed to prevent SQL injection.");
            }

            // Restriction 3. Cannot select the same column more than once
            if(in_array($row, $this->selectRows))
            {
                Composer::Throw("Column $row is selected multiple times.");
            }

            // Restriction 4. Cannot select columns from the ID schema
            if(in_array($row, $this->tableSchema->GetIDColumnNames()))
            {
                //self::Throw("Column $row is part of the ID schema and cannot be selected explicitly. The ID columns are always included in the selection.");
            }
        }

        $this->selectRows += $rows;
    }

    public function GetSelectionQuery(bool $includeWorkingColumns = true, bool $includeForeign = false) : string
    {
        if($includeForeign && !empty($this->bringForeignProperties))
        {
            $foreignColumns = array_map(fn($foreign) => $foreign['FOREIGN_TABLE'] . "." . $foreign['PROPERTY'] . " AS " . $foreign['ALIAS'], $this->bringForeignProperties);
            $columns = array_map(fn($col) => $this->tableSchema->GetTableName() . "." . "$col AS $col", $includeWorkingColumns ? array_merge($this->workingColumns, $this->selectRows) : $this->selectRows);
        
            return implode(", ", array_merge($columns, $foreignColumns));
        }

        if(empty($this->selectRows))
        {
            return implode(", ", $this->workingColumns);
        }

        $columns = $includeWorkingColumns ? array_merge($this->workingColumns, $this->selectRows) : $this->selectRows;
        return implode(", ", $columns);
    }

    public function ClearSelected() : void
    {
        $this->selectRows = [];
    }

    /**
     * Constructs and returns the SQL "FROM" clause for the current table schema.
     *
     * @return string The "FROM" clause including the table name.
     */
    public function GetFromQuery() : string
    {
        return " FROM " . $this->tableSchema->GetTableName();
    }

    public function BringForeignProperty(string $property, string $foreignTable, string|null $alias=null) : void
    {
        $this->bringForeignProperties[] = [
            'PROPERTY' => $property,
            'FOREIGN_TABLE' => $foreignTable,
            'ALIAS' => $alias ? $alias : "$foreignTable.$property"
        ];
    }

    public function GetInnerJoins() : string
    {
        if(empty($this->bringForeignProperties))
        {
            return "";
        }

        $joins = "";
        foreach($this->bringForeignProperties as $foreign)
        {
            $joins .= " INNER JOIN " . $foreign['FOREIGN_TABLE'] . " ON " . $foreign['ALIAS'] . " = " . $foreign['PROPERTY'];
        }

        return $joins;
    }

    public function SetIDCondition(IDschema|string|array $id)
    {
        $idColumns = $this->tableSchema->GetIDColumnNames();

        // Case 1. Single string ID provided
        if(is_string($id))
        {
            if(count($idColumns) > 1)
            {
                Composer::Throw("The table " . $this->tableSchema->GetTableName() . " has a composite primary key. A single string ID is not sufficient to perform the selection. Please provide an array of key-value pairs or an IDschema object.");
            }

            $primaryKeyColumn = $this->tableSchema->GetIDColumnNames()[0];
            $this->SetCondition($primaryKeyColumn, Conditions::EQUALS, $id);
        }

        $idPairs = ($id instanceof IDschema) ? $id->GetPairs() : (is_array($id) ? $id : [$this->tableSchema->GetIDColumnNames()[0] => $id]);

        foreach($idPairs as $key => $value)
        {
            if(!in_array($key, $idColumns))
            {
                Composer::Throw("Column $key is not part of the ID schema for the table " . $this->tableSchema->GetTableName() . ". Please provide valid key-value pairs that match the ID schema.");
            }

            $this->SetCondition($key, Conditions::EQUALS, $value);
        }
    }

    public function ExecuteSelect(bool $fetchColumn = false) : array|string|int|null
    {
        $query = "SELECT " . $this->GetSelectionQuery(!$fetchColumn) . $this->GetFromQuery() . $this->GetInnerJoins() . $this->GetConditionsQuery() . " " . $this->GetQueryModifiers();
        $db = $this->tableSchema->GetDatabase()->connect();
        $statement = $db->prepare($query);
        $statement->execute();
        $results = $fetchColumn ? $statement->fetchColumn() : $statement->fetchAll(\PDO::FETCH_ASSOC);
        $this->ClearConditions();
        return $results;
    }

    public function ExecuteDelete() : int
    {
        // Restriction 1. At least one condition must be specified before executing a delete operation.
        if(empty($this->fetchConditions))
        {
            Composer::Throw("No conditions specified for deletion. To prevent accidental deletion of all records, please set at least one condition before calling the Delete method.");
            return false;
        }

        $query = "DELETE" . $this->GetFromQuery() . $this->GetConditionsQuery();
        $db = $this->tableSchema->GetDatabase()->connect();
        $statement = $db->prepare($query);
        $statement->execute();
        $this->ClearConditions();
        return $statement->rowCount();
    }

    public function ExecuteUpdate(array $data) : int
    {
        if(empty($this->fetchConditions))
        {
            Composer::Throw("No conditions specified for update. To prevent accidental update of all records, please set at least one condition before calling the Update method.");
            return false;
        }

        $columns = array_keys($data);
        $updatePairs = array_map(fn($col) => "$col = :$col", $columns);
        $query = "UPDATE " . $this->tableSchema->GetTableName() . 
                " SET " . implode(', ', $updatePairs) . ' ' .
                $this->GetConditionsQuery();

        $db = $this->tableSchema->GetDatabase()->connect();
        $statement = $db->prepare($query);
        $statement->execute($data);
        $this->ClearConditions();
        return $statement->rowCount();
    }

    public function ClearQuery() : void
    {
        $this->ClearSelected();
        $this->ClearConditions();
        $this->ClearQueryModifiers();
        $this->bringForeignProperties = [];
    }


    /**
     * 
                                  .__         .__    .___   _____       __         .__     
  ____  __ __  _____   ___________|__| ____   |__| __| _/ _/ ____\_____/  |_  ____ |  |__  
 /    \|  |  \/     \_/ __ \_  __ \  |/ ___\  |  |/ __ |  \   __\/ __ \   __\/ ___\|  |  \ 
|   |  \  |  /  Y Y  \  ___/|  | \/  \  \___  |  / /_/ |   |  | \  ___/|  | \  \___|   Y  \
|___|  /____/|__|_|  /\___  >__|  |__|\___  > |__\____ |   |__|  \___  >__|  \___  >___|  /
     \/            \/     \/              \/          \/             \/          \/     \/ 
     */

    public function ColumnInRange(string $columnName, int $startingValue, int $limit, bool $ascending = true) : array
    {
        if(!$this->tableSchema->ColumnExists($columnName))
        {
            Composer::Throw("Column $columnName does not exist in the table " . $this->tableSchema->GetTableName());
        }

        $operator = $ascending ? Conditions::GREATER_OR_EQUALS : Conditions::LESS_OR_EQUALS;
        $order = $ascending ? "ASC" : "DESC";

        $this->SetCondition($columnName, $operator, $startingValue);
        $this->SetQueryModifier("ORDER BY $columnName $order");
        $this->SetQueryModifier("LIMIT $limit");

        return $this->ExecuteSelect();
    }

    public function InRange(int $startingID, int $limit) : ?array
    {   
        $pkSchema = $this->datamodel->GetIDSchema()->Single();

        if($pkSchema == null)
        {
            Composer::Throw("The table " . $this->tableSchema->GetTableName() . " has a composite primary key. This method is only applicable to tables with a single-column primary key.");
        }

        return $this->ColumnInRange($pkSchema->GetColumnName(), $startingID, $limit);
    }

    public function Max(string $columnName) : int
    {
        if(!$this->tableSchema->ColumnExists($columnName))
        {
            Composer::Throw("Column $columnName does not exist in the table " . $this->tableSchema->GetTableName());
        }

        $columnSchema = new Tablecolumnschema($this->tableSchema, $columnName);

        if(!$columnSchema->IsInteger())
        {
            Composer::Throw("Column $columnName is not an integer column. The maximum value can only be fetched from integer columns.");
        }

        $this->Select("MAX($columnName)");
        $result = $this->ExecuteSelect(true);
        return is_numeric($result) ? (int)$result : 0;
    }

    /**
     * 
 _______                                             .__         .__    .___
 \      \   ____     ____  __ __  _____   ___________|__| ____   |__| __| _/
 /   |   \ /  _ \   /    \|  |  \/     \_/ __ \_  __ \  |/ ___\  |  |/ __ | 
/    |    (  <_> ) |   |  \  |  /  Y Y  \  ___/|  | \/  \  \___  |  / /_/ | 
\____|__  /\____/  |___|  /____/|__|_|  /\___  >__|  |__|\___  > |__\____ | 
        \/              \/            \/     \/              \/          \/ 
     */

    
    public function FetchItemById(IDschema|string|array $id) : array|null
    {
        self::CheckIntegrityID($id);

        $this->ClearQuery();
        $this->SetIDCondition($id);
        $this->SetQueryModifier("LIMIT 1");
        
        return $this->ExecuteSelect()[0] ?? null;
    }

    /**
     * Fetches a full item from the database by its ID.
     *
     * This method retrieves a single record from the database table based on the provided ID.
     * The ID can be provided as an instance of the IDschema class, a string, or an array.
     *
     * @param IDschema|string|array $id The identifier of the item to fetch. It can be an instance of IDschema, 
     *                                  a string, or an array representing the ID.
     * 
     * @return array|null Returns an associative array containing the item data if found, or null if no item is found.
     * 
     * @throws \InvalidArgumentException If the provided ID does not pass integrity checks.
     * @throws \PDOException If there is an error during the database query execution.
     */
    public function FetchFullItemById(IDschema|string|array $id) : array|null
    {
        self::CheckIntegrityID($id);
        $this->ClearQuery();
        $this->SetIDCondition($id);
        $this->SetQueryModifier("LIMIT 1");

        return $this->ExecuteSelect()[0] ?? null;
    }

    /**
     * Fetches rows from a database table in their physical storage order.
     *
     * This method retrieves rows from the specified table schema starting from a given physical ID
     * and limits the number of rows fetched. The query does not include an ORDER BY clause to ensure
     * the physical storage order is respected. This method is useful for fetching rows in the order
     * they were inserted into the table when the table has no explicit ordering.
     *
     * @param int $startingPhysicID The starting physical ID for fetching rows.
     * @param int $limit The maximum number of rows to fetch.
     * 
     * @return array|null An array of fetched rows as associative arrays, or null if no rows are found.
     */
    public function FetchInPhysicOrder(int $startingPhysicID, int $limit) : array|null
    {
        // Generar la consulta sin ORDER BY para respetar el orden físico de almacenamiento
        $this->ClearQuery();
        $this->SetQueryModifier("LIMIT $startingPhysicID, $limit");
        
        return $this->ExecuteSelect();
    }

    /**
     * Fetches a specified number of random items from a database table.
     *
     * @param int $limit The maximum number of random items to fetch.
     * 
     * @return array|null An array of fetched items as associative arrays, or null if no items are found.
     */
    public function Randoms(int $limit) : array|null
    {
        $this->ClearQuery();
        $this->SetQueryModifier("ORDER BY RAND()");
        $this->SetQueryModifier("LIMIT $limit");

        return $this->ExecuteSelect();
    }

    public function ValueExists(string $column, string $value) : bool
    {
        // Restriction 1. The column must exist in the table schema
        if(!$this->tableSchema->ColumnExists($column))
        {
            Composer::Throw("Column $column does not exist in the table " . $this->tableSchema->GetTableName());
        }

        $this->ClearQuery();
        $this->SetCondition($column, Conditions::EQUALS, $value);
        $this->Select("COUNT(*)");

        $count = $this->ExecuteSelect(true);

        return ($count > 0);
    }

    /**
     * Checks if an item exists in the database table based on the provided ID.
     *
     * @param IDschema|string|array|null $id The identifier(s) to check for existence. 
     *                                       Can be an IDschema object, a string, an array of key-value pairs, or null.
     * 
     * @return bool Returns true if the item exists, false otherwise.
     * 
     * @throws InvalidArgumentException If the ID integrity check fails.
     * @throws PDOException If there is an error during the database query execution.
     */
    public function ItemExists(IDschema|string|array|null $id): bool
    {
        if($id === null)
        {
            return false;
        }

        self::CheckIntegrityIDOnTable($this->tableSchema, $id);
        $this->ClearQuery();
        $idPairs = ($id instanceof IDschema) ? $id->GetPairs() : (is_array($id) ? $id : [$this->tableSchema->GetIDColumnNames()[0] => $id]);

        foreach ($idPairs as $key => $value)
        {
            $this->SetCondition($key, Conditions::EQUALS, $value);
        }

        $this->ClearSelected();
        $this->Select("COUNT(*)");
        $count = $this->ExecuteSelect(true);
        
        return ($count > 0);
    }

    public function FirstWhere(string $column, string $search) : array|null
    {
        if (!$this->tableSchema->ColumnExists($column))
        {
            Composer::Throw("Column $column does not exist in the table " . $this->tableSchema->GetTableName());
            return null;
        }

        $this->ClearQuery();
        $this->SetCondition($column, Conditions::EQUALS, $search);
        $this->SetQueryModifier("LIMIT 1");
        return $this->ExecuteSelect()[0] ?? null;
    }

    public function AllWhere(string $column, string $value) : array|null
    {
        if (!$this->tableSchema->ColumnExists($column))
        {
            Composer::Throw("Column $column does not exist in the table " . $this->tableSchema->GetTableName());
            return null;
        }

        $this->ClearQuery();
        $this->SetCondition($column, Conditions::EQUALS, $value);
        return $this->ExecuteSelect();
    }


    public function AllRows() : array|null
    {
        $this->ClearQuery();
        return $this->ExecuteSelect();
    }


    public function Sum(string $column) : int
    {
        if (!$this->tableSchema->ColumnExists($column))
        {
            Composer::Throw("Column $column does not exist in the table " . $this->tableSchema->GetTableName());
            return 0;
        }

        $this->ClearQuery();
        $this->Select("SUM($column)");
        $sum = $this->ExecuteSelect(true);
        return (int)$sum;
    }
    
    /**
 _____                _             __  _   _           _       _       
/  __ \              | |           / / | | | |         | |     | |      
| /  \/_ __ ___  __ _| |_ ___     / /  | | | |_ __   __| | __ _| |_ ___ 
| |   | '__/ _ \/ _` | __/ _ \   / /   | | | | '_ \ / _` |/ _` | __/ _ \
| \__/\ | |  __/ (_| | ||  __/  / /    | |_| | |_) | (_| | (_| | ||  __/
 \____/_|  \___|\__,_|\__\___| /_/      \___/| .__/ \__,_|\__,_|\__\___|
                                             | |                        
                                             |_|                        
     */


    public function Insert(array $data) : array
    {
        $idPairs = $this->GetDatamodel()->GetIDSchema()->GetPairs();

        // Restriction 1. The data array must contain all the ID columns for composite primary keys to ensure the integrity of the inserted data.
        if(array_count_values($idPairs) > 0 && !empty(array_diff(array_keys($idPairs), array_keys($data))))
        {
            Composer::Throw("The data array must contain all the columns of the table schema including all the ID columns.");
        }

        $singleSchema = $this->datamodel->GetIDSchema()->Single();

        // Insert "NULL" for auto-incremental columns.
        if($singleSchema && $singleSchema->IsAutoIncremental() && (!isset($data[$singleSchema->GetColumnName()]) || in_array($data[$singleSchema->GetColumnName()], ["DEFAULT", null])))
        {
            $data[$singleSchema->GetColumnName()] = "NULL";
        }

        // Insert UUID values for UUID ID columns if not provided in the data array.
        else if($singleSchema && $singleSchema->IsUUID() && !isset($data[$singleSchema->GetColumnName()]))
        {
            $data[$singleSchema->GetColumnName()] = $this->datamodel->GetIDSchema()->GenerateUUID();
        }

        $db = $this->tableSchema->GetDatabase()->connect();
        $columns = array_keys($data);

        // Manage DEFAULT database values when key is missing in data array or equals DEFAULT
        $placeholders = array_map(function($col) use(&$data) {
            if($data[$col] === "DEFAULT")
            {
                //Delete the column from the data array
                unset($data[$col]);
                return "DEFAULT";
            }
            
            return ":$col";
        }, $columns);

        $columnsList = implode(", ", $columns);
        $placeholdersList = implode(", ", $placeholders);
        $query = "INSERT INTO " . $this->tableSchema->GetTableName() . " ($columnsList) VALUES ($placeholdersList)";
        $statement = $db->prepare($query);
        $statement->execute($data);

        if($singleSchema && $singleSchema->IsUUID())
        {
            $idPairs[$singleSchema->GetColumnName()] = $data[$singleSchema->GetColumnName()];
        }
        else if($singleSchema && $singleSchema->IsAutoIncremental())
        {
            $idPairs[$singleSchema->GetColumnName()] = $db->lastInsertId();
        }

        return $idPairs;
    }

    public function Update(array $data) : array
    {
        $idSchema = $this->datamodel->GetIDSchema();

        // Restriction 1. The item to be updated must exist in the database
        if(!$this->ItemExists($idSchema))
        {
            Composer::Throw("Item with the specified ID does not exist and cannot be updated.");
        }
        
        $db = $this->tableSchema->GetDatabase()->connect();
        $columns = array_keys($data);
        $this->SetIDCondition($idSchema);

        $updatePairs = array_map(fn($col) => "$col = :$col", $columns);
        $query = "UPDATE " . $this->tableSchema->GetTableName() . 
                " SET " . implode(', ', $updatePairs) . ' ' .
                $this->GetConditionsQuery();

        $statement = $db->prepare($query);
        $statement->execute($data);

        return $idSchema->GetPairs();
    }

    /**
______     _      _       
|  _  \   | |    | |      
| | | |___| | ___| |_ ___ 
| | | / _ \ |/ _ \ __/ _ \
| |/ /  __/ |  __/ ||  __/
|___/ \___|_|\___|\__\___|
     */

    public function Delete() : bool
    {
        $this->SetIDCondition($this->datamodel->GetIDSchema());
        $affectedRows = $this->ExecuteDelete();
        return ($affectedRows > 0);
    }



    /**
     * 
__________        .__               __           .___        __                 _____                     
\______   \_______|__|__  _______ _/  |_  ____   |   | _____/  |_  ____________/ ____\____    ____  ____  
 |     ___/\_  __ \  \  \/ /\__  \\   __\/ __ \  |   |/    \   __\/ __ \_  __ \   __\\__  \ _/ ___\/ __ \ 
 |    |     |  | \/  |\   /  / __ \|  | \  ___/  |   |   |  \  | \  ___/|  | \/|  |   / __ \\  \__\  ___/ 
 |____|     |__|  |__| \_/  (____  /__|  \___  > |___|___|  /__|  \___  >__|   |__|  (____  /\___  >___  >
                                 \/          \/           \/          \/                  \/     \/    \/ 
     */

    /**
     * Checks the integrity of an ID against a table schema.
     *
     * This method verifies whether the provided ID is valid for the given table schema.
     * If the ID is an instance of `IDschema`, it is considered valid. Otherwise, it
     * delegates the validation to the `CheckValidAssignID` method of the table schema's
     * ID schema.
     *
     * @param Tableschema $tableSchema The schema of the table to validate against.
     * @param IDschema|string|array|null $id The ID to validate. It can be an instance of
     *                                       `IDschema`, a string, an array, or null.
     * @return bool Returns `true` if the ID is valid, otherwise `false`.
     */
    public static function CheckIntegrityIDOnTable(Tableschema $tableSchema, IDschema|string|array|null $id) : bool
    {
        if($id instanceof IDschema)
        {
            return true;
        }

        return $tableSchema->GetAnyIDSchema()->CheckValidAssignID($id);
    }

    /**
     * Checks the integrity of the provided ID against the table schema.
     *
     * This method verifies whether the given ID is valid based on the table schema's
     * ID schema. If the ID is an instance of `IDschema`, it is considered valid.
     * Otherwise, it delegates the validation to the `CheckValidAssignID` method
     * of the table schema's ID schema.
     *
     * @param IDschema|string|array|null $id The ID to validate. It can be an instance
     *                                       of `IDschema`, a string, an array, or null.
     * @return bool Returns true if the ID is valid; otherwise, false.
     */
    private function CheckIntegrityID(IDschema|string|array|null $id) : bool
    {
        return self::CheckIntegrityIDOnTable($this->tableSchema, $id);
    }

    /**
     * Checks if the given table schema has a numeric single ID column as its primary key.
     *
     * This method validates that the table schema's primary key is numeric and consists of a single column.
     * If the primary key does not meet these criteria, an exception is thrown.
     * 
     * @return bool Returns true if the table schema has a numeric single ID column.
     * 
     * @throws \Exception If the table schema does not have a numeric single ID column.
     */
    private function CheckTableschemaHasNumericSingleID() : bool
    {
        $primaryKey = $this->tableSchema->GetIDColumnNames()[0] ?? null;

        if(!$primaryKey || array_count_values($this->tableSchema->GetIDColumnNames()) !== 1 || !$primaryKey->IsIDNumeric())
        {
            Composer::Throw("The table schema " . $this->tableSchema->GetTableName() . " must have a numeric single ID column to perform this operation.");
            return false;
        }

        return true;
    }

    /**
 _____      _   _                
|  __ \    | | | |               
| |  \/ ___| |_| |_ ___ _ __ ___ 
| | __ / _ \ __| __/ _ \ '__/ __|
| |_\ \  __/ |_| ||  __/ |  \__ \
 \____/\___|\__|\__\___|_|  |___/
     */

    public function GetTableschema() : Tableschema
    {
        return $this->tableSchema;
    }

    public function GetDatamodel() : Datamodel
    {
        return $this->datamodel;
    }

}
