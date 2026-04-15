<?php
namespace Database;

use Countable;
use Deprecated;
use Database\Tableschema;
use Composer\Composer;

include_once "tableschema.php";

/**
 * The IDschema class is responsible for managing the primary key schema of a database table.
 *
 * This class provides methods to resolve the primary key schema based on the table's constraints,
 * validate and set primary key values, and generate new IDs when necessary. It supports both
 * single primary keys and composite primary keys, as well as different types of primary keys
 * such as numeric IDs and UUIDs.
 * 
 * An ID schema can be of two types:
 * - A single primary key column, which can be either numeric or a UUID.
 * - A composite primary key, which consists of multiple columns. In this case, the ID schema will manage multiple primary key schemas corresponding to each column in the composite key.
 */
class IDschema implements Countable
{
    /**
     * @var Tableschema $tableSchema An instance of the Tableschema class representing the schema of the table.
     */
    protected Tableschema $tableSchema;

    /**
     * An array containing all primary key schemas.
     *
     * This property stores the schema definitions for all primary keys
     * used in the database. Each element in the array is an instance of
     * the `Primarykeyschema` class.
     *
     * @var Primarykeyschema[] $allPrimaryKeySchemas
     */
    protected array $allPrimaryKeySchemas;

    /**
     * Indicates whether the ID schema was resolved.
     *
     * @var bool
     */
    protected bool $wasResolved;

    /**
     * Stores the last numeric ID that was generated.
     *
     * @var int
     */
    protected int $lastNumericIDGenerated;

    /**
     * Static array to store any generated ID schemas for tables, indexed by table name.
     * 
     * @var IDschema[] $anyIDschemasGenerated
     */
    protected array $anyIDschemasGenerated;


    public function __construct(Tableconstraints &$tableConstraints)
    {
        $this->tableSchema = $tableConstraints->GetTableSchema();
        $this->lastNumericIDGenerated = 0;
        
        if(isset($this->anyIDschemasGenerated[$this->tableSchema->GetTableName()]))
        {
            $any = $this->anyIDschemasGenerated[$this->tableSchema->GetTableName()];
            $this->wasResolved = $any->WasResolved();
            $this->allPrimaryKeySchemas = $any->GetAllPrimaryKeySchemas();
        }
        else
        {
            $this->ResolveIDschema($tableConstraints);
            $this->anyIDschemasGenerated[$this->tableSchema->GetTableName()] = $this;
        }
    }

    /**
     * Resolves the ID schema for a table based on its primary key constraints.
     *
     * This method analyzes the primary key constraints of the given table and 
     * constructs the schema for managing the table's data model. If no primary 
     * key is defined, an exception is thrown as the table cannot be managed 
     * without a primary key.
     *
     * @param Tableconstraints $tableConstraints An object containing the table's 
     *                                           constraints, including primary key definitions.
     * 
     * @return bool Returns true if the ID schema was successfully resolved, 
     *              otherwise false.
     * 
     * @throws \Exception If no primary key is found for the table, an exception 
     *                    is thrown indicating that the ID schema cannot be resolved.
     */
    public function ResolveIDschema(Tableconstraints &$tableConstraints) : bool
    {
        // If there are no primary keys, the schema cannot be resolved.
        $primaryKeyConstraints = $tableConstraints->GetPrimaryKeyConstraints();

        if(count($primaryKeyConstraints) === 0)
        {
            $this->wasResolved = false;

            //Throw an exception since the table cannot be managed without a primary key
            Composer::Throw("No primary key found for table {$this->tableSchema->GetTableName()}. Unable to resolve ID schema. The table must have a primary key defined to manage their Datamodel.");
            return false;
        }
    

        foreach($primaryKeyConstraints as $primaryKey)
        {
            $columnSchema = $primaryKey->GetDefinitionColumnSchema();
            $this->allPrimaryKeySchemas[$columnSchema->GetColumnName()] = new Primarykeyschema($columnSchema);
        }
        
        $this->wasResolved = true;
        return true;
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
     * Retrieves the table schema associated with the current instance.
     *
     * @return Tableschema The table schema object.
     */
    public function &GetTableSchema(): Tableschema
    {
        return $this->tableSchema;
    }

    /**
     * Retrieves all primary key schemas.
     *
     * @return array An array containing all primary key schemas.
     */
    public function GetAllPrimaryKeySchemas(): array
    {
        return $this->allPrimaryKeySchemas;
    }

    /**
     * Retrieves the primary key schema associated with a specific column name.
     *
     * @param string $columnName The name of the column for which the primary key schema is to be retrieved.
     * @return Primarykeyschema|null The primary key schema for the specified column, or null if no schema is found.
     */
    public function GetPrimaryKeySchemaForColumn(string $columnName): Primarykeyschema|null
    {
        return $this->allPrimaryKeySchemas[$columnName] ?? null;
    }

    /**
     * Retrieves all primary key column names from the schema.
     *
     * @return string[] An array of strings representing the names of all primary key columns.
     */
    public function GetAllColumns(): array
    {
        return array_keys($this->allPrimaryKeySchemas);
    }

    /**
     * Retrieves the total number of primary key columns.
     *
     * This method returns the total number of primary key columns found in the table.
     *
     * @return int The total number of primary key columns.
     */
    public function GetColumnsCount(): int
    {
        return count($this->allPrimaryKeySchemas);
    }

    /**
     * Checks if a column exists in the primary key schemas.
     *
     * @param string $columnName The name of the column to check.
     * @return bool True if the column exists in the primary key schemas, false otherwise.
     */
    public function HasColumn(string $columnName): bool
    {
        return array_key_exists($columnName, $this->allPrimaryKeySchemas);
    }

    /**
     * Retrieves key-value pairs of all primary key columns and their values.
     *
     * @return array An associative array where keys are column names and values are
     *               the corresponding schema values obtained from GetValue().
     */
    public function GetPairs(): array
    {
        $pairs = array();

        foreach($this->allPrimaryKeySchemas as $column => $schema)
        {
            $pairs[$column] = $schema->GetValue();
        }

        return $pairs;
    }

    /**
     * Checks if the ID was resolved (There is at least one correctly formed primary key).
     *
     * This method returns true if the primary key was successfully resolved, and false otherwise.
     * 
     * The primary key might not be resolved in the following cases:
     * 1. The table does not have a primary key defined.
     * 2. The column intended to be the primary key is not marked as PRIMARY KEY.
     * 3. The table schema is corrupted or improperly defined.
     * 4. The database connection or query execution failed.
     * @return bool True if the primary key was resolved, false otherwise.
     */
    public function WasResolved(): bool
    {
        return $this->wasResolved;
    }


    /**
     * Retrieves the default primary key schema.
     *
     * This method returns the default primary key schema for the current database table.
     * If the table is using a composite ID, it will return null. Otherwise, it will return
     * the first primary key schema from the list of all primary key schemas.
     *
     * @return Primarykeyschema|null The default primary key schema, or null if using a composite ID.
     */
    public function Single(): Primarykeyschema|null
    {
        return $this->IsComposite() ? null : $this->allPrimaryKeySchemas[array_key_first($this->allPrimaryKeySchemas)];
    }

    /**
     * 
_________ .__                   __     ___________                   __  .__                      
\_   ___ \|  |__   ____   ____ |  | __ \_   _____/_ __  ____   _____/  |_|__| ____   ____   ______
/    \  \/|  |  \_/ __ \_/ ___\|  |/ /  |    __)|  |  \/    \_/ ___\   __\  |/  _ \ /    \ /  ___/
\     \___|   Y  \  ___/\  \___|    <   |     \ |  |  /   |  \  \___|  | |  (  <_> )   |  \\___ \ 
 \______  /___|  /\___  >\___  >__|_ \  \___  / |____/|___|  /\___  >__| |__|\____/|___|  /____  >
        \/     \/     \/     \/     \/      \/             \/     \/                    \/     \/ 
     */
    
    /**
     * Determines if the table has a composite primary key.
     *
     * A composite primary key is defined as a primary key that consists
     * of more than one column.
     *
     * @return bool Returns true if the primary key consists of multiple columns, false otherwise.
     */
    public function IsComposite(): bool
    {
        return $this->GetColumnsCount() > 1;
    }

    /**
     * Determines if the schema is using a single primary key column.
     *
     * @return bool Returns true if the primary key consists of a single column, false otherwise.
     */
    public function IsSingle(): bool
    {
        return $this->GetColumnsCount() == 1;
    }

    /**
     * Determines if the schema is using a numeric ID as the primary key.
     *
     * This method checks if the schema is configured to use a single primary key
     * and verifies if the default primary key schema specifies a numeric ID.
     *
     * @return bool Returns true if the schema uses a numeric ID as the primary key, false otherwise.
     */
    public function IsNumeric(): bool
    {
        return $this->IsSingle() && $this->Single()->IsNumeric();
    }

    /**
     * Determines if the current schema is using UUID as the primary key type.
     *
     * This method checks if the schema is configured to use a single ID and 
     * verifies if the default primary key schema is utilizing UUIDs.
     *
     * @return bool Returns true if the schema is using UUID as the primary key type, false otherwise.
     */
    public function IsSingleUUID(): bool
    {
        return $this->IsSingle() && $this->Single()->IsUUID();
    }

    /**
     * Validates the provided ID based on the table's primary key schema.
     *
     * This method checks whether the provided ID is valid according to the table's
     * primary key configuration. It handles both single primary keys and composite
     * primary keys.
     *
     * @param string|array|null &$id The ID to validate. This can be a string, an array,
     *                               or null. The type depends on whether the table uses
     *                               a single primary key or a composite primary key.
     *
     * @return bool Returns true if the ID is valid; otherwise, an exception is thrown.
     *
     * @throws \Exception If the table uses a single primary key and an array is provided,
     *                    or if the table uses a composite primary key and a non-array value
     *                    is provided.
     */
    public function CheckValidAssignID(string|array|null &$id): bool
    {
        //When using array of one element, it is considered a single primary key
        if($this->IsSingle() && is_array($id))
        {
            if(count($id) == 0)
            {
                throw new \Exception("Error: Unable to set the ID for the table. The provided ID is empty.");
            }

            if(array_keys($id)[0] == $this->Single()->GetColumnName())
            {
                return true;
            }

            throw new \Exception("Error: Unable to set the ID for the table. The provided ID does not match the primary key column for the table '{$this->tableSchema->GetTableName()}'.");
            return false;
        }

        if($this->IsComposite() && !is_array($id))
        {
            throw new \Exception("Error: Unable to set the ID for the table. Trying to set a single primary key value for a composite ID.");
            return false;
        }

        if(!$this->IsComposite())
        {
            // If the table uses a single primary key and the ID is not an array, validate the ID.
            return $this->Single()->ValidateAssignValue($id);
        }
        
        return true; // If the table uses a composite primary key, no further validation is required.
    }

    /**
     * 
__________     ___.   .__  .__         .___        __                 _____                     
\______   \__ _\_ |__ |  | |__| ____   |   | _____/  |_  ____________/ ____\____    ____  ____  
 |     ___/  |  \ __ \|  | |  |/ ___\  |   |/    \   __\/ __ \_  __ \   __\\__  \ _/ ___\/ __ \ 
 |    |   |  |  / \_\ \  |_|  \  \___  |   |   |  \  | \  ___/|  | \/|  |   / __ \\  \__\  ___/ 
 |____|   |____/|___  /____/__|\___  > |___|___|  /__|  \___  >__|   |__|  (____  /\___  >___  >
                    \/             \/           \/          \/                  \/     \/    \/ 
     */

    /**
     * Sets the ID for the table, supporting both single primary key and composite primary key scenarios.
     *
     * @param string|array|null $id The ID to set. Can be a string for single primary key or an associative array for composite primary key.
     * 
     * @return bool Returns true if the ID is successfully set, false otherwise.
     * 
     * @throws \Exception If attempting to set a composite ID on a table with a single primary key, 
     *                    or if attempting to set a single primary key value on a table with a composite ID.
     */
    public function SetID(string|array|null $id) : bool
    {
        $this->CheckValidAssignID($id);
        
        if(!$this->IsComposite())
        {
            return $this->Single()->SetValue(is_array($id) ? $id[array_key_first($id)] : $id);
        }
        else
        {
            $columns = array_keys($id);
            $values = array_values($id);

            for($i = 0; $i < count($columns); $i++)
            {
                $this->SetSingleColumn($columns[$i], $values[$i]);
            }

            return true;
        }
    }

    /**
     * Sets the value of a single-column primary key in the schema.
     * 
     * Useful when you want to set the value of a single primary key column even if you don't know if the table is using a composite ID or not. If the table is using a composite ID, this method will set the value of the specified column in the composite key.
     * 
     * @param string $columnName The name of the column representing the primary key.
     * @param string|null $id The value to set for the primary key. Can be null.
     * 
     * @return bool Returns true if the operation was successful, false otherwise.
     */
    public function SetSingleColumn(string $columnName, string|null $id) : bool
    {
        // Restriction 1. Check if the column is a primary key column.
        if(!$this->HasColumn($columnName))
        {
            Composer::Throw("Unable to set the ID for the table. The column '{$columnName}' is not a primary key column for the table '{$this->tableSchema->GetTableName()}'.");
            return false;
        }

        $this->allPrimaryKeySchemas[$columnName]->SetValue($id);
        return true;
    }

    /**
     * Retrieves the ID of the current entity.
     *
     * This method returns the primary key value(s) of the entity. If the entity
     * uses a single primary key, it returns the value as a string. If the entity
     * uses a composite primary key, it returns an associative array where the
     * keys are the column names and the values are the corresponding primary key
     * values. If no primary key is set, it returns null.
     *
     * @return string|array|null The primary key value(s) of the entity:
     *                           - string: Single primary key value.
     *                           - array: Associative array of composite primary key values.
     *                           - null: If no primary key is set.
     */
    public function GetID(): string|array|null
    {
        if(!$this->IsComposite())
        {
            return $this->Single()->GetValue();
        }

        $id = array();

        foreach($this->allPrimaryKeySchemas as $column => $schema)
        {
            $id[$column] = $schema->GetValue();
        }

        return $id;
    }

    /**
     * Retrieves the ID as a key-value array.
     *
     * This method attempts to retrieve the ID of the current object in the form of
     * an associative array where the key is the column name and the value is the ID.
     *
     * @return array|bool Returns an associative array with the ID as a key-value pair
     *                    if the ID is set, or the ID itself if it is already an array.
     *                    Returns false if the ID cannot be retrieved.
     * 
     * @throws \Exception If the ID is not set and cannot be retrieved.
     */
    public function GetIDAsKeyValueArray(): array|bool
    {
        $id = $this->GetID();

        if(is_array($id))
        {
            return $id;
        }
        elseif($this->IsSingle())
        {
            $value = $id == null ? "NULL" : $id;
            return [$this->Single()->GetColumnName() => $value];
        }
        
        //If the ID is not set, throw an exception
        Composer::Throw("Error: Unable to retrieve the ID for the table. The ID is not set.");
        return false;
    }

    /**
     * Deduce a new ID for the primary key based on the schema configuration.
     *
     * This method determines the appropriate ID generation strategy for the primary key
     * based on the schema's configuration. It supports auto-increment, numeric IDs, and UUIDs.
     *
     * @param bool $forceNotNull If true, forces the method to generate a new ID even if the schema
     *                           uses auto-increment. Defaults to false.
     * 
     * @return string|null Returns the newly generated ID as a string, or null if the schema uses
     *                     auto-increment and $forceNotNull is false.
     * 
     * @throws \Exception If the table uses a composite primary key or if the ID generation strategy
     *                    cannot be determined.
     */
    public function DeduceNewID(bool $forceNotNull=false): string|null
    {
        $primaryKeySchema = $this->Single();

        if($primaryKeySchema == null)
        {
            Composer::Throw("Error: Unable to deduce a new ID for the primary key. The table is using a composite primary key.");
            return null;
        }

        if ($primaryKeySchema->IsAutoIncremental() && !$forceNotNull)
        {
            return null;
        }
        elseif ($primaryKeySchema->IsNumeric())
        {
            return $this->GenerateNewNumericID();
        }
        elseif ($primaryKeySchema->IsUUID())
        {
            return $this->GenerateUUID();
        }

        Composer::Throw("Error: Unable to deduce a new ID for the primary key.");
        return null;
    }

    /**
     * Attempts to set a new ID for the current instance.
     *
     * This method deduces a new ID and sets it as the current ID.
     * If the `$forceNotNull` parameter is true, the method ensures
     * that the deduced ID is not null.
     *
     * @param bool $forceNotNull Whether to force the new ID to be non-null.
     * @return string|null The newly set ID, or null if the operation fails.
     */
    public function TrySetNewID(bool $forceNotNull=false) : string|null
    {
        $newID = $this->DeduceNewID($forceNotNull);
        return $this->SetID($newID);
    }

    /**
     * 
  ________                                   __                       
 /  _____/  ____   ____   ________________ _/  |_  ___________  ______
/   \  ____/ __ \ /    \_/ __ \_  __ \__  \\   __\/  _ \_  __ \/  ___/
\    \_\  \  ___/|   |  \  ___/|  | \// __ \|  | (  <_> )  | \/\___ \ 
 \______  /\___  >___|  /\___  >__|  (____  /__|  \____/|__|  /____  >
        \/     \/     \/     \/           \/                       \/ 
     */

    /**
     * Generates a UUID (Universally Unique Identifier) version 4.
     *
     * This method creates a random UUID using the `mt_rand` function to generate
     * random numbers. The format of the UUID is `xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx`
     * where `x` is any hexadecimal digit and `y` is one of `8`, `9`, `A`, or `B`.
     *
     * @return string The generated UUID.
     */
    public function GenerateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Generates a new numeric ID for the primary key of the table.
     *
     * This method ensures that the generated ID is unique and does not already exist
     * in the table. It uses the last known primary key numeric value and increments
     * it until a unique ID is found. If the table uses a composite primary key, an
     * exception is thrown.
     *
     * @return string|null Returns the newly generated numeric ID as a string, or null
     *                     if an error occurs during generation.
     *
     * @throws \Exception If the table uses a composite primary key, an exception is
     *                    thrown indicating that a numeric ID cannot be generated.
     */
    #[Deprecated("This method is deprecated and may be removed in future versions. Consider using a more robust ID generation strategy that does not rely on auto-incrementing numeric values, especially in distributed systems or scenarios with high concurrency.")]
    public function GenerateNewNumericID(): string|null
    {
        return null;
    }

    /**
     * 
____   ____      .__  .__    .___       __                       
\   \ /   /____  |  | |__| __| _/____ _/  |_  ___________  ______
 \   Y   /\__  \ |  | |  |/ __ |\__  \\   __\/  _ \_  __ \/  ___/
  \     /  / __ \|  |_|  / /_/ | / __ \|  | (  <_> )  | \/\___ \ 
   \___/  (____  /____/__\____ |(____  /__|  \____/|__|  /____  >
               \/             \/     \/                       \/ 
     */

    /**
     * Validates whether the given string is a valid UUID (Universally Unique Identifier).
     *
     * A valid UUID is a 36-character string formatted as:
     * xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
     * where each 'x' is a hexadecimal digit (0-9, a-f).
     *
     * @param string $uuid The UUID string to validate.
     * @return bool Returns true if the given string is a valid UUID, false otherwise.
     */
    public static function ValidUUID(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid) === 1;
    }

    /**
     * Validates whether the given ID is a numeric value.
     *
     * This method checks if the provided string represents a numeric value.
     *
     * @param string $id The ID to validate.
     * @return bool Returns true if the ID is numeric, otherwise false.
     */
    public static function ValidNumericID(string $id): bool
    {
        return is_numeric($id);
    }

    /**
     * Validates whether the given ID is a valid numeric auto-increment ID.
     *
     * This method checks if the provided ID is either:
     * - A numeric value
     * - Null
     *
     * @param string|null $id The ID to validate. It can be a string or null.
     * @return bool Returns true if the ID is numeric or null; otherwise, false.
     */
    public static function ValidNumericAutoIncrementID(string|null $id): bool
    {
        return is_null($id) || self::ValidNumericID($id);
    }

    public function count(): int
    {
        return $this->GetColumnsCount();
    }
}