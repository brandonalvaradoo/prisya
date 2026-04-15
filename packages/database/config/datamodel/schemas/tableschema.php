<?php
namespace Database;
use Database\Database;
use Database\Tablecolumnschema;
use Database\Tableconstraints;
use Database\IDschema;
use Composer\Composer;

include_once 'primarykeyschema.php';
include_once __DIR__ . '/../tableconstraints.php';
include_once 'idschema.php';

/**
 * Class Tableschema
 *
 * This class represents the schema of a database table and provides methods to resolve the table name,
 * retrieve the column names, and manage the primary key schema.
 *
 * @package Database
 */
class Tableschema
{
    //DEPENDS
    protected Database $database;

    //STATIC IMPLEMENTS (shared by one class of datamodels, but not shared by all datamodels)
    protected string $tableName;
    protected string $className;
    protected array $allTableColumns; // Names of all columns in the table, including primary key columns.
    protected array|null $primaryKeyColumns; // Names of the primary key columns in the table.
    protected array $columnSchemas;
    protected Tableconstraints $tableConstraints;

    /**
     * Any ID schema.
     * 
     * It is not usable as Datamodel's ID schema, because it is not deduced from specific objective table schema, but it is useful to perform operations that require an ID schema before the table schema is fully initialized, like checking if a given ID value is valid for the primary key column of the table, since it is initialized with the default values of the ID schema properties, and it can be used to check if a given ID value is valid for the primary key column of the table by setting its properties with the values of the primary key column schema.
     */
    protected IDschema $anyIDSchema;

    protected static array $databaseSearchKeysDictionary;
    protected static array $PREPARED_TABLESCHEMAS;
    protected static array $IRREGULARS;


    /**
     * Constructor for the TableSchema class.
     *
     * @param Database $database The database instance to be used.
     * @param string $className The name of the class representing the table.
     *
     * Initializes the table schema by setting the database, resolving the table name,
     * retrieving the column names, and setting up the primary key schema.
     * Note: The order of operations is important; the database must be set before the table name.
     */
    public function __construct(Database $database, string $className)
    {
        $this->__init($database, $className);
        self::$PREPARED_TABLESCHEMAS[$this->GetTableName()] = &$this;
    }

    /**
     * 
.___        __                 _____                     
|   | _____/  |_  ____________/ ____\____    ____  ____  
|   |/    \   __\/ __ \_  __ \   __\\__  \ _/ ___\/ __ \ 
|   |   |  \  | \  ___/|  | \/|  |   / __ \\  \__\  ___/ 
|___|___|  /__|  \___  >__|   |__|  (____  /\___  >___  >
         \/          \/                  \/     \/    \/ 
     */

    /**
     * Initializes the table schema with the provided database and class name.
     *
     * This method sets up the necessary properties for the table schema, including
     * the database reference, class name, table name, column names, and primary key schema.
     * The order of operations is important: the database must be set before the table name.
     *
     * @param Database $database The database instance to be associated with the table schema.
     * @param string $className The name of the class representing the table schema.
     *
     * @return void
     */
    private function __init(Database &$database, string $className)
    {
        // Order is important here. The database must be set before the table name.
        self::$databaseSearchKeysDictionary = self::$databaseSearchKeysDictionary ?? self::BringTablenameResolveDictionary();
        $this->database = $database;
        $this->className = $className;
        $this->tableName = $this->ResolveTableName();
        $this->allTableColumns = $this->RetrieveColumnNames();
        $this->tableConstraints = new Tableconstraints($this);
        $this->anyIDSchema = new IDschema($this->tableConstraints);
        $this->primaryKeyColumns = $this->tableConstraints->GetPrimaryKeyColumnNames();
    }

    /**
     * Builds and returns a Tableschema instance for the given class name.
     * If a Tableschema for the specified class name has already been prepared,
     * it retrieves and returns the prepared instance. Otherwise, it creates
     * a new Tableschema instance.
     *
     * @param Database $database   Reference to the database instance.
     * @param string   $className  The name of the class for which the Tableschema is being built.
     *
     * @return Tableschema Returns an instance of Tableschema for the specified class name.
     */
    public static function Builder(Database &$database, string $className) : Tableschema
    {
        if(self::IsTableschemaAlreadyPrepared($className))
        {
            return self::GetPreparedTableschema($className);
        }

        return new Tableschema($database, $className);
    }

    /**
     * Resolves the table name for the current class.
     *
     * This method converts the class name to a table name and attempts to find a similar table in the database.
     * If no similar table is found, an exception is thrown.
     *
     * @return string The name of the found table.
     * @throws \Exception If no similar table is found.
     */
    public function ResolveTableName()
    {
        $searchQueries = $this->GetOrderedSearchQueries();
        $foundTable = null;

        foreach ($searchQueries as $query)
        {
            $foundTable = $this->FindSimilarTable($query, true);
            if ($foundTable !== null)
            {
                break;
            }
        }

        return $foundTable
            ?? Composer::Throw("No table found for class $this->className. Try defining a reference table name in the dictionary file at " . Database::GetDatamodelKeyDictionaryFilePath() . ".");
    }

    /**
     * Generates an array of ordered search queries based on the class name.
     *
     * This method constructs an array of potential search queries derived from the class name.
     * It includes the following:
     * - The value from the dictionary for the class name, if available.
     * - The class name itself.
     * - The irregular form of the class name, if available.
     * - The pluralized form of the class name.
     * - The class name with an appended 's'.
     *
     * The resulting array is filtered to remove any null values.
     *
     * @return array An array of ordered search queries.
     */
    public function GetOrderedSearchQueries() : array
    {
        $ORDERED_SEARCH_QUERIES = [];
        $ORDERED_SEARCH_QUERIES[] = $this->GetDictionaryValue($this->className) ?? null;
        $ORDERED_SEARCH_QUERIES[] = $this->className;
        $ORDERED_SEARCH_QUERIES[] = $this->GetIrregular($this->className) ?? null;
        $ORDERED_SEARCH_QUERIES[] = self::SimplePluralize($this->className);
        $ORDERED_SEARCH_QUERIES[] = $this->className . 's';
        $ORDERED_SEARCH_QUERIES = array_filter($ORDERED_SEARCH_QUERIES, fn($x) => $x !== null);

        return $ORDERED_SEARCH_QUERIES;
    }

    /**
     * Resolves and retrieves the column names of a table from the database schema.
     *
     * This method connects to the database, retrieves the column names of the specified table
     * from the INFORMATION_SCHEMA.COLUMNS, and returns them as an array.
     *
     * @return array An array of column names for the specified table.
     * 
     */
    public function RetrieveColumnNames(): array
    {
        $db = $this->GetDatabase()->connect();
        $db_name = $this->GetDatabase()->GetDatabaseName();
        $tableName = $this->GetTableName();

        $query = "SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = :tableName AND TABLE_SCHEMA = :databaseName";

        $statement = $db->prepare($query);
        $statement->bindParam(':tableName', $tableName, \PDO::PARAM_STR);
        $statement->bindParam(':databaseName', $db_name, \PDO::PARAM_STR);
        $statement->execute();

        $columns = $statement->fetchAll(\PDO::FETCH_COLUMN);

        return $columns;
    }

    /**
     * Retrieves the schema information for each column in the table.
     *
     * This method fetches the names of all columns in the table and creates
     * a schema object for each column. The schema objects are then returned
     * as an associative array where the keys are the column names and the
     * values are the corresponding schema objects.
     *
     * @return array An associative array of column schemas, where the keys
     *               are column names and the values are Tablecolumnschema objects.
     */
    public function RetrieveColumnSchemas() : array
    {
        $columnNames = $this->GetColumnsNames();
        $columnSchemas = [];

        foreach ($columnNames as $columnName)
        {
            $columnSchemas[$columnName] = new Tablecolumnschema($this, $columnName);
        }

        return $columnSchemas;
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
     * Brings the table name resolve dictionary from a specified dictionary file.
     *
     * This method attempts to read a dictionary file and decode its JSON content
     * into an associative array. If the file does not exist or has an invalid format,
     * an exception is thrown.
     * 
     * Table name resolve dictionary is a JSON file that contains key-value pairs of class names and their corresponding table names in the database.
     *
     * @return array|null The decoded dictionary array, or null if an error occurs.
     *
     * @throws \Exception If the dictionary file is not found or has an invalid format.
     */
    private static function BringTablenameResolveDictionary() : array|null
    {
        $dictionaryFile = Database::GetDatamodelKeyDictionaryFilePath();

        if (!file_exists($dictionaryFile))
        {
            Composer::Throw("The dictionary file was not found at $dictionaryFile.");
            return null;
        }

        try
        {
            self::$databaseSearchKeysDictionary = json_decode(file_get_contents($dictionaryFile), true);
        }
        catch (\Exception $e)
        {
            Composer::Throw("The dictionary file has an invalid format. Please check the file at $dictionaryFile.");
            return null;
        }

        return self::$databaseSearchKeysDictionary;
    }

    /**
     * GetIrregularsDictionary
     *
     * This method returns an associative array that maps singular nouns to their irregular plural forms.
     * The dictionary includes common irregular nouns in English.
     *
     * @return array An associative array where the keys are singular nouns and the values are their irregular plural forms.
     */
    private static function GetIrregularsDictionary()
    {
        return
        [
            'person' => 'people',
            'child' => 'children',
            'mouse' => 'mice',
            'goose' => 'geese',
            'man' => 'men',
            'woman' => 'women',
            'tooth' => 'teeth',
            'foot' => 'feet',
            'cactus' => 'cacti',
            'focus' => 'foci',
            'axis' => 'axes',
            'crisis' => 'crises',
            'thesis' => 'theses',
            'analysis' => 'analyses',
            'diagnosis' => 'diagnoses',
            'fungus' => 'fungi',
            'appendix' => 'appendices',
            'formula' => 'formulae',
            'alumnus' => 'alumni',
            'stimulus' => 'stimuli',
            'medium' => 'media',
            'vertex' => 'vertices',
            'index' => 'indices',
            'ox' => 'oxen',
            'radius' => 'radii',
            'bacterium' => 'bacteria',
            'alumna' => 'alumnae',
            'datum' => 'data',
            'genius' => 'geniuses',
            'crisis' => 'crises',
            'maniac' => 'maniacs',
            'die' => 'dice',
            'wolf' => 'wolves',
            'elf' => 'elves',
            'calf' => 'calves',
            'leaf' => 'leaves',
            'loaf' => 'loaves',
            'knife' => 'knives',
            'wife' => 'wives',
            'shelf' => 'shelves',
            'life' => 'lives',
            'half' => 'halves',
            'thief' => 'thieves',
            'looseleaf' => 'looseleaves',
            'looseleaf' => 'looseleaves',
            'wife' => 'wives',
        ];
    }

    /**
     * Pluralizes a given word using simple English rules.
     *
     * This method converts a singular word to its plural form by applying the following rules:
     * - If the word ends with 'y', it replaces 'y' with 'ies'.
     * - If the word ends with 'ie', it replaces 'ie' with 'ies'.
     * - If the word ends with a vowel, it appends 's' to the word.
     * - For all other cases, it appends 'es' to the word.
     *
     * @param string $word The word to be pluralized.
     * @return string The pluralized form of the word.
     */
    private static function SimplePluralize(string $word) : string
    {
        if (substr($word, -1) == 'y')
        {
            return substr($word, 0, -1) . 'ies';
        }
        elseif (substr($word, -2) == 'ie')
        {
            return substr($word, 0, -2) . 'ies';
        }
        elseif (preg_match('/[aeiou]$/i', $word))
        {
            return $word . 's';
        }

        return $word . 'es';
    }


    /**
     * Finds a similar table name in the database that starts with the given table name.
     *
     * This method connects to the database and searches for tables in the information schema
     * that belong to the same database and have names starting with the provided table name.
     *
     * @param string $tableName The name of the table to search for similar tables.
     * @return string|null The name of the similar table if found, or null if no similar table is found.
     */
    private function FindSimilarTable(string $tableName): ?string
    {
        $pdo = $this->database->connect();

        // Consulta para buscar tablas similares
        $query = $pdo->prepare("
            SELECT TABLE_NAME 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = :database 
            AND TABLE_NAME LIKE :table"
        );

        $query->execute([
            ':database' => $this->database->getDatabaseName(),
            ':table' => $tableName . "%", // Aquí el comodín "%" se usa para buscar tablas que comiencen con el nombre pluralizado
        ]);

        if ($query->rowCount() > 0)
        {
            $row = $query->fetch(\PDO::FETCH_ASSOC);
            $tableName = $row['TABLE_NAME'];
            return $tableName;
            
        } else {
            return null;
        }
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
     * Checks if a column exists in the table schema.
     *
     * @param string $column The name of the column to check.
     * @return bool Returns true if the column exists, false otherwise.
     */
    public function ColumnExists(string $column): bool
    {
        return in_array($column, $this->allTableColumns);
    }

    /**
     * Get the name of the table.
     *
     * @return string The name of the table.
     */
    public function GetTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Get the name of the class.
     *
     * @return string The name of the class.
     */
    public function GetClassName(): string
    {
        return $this->className;
    }

    /**
     * Get the database instance.
     *
     * @return Database The database instance.
     */
    public function GetDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Retrieves the names of the columns in the table schema.
     *
     * WARNING: This array includes the primary key columns. If you want to get only the non-primary key columns, use GetNonIDColumnNames() instead.
     *
     * @return array An array containing the names of the columns.
     */
    public function GetColumnsNames(): array
    {
        return $this->allTableColumns;
    }

    /**
     * Retrieves the primary key column names for the table schema.
     *
     * @return array|null An array of primary key column names, or null if no primary key is defined.
     */
    public function GetIDColumnNames(): array|null
    {
        return $this->primaryKeyColumns;
    }

    /**
     * Retrieves the names of the non-primary key columns in the table schema.
     *
     * This method returns an array of column names that are not part of the primary key.
     * It calculates this by taking the difference between all column names and the primary key column names.
     * @return array An array of non-primary key column names. It does include also Foreign Key columns if they are not part of the primary key.
     */
    public function GetNonIDColumnNames(): array
    {
        $IDcolumns = $this->GetIDColumnNames() ?? [];
        return array_values(array_diff($this->allTableColumns, $IDcolumns));
    }

    /**
     * Retrieves the column schemas for the table.
     *
     * This method returns the column schemas if they have already been retrieved.
     * Otherwise, it retrieves the column schemas from the database and caches them.
     *
     * @return array The column schemas for the table.
     */
    public function &GetColumnSchemas(): array
    {
        $this->columnSchemas = $this->columnSchemas ?? $this->RetrieveColumnSchemas();
        return $this->columnSchemas;
    }

    /**
     * Retrieves the schema object representing any ID.
     * 
     * It is not usable as Datamodel's ID schema, because it is not deduced from specific objective table schema, but it is useful to perform operations that require an ID schema before the table schema is fully initialized, like checking if a given ID value is valid for the primary key column of the table, since it is initialized with the default values of the ID schema properties, and it can be used to check if a given ID value is valid for the primary key column of the table by setting its properties with the values of the primary key column schema.
     * 
     * @return IDschema The schema object representing any ID.
     */
    public function &GetAnyIDSchema(): IDschema
    {
        return $this->anyIDSchema;
    }

    /**
     * Retrieves the schema of a specific column.
     *
     * @param string $columnName The name of the column whose schema is to be retrieved.
     * @return Tablecolumnschema The schema of the specified column.
     */
    public function GetColumnSchema(string $columnName): Tablecolumnschema
    {
        return $this->GetColumnSchemas()[$columnName];
    }

    /**
     * Retrieves the table constraints for the table.
     *
     * @return Tableconstraints The table constraints for the table.
     */
    public function &GetTableConstraints(): Tableconstraints
    {
        return $this->tableConstraints;
    }

    /**
     * Retrieves the primary key constraints for the table.
     *
     * @return Tableconstraint[] A referenced array of Tableconstraint objects representing the primary key constraints.
     */
    public function &GetPrimaryKeyConstraints(): array
    {
        return $this->tableConstraints->GetPrimaryKeyConstraints();
    }

    /**
     * Retrieves the foreign key constraints for the table.
     *
     * This method delegates the retrieval of foreign key constraints
     * to the `GetForeignKeyConstraints` method of the `tableConstraints` object.
     *
     * @return Tableconstraint[] A referenced array of foreign key constraints associated with the table.
     */
    public function &GetForeignKeyConstraints(): array
    {
        return $this->tableConstraints->GetForeignKeyConstraints();
    }

    /**
     * Retrieves all prepared table schemas.
     *
     * This method returns an array of all the prepared table schemas
     * that have been stored in the static property `$PREPARED_TABLESCHEMAS`.
     *
     * @return array An array of prepared table schemas.
     */
    public static function GetAllPreparedTableschemas(): array
    {
        return self::$PREPARED_TABLESCHEMAS;
    }

    /**
     * Retrieves a prepared table schema by its key.
     *
     * @param string $key The key associated with the prepared table schema.
     * @return Tableschema The prepared table schema corresponding to the provided key.
     */
    public static function GetPreparedTableschema(string $key): Tableschema
    {
        return self::$PREPARED_TABLESCHEMAS[$key];
    }

    /**
     * Checks if the table schema with the given key is already prepared.
     *
     * @param string $key The key of the table schema to check.
     * @return bool True if the table schema is already prepared, false otherwise.
     */
    public static function IsTableschemaAlreadyPrepared(string $key): bool
    {
        return isset(self::$PREPARED_TABLESCHEMAS[$key]);
    }

    /**
     * Retrieves the irregular entries.
     *
     * This method returns an array of irregular entries. If the irregular entries
     * are not already cached in the static property `$IRREGULARS`, it initializes
     * the cache by calling `GetIrregularsDictionary()` method.
     *
     * @return array The array of irregular entries.
     */
    public static function GetIrregulars() : array
    {
        return self::$IRREGULARS ?? self::$IRREGULARS = self::GetIrregularsDictionary();
    }

    /**
     * Retrieves the irregular value associated with the given key.
     *
     * This method looks up the provided key in the irregulars dictionary and returns
     * the corresponding value if it exists. If the key is not found, it returns null.
     *
     * @param string $key The key to look up in the irregulars dictionary.
     * @return string|null The irregular value associated with the key, or null if the key is not found.
     */
    public static function GetIrregular(string $key) : string|null
    {
        return self::GetIrregularsDictionary()[$key] ?? null;
    }

    /**
     * Retrieves the value associated with the specified key from the prepared dictionary.
     *
     * @param string $key The key to look up in the dictionary.
     * @return string|null The value associated with the key, or null if the key does not exist.
     */
    public static function GetDictionaryValue(string $key) : string|null
    {
        return self::$databaseSearchKeysDictionary[$key] ?? null;
    }

    /**
     * Checks if the given key exists in the prepared dictionary.
     *
     * @param string $key The key to check in the dictionary.
     * @return bool Returns true if the key exists in the dictionary, false otherwise.
     */
    public static function CheckDictionaryHasValue(string $key) : bool
    {
        return isset(self::$databaseSearchKeysDictionary[$key]);
    }
}