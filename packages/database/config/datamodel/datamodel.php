<?php
namespace Database;

use Attribute;
use Composer\Composer;
use Database\Database;

include_once 'schemas/tableschema.php';
include_once 'datafetch.php';
include_once 'dcollection.php';
include_once 'searchinghelper.php';

use Database\Attributes\Attributes;
use Database\Attributes\Get;
use Database\Attributes\Set;
use Database\Attributes\Foreign;

/* VERY IMPORTANT: ***Database*** class must be resolved correctly before using this class.*/



/**
 * Datamodel Class
 *
 * An abstract base class for all data models in the application. This class provides a comprehensive
 * framework for managing database records as PHP objects, including CRUD operatio ns, data validation,
 * schema management, and ID handling.
 *
 * ## Key Features:
 *
 * ### Initialization & Schema Management
 * - Automatic schema deduction from class names
 * - Lazy loading and caching of table schemas to improve performance
 * - Support for single and composite primary keys
 * - Automatic database connection based on file location
 *
 * ### Data Operations (CRUD)
 * - **Fetch**: Multiple fetch methods including by ID, by column value, ranges, random items, and search algorithms
 * - **Save**: Insert and update operations with support for selective column saving
 * - **Delete**: Safe deletion with database synchronization checks
 * - **Query**: Complex searching with normalization algorithms
 *
 * ### Data Integrity
 * - ID schema validation for primary keys
 * - Working ID tracking to detect modifications to the primary key
 * - Database synchronization checks before deletion
 * - Support for auto-increment primary keys
 *
 * ### Property Management
 * - Automatic deduction of working properties from class properties
 * - Null property filtering options
 * - Selective property setting and retrieval
 * - Data model to array conversion and JSON serialization
 *
 * ### Method Control
 * - Enable/disable specific methods for security
 * - Method availability checking
 *
 * ### Data Collections
 * - Support for returning collections of data models
 * - Aggregation operations (e.g., sum on columns)
 *
 * ## Property Types:
 *
 * - **WORKING_ID**: The ID as stored in the database (differs from model ID if modified before saving)
 * - **IDschema**: Validates and manages primary key(s) for the table
 * - **tableSchema**: Contains table structure information (columns, constraints, etc.)
 * - **workingProperties**: Array of property names that map to non-ID database columns
 * - **disabledDatamodelMethods**: Methods that are disabled for this instance
 *
 * ## Usage Example:
 *
 * ```php
 * class User extends Datamodel {
 *     public string|null $name;
 *     public string|null $email;
 *     public int|null $age;
 * }
 *
 * // Fetch and modify
 * $user = new User();
 * $user->FetchById(1);
 * $user->name = "John Doe";
 * $user->SaveAtDB();
 *
 * // Fetch with search
 * $user = new User();
 * $user->Where('email', 'john@example.com');
 *
 * // Fetch multiple
 * $users = new User();
 * $allUsers = $users->FetchAll();
 * $allUsers->Render(); // Renders an HTML table of all users
 * ```
 *
 * ## Important Notes:
 *
 * - All instances of the same data model class share the same schema and database connection
 * - The `FetchAll()` method should be used cautiously on large tables
 * - ID modifications are tracked and can prevent unintended overwrites
 * - Methods can be disabled per instance using `DisableMethod()`
 * - Prepared datamodels are cached for performance optimization
 *
 * @abstract
 * @uses \Thrower Provides exception throwing functionality
 */
abstract class Datamodel 
{
    //DEPENDS
    protected Database $database;

    //IMPLEMENTS
    protected IDschema|null $WORKING_ID; //This is the working ID like it is in the database, it differs from Tableschema ID schema when the Datamodel modifies the ID before saving it to the database.
    protected IDschema|null $IDschema; //The ID schema of the table, deduced from the table schema. It is used to set and get the ID of the model, and to check if the ID is valid.
    
    //&STATIC IMPLEMENTS (shared by all datamodels of the same class)
    protected Tableschema $tableSchema;
    protected Datafetch $datafetcher;
    private \ReflectionClass $reflectionSchema;
    protected string $DATAMODEL_NAME;
    protected string $TABLE_NAME;
    protected array $workingProperties = []; //The working properties are the properties declared in the datamodel that correspond to the non-ID columns in the database table. A worrking property has (by default) the same name as the column it corresponds to.
    protected array $foreignValues = []; //Array of ForeignValue objects that represent the foreign keys of the table and their corresponding datamodels.
    protected array $disabledDatamodelMethods = [];

    private static array $PREPARED_DATAMODELS = [];

    public function __construct()
    {
        $this->__init();
    }

    /**
     * Initializes the data model by setting up the schema and table information.
     * 
     * This method performs the following steps:
     * 1. Sets the `DATAMODEL_NAME` to the class name.
     * 2. Checks if the data model is already saved. If so, loads the schema from the prepared data model.
     * 3. If the data model is not saved, deduces the schema from the class name.
     * 4. Retrieves the database based on the parent directory of the class file.
     * 5. Creates a new `Tableschema` object for the class.
     * 6. Sets the `TABLE_NAME` from the table schema.
     * 7. Deduces the working properties of the data model.
     * 8. Saves the data model as a prepared data model.
     * 
     * @return void
     */
    protected final function __init()
    {
        $this->WORKING_ID = null;
        $this->DATAMODEL_NAME = get_class($this);

        if($this->IsDatamodelSaved($this->DATAMODEL_NAME))
        {
            $this->LoadSchemaFromPrepared($this->DATAMODEL_NAME);
            return;
        }

        //Deduce schema from class name if not found in prepared datamodels.
        $this->reflectionSchema = \Attributes\CachePerformance::GetReflectionClassForObject($this);
        $parentDir = basename(dirname($this->reflectionSchema->getFileName()));
        $this->database = Database::RetrieveDatabase($parentDir);

        if(!$this->database->connect())
        {
            Composer::Throw("Cannot initialize datamodel `" . $this->DATAMODEL_NAME . "`. Database connection failed. Check if database <b>" . $parentDir . "</b> exists and is accessible.", 1001);
        }

        $this->tableSchema = new Tableschema($this->database, $this->DATAMODEL_NAME);
        $this->TABLE_NAME = $this->tableSchema->GetTableName();
        $this->workingProperties = $this->DeduceWorkingProperties();
        $this->IDschema = new IDschema($this->GetTableschema()->GetTableConstraints());
        $this->datafetcher = new Datafetch($this->tableSchema, $this);
    

        $this->SaveDatamodelAsPrepared($this->DATAMODEL_NAME);
    }


    
    /**
     * Deduces the working properties of the current class by intersecting its property names
     * with the non-ID column names from the table schema.
     * 
     * Working properties are the properties declared in the datamodel that correspond to the non-ID columns in the database table.
     * A worrking property has (by default) the same name as the column it corresponds to.
     *
     * @return array Array of property names that correspond to non-ID columns in the table schema.
     */
    private function DeduceWorkingProperties() : array
    {
        $properties = $this->reflectionSchema->getProperties(); //All properties from this class, including inherited ones.
        $properties = array_map(function($item) {
            return $item->getName();
        }, $properties); //Get only the names of the properties.

        $NonIDcolumns = $this->tableSchema->GetNonIDColumnNames(); //Get all non ID table column names.

        return array_intersect($properties, $NonIDcolumns); //Intersect the properties with the non ID columns to get the working properties.
    }

    /**
     * Disables a specified method in the data model.
     *
     * @param string $method The name of the method to disable.
     */
    protected function DisableMethod(string $method)
    {
        $this->disabledDatamodelMethods[] = $method;
    }

    /**
     * Enables a previously disabled data model method.
     *
     * This method searches for the specified method in the list of disabled data model methods.
     * If found, it removes the method from the list, effectively enabling it.
     *
     * @param string $method The name of the method to enable.
     */
    protected function EnableMethod(string $method)
    {
        $index = array_search($method, $this->disabledDatamodelMethods);
        if($index !== false)
        {
            unset($this->disabledDatamodelMethods[$index]);
        }
    }

    /**
     * Checks if a given method is enabled.
     *
     * This function determines whether a specified method is enabled by checking
     * if it is not present in the list of disabled data model methods.
     *
     * @param string $method The name of the method to check.
     * @return bool Returns true if the method is enabled, false otherwise.
     */
    public function IsMethodEnabled(string $method) : bool
    {
        return !in_array($method, $this->disabledDatamodelMethods);
    }

    /**
     * Throws an exception if the calling method is disabled for this data model.
     *
     * This method checks the backtrace to determine the name of the method that called it.
     * If the calling method is disabled, an exception is thrown.
     * After the check, the method exits the script.
     *
     * @throws \Exception if the calling method is disabled.
     */
    protected function ThrowableIfMethodDisabled() : void
    {
        //Deduce the method name where this method is being called
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $method = $backtrace[1]['function'];

        if(!$this->IsMethodEnabled($method))
        {
           Composer::Throw("Method $method is disabled for this data model.");

            //Exit the method where this method is being called
            exit();
        }
    }


    
    /**
___________     __         .__      ___________                   __  .__                      
\_   _____/____/  |_  ____ |  |__   \_   _____/_ __  ____   _____/  |_|__| ____   ____   ______
 |    __)/ __ \   __\/ ___\|  |  \   |    __)|  |  \/    \_/ ___\   __\  |/  _ \ /    \ /  ___/
 |     \\  ___/|  | \  \___|   Y  \  |     \ |  |  /   |  \  \___|  | |  (  <_> )   |  \\___ \ 
 \___  / \___  >__|  \___  >___|  /  \___  / |____/|___|  /\___  >__| |__|\____/|___|  /____  >
     \/      \/          \/     \/       \/             \/     \/                    \/     \/ 

     */
    
    /**
     * Extracts and returns an array containing only the primary key columns
     * from the provided data array.
     *
     * This method filters the input array to include only the keys that match
     * the primary key column names defined in the ID schema.
     *
     * @param array $data The input associative array to filter.
     * 
     * @return array An associative array containing only the primary key columns
     *               from the input data.
     */
    private function GetIDColumnsFromArray(array $data) : array
    {
        // $data is key-value pairs of column names and values, we need to filter only the ones that are primary keys according to the IDschema
        $idColumnNames = $this->GetIDSchema()->GetAllColumns();

        $idColumns = array_filter($data, function($key) use ($idColumnNames) {
            return in_array($key, $idColumnNames);
        }, ARRAY_FILTER_USE_KEY);

        return $idColumns;
    }

    /**
     * Sets the properties of the current object using the fetched data.
     * This method should only be called by a self-fetch method.
     *
     * @param array|null $data The fetched data to set the properties.
     * @return self|null Returns the current object if data is provided, otherwise null.
     */
    private function SetByFetchedData(array|null $data) : self|null
    {
        //Generic private method to set the properties of the current object by the fetched data
        //This must be called only by a self fetch method
        if($data==null || !$data || empty($data))
        {
            return null;
        }


        $this->SetID($this->GetIDColumnsFromArray($data));
        $this->WORKING_ID = clone $this->GetIDSchema();

        $this->SetProperties($data);

        return $this;
    }

    /**
     * Converts an array of collection items into an array of datamodel objects.
     *
     * This method takes an array of items, clones the current datamodel instance
     * for each item, populates it with the item's data using the `SetByFetchedData` method,
     * and returns an array of these datamodel objects. If the input array is null, empty,
     * or invalid, the method returns null.
     *
     * @param array|null $items An array of collection items or null.
     * @return DCollection|null An array of datamodel objects or null if the input is invalid.
     */
    private function GetDatamodelObjectsFromCollectionArray(array|null $items) : DCollection|null
    {
        if($items==null || !$items || empty($items))
        {
            return null;
        }

        $dcollection = new DCollection(get_class($this));
        $dcollection->addArrayData($items);

        return $dcollection;
    }

    /**
     * 

.dP"Y8 888888 88     888888 
`Ybo." 88__   88     88__   
o.`Y8b 88""   88  .o 88""   
8bodP' 888888 88ood8 88     
     */


    /**
     * Mutates the current object.
     * Fetches a record by its ID and populates the current instance with the fetched data.
     *
     * @param IDschema|string|array $id The ID of the record to fetch. It can be:
     *                                  - An instance of IDschema: Represents the primary key schema for the table.
     *                                  - A string: Used for tables with a single primary key column (e.g., numeric or string ID).
     *                                  - An array: Used for tables with compound primary keys. The array should have key-value pairs
     *                                    where the keys are the column names of the primary keys, and the values are the corresponding values.
     *
     * @return self|null Returns the current instance populated with the fetched data, 
     *                   or null if no record is found.
     * 
     * @example // Fetch a record by its numeric ID.
     * ```php
     * $model->FetchById(123);
     * ```
     * 
     * @example // Fetch a record by its string ID.
     * ```php
     * $model->FetchById('abc123');
     * ```
     * 
     * @example // Fetch a record by its compound primary key.
     * ```php
     * $model->FetchById(['column1' => 'value1', 'column2' => 123]);
     * ```
     */
    public function FetchById(IDschema|string|array $id): self|null
    {
        $item = $this->datafetcher->FetchItemByID($id);
        return $this->SetByFetchedData($item);
    }

    /**
     * Mutates the current object.
     * Fetches the first record from the database table that matches the specified column value.
     *
     * @param string $column The name of the column to search.
     * @param string $value The value to search for in the specified column.
     * @return self|null Returns an instance of the current class with the fetched data, or null if no matching record is found.
     */
    public function Where(string $column, string $value): self|null
    {
        $item = $this->datafetcher->FirstWhere($column, $value);
        return is_null($item) ? null : $this->SetByFetchedData($item);
    }

    /**
     * Fetches a range of items from the database table based on a starting ID and a limit.
     *
     * This method requires the table to have a single and numeric primary key.
     * If the table does not meet this requirement, an exception will be thrown.
     *
     * @param int $startingID The starting ID from which to fetch items.
     * @param int $limit The maximum number of items to fetch.
     * @return DCollection|null Returns a collection of datamodel objects if items are found, otherwise null.
     * @throws \Exception If the table does not have a single and numeric primary key.
     */
    public function FetchInRange(int $startingID, int $limit) : DCollection|null
    {
        //Needs a single and numeric primary key
        if(!$this->GetIDSchema()->IsNumeric())
        {
            Composer::Throw("Table {$this->GetTableschema()->GetTableName()} must have a single and numeric primary key to use this method.");
            return null;
        }

        $items = $this->datafetcher->InRange($startingID, $limit);
        return $this->GetDatamodelObjectsFromCollectionArray($items);
    }

    /**
     * Fetches a random item from the database table defined by the table schema.
     *
     * @return self|null Returns the current object populated with the fetched data,
     *                   or null if no data was fetched.
     */
    public function FetchRandom(): self|null
    {
        $item = $this->datafetcher->Randoms(1);
        return $this->SetByFetchedData($item[0] ?? null);
    }

    /**
     * Fetches a limited number of random items from the database.
     *
     * This method retrieves a specified number of random items from the database
     * table defined by the table schema. It then creates a new instance of the
     * current class for each item, sets the data for each instance, and returns
     * an array of these instances.
     *
     * @param int $limit The number of random items to fetch.
     * @return DCollection|null Returns a collection of datamodel objects if items are found, otherwise null.
     */
    public function FetchRandoms(int $limit) : DCollection|null
    {
        $items = $this->datafetcher->Randoms($limit);
        return $this->GetDatamodelObjectsFromCollectionArray($items);
    }

    /**
     * Fetches all records from the database table and returns them as an array of data models.
     * 
     * WARNING: This method should not be used for large tables as it fetches all records at once.
     *
     * @return DCollection|null An array of datamodel objects if records are found, or null if no records exist.
     */
    public function FetchAll(): DCollection|null
    {
        //WARNING: This method should not be used for large tables
        $items = $this->datafetcher->AllRows();
        return $this->GetDatamodelObjectsFromCollectionArray($items);
    }

    /**
     * Retrieves all items from the database that match the specified column and value,
     * and converts them into an array of data model objects.
     *
     * @param string $column The name of the column to filter by.
     * @param string $value The value to match in the specified column.
     * @return DCollection|null Returns a collection of datamodel objects if matches are found, or null if no matches exist.
     */
    public function AllWhere(string $column, string $value): DCollection|null
    {
        $items = $this->datafetcher->AllWhere($column, $value);
        return $this->GetDatamodelObjectsFromCollectionArray($items);
    }

    /**
     * Mutates the current object.
     * 
     * Fetches a single record from the database table based on a normalized search algorithm.
     *
     * This method uses the NormalizeAlgorithmSimple function from the SearchingHelper class
     * to perform a search on the specified column with the given query. It returns the first
     * matching record or null if no match is found.
     *
     * @param string $column The name of the column to search.
     * @param string $query The search query.
     * @return self|null The fetched record as an instance of the current class, or null if no match is found.
     */
    public function FetchBySearchNormalizeAlgorithmSimple(string $column, string $query): self|null
    {
        $items = SearchingHelper::NormalizeAlgorithmSimple($this->tableSchema, $column, $query, 1);
        return $this->SetByFetchedData($items[0] ?? null);
    }

    /**
     * Mutates the current object.
     * 
     * Fetches a single record from the database table based on a complex normalized search algorithm.
     *
     * This method uses the ComplexNormalizeAlgorithm function from the SearchingHelper class
     * to perform a search on the specified column with the given query. It returns the first
     * matching record or null if no match is found.
     *
     * @param string $column The name of the column to search.
     * @param string $query The search query.
     * @return self|null The fetched record as an instance of the current class, or null if no match is found.
     */
    public function FetchByComplexNormalizeAlgorithm(string $column, string $query): self|null
    {
        $items = SearchingHelper::ComplexNormalizeAlgorithm($this->tableSchema, $column, $query, 1);
        return $this->SetByFetchedData($items[0] ?? null);
    }

    /**
     * Fetches an array of items by applying a complex normalization algorithm to the specified column and query.
     *
     * This method utilizes the `ComplexNormalizeAlgorithm` from the `SearchingHelper` class to process
     * the data based on the provided table schema, column, query, and optional limit.
     *
     * @param string $column The name of the column to apply the normalization algorithm on.
     * @param string $query The search query to be used in the normalization process.
     * @param int $limit Optional. The maximum number of items to return. Defaults to 0 (no limit).
     * 
     * @return array|null Returns an array of items if found, or null if no items match the criteria.
     */
    public function FetchArrayByComplexNormalizeAlgorithm(string $column, string $query, int $limit=0, array $whereClauses=[]): array|null
    {
        $items = SearchingHelper::ComplexNormalizeAlgorithm($this->tableSchema, $column, $query, $limit, $whereClauses);
        return $items;
    }

    /**
     * Calculates the sum of all values in the specified column for the current table schema.
     *
     * @param string $column The name of the column to sum.
     * @return float|int The total sum of the column values.
     */
    public function SummatoryOnColumn(string $column): float|int
    {
        return $this->datafetcher->Sum($column);
    }

    /**
     * 
________         .__          __           ___________                   __  .__                      
\______ \   ____ |  |   _____/  |_  ____   \_   _____/_ __  ____   _____/  |_|__| ____   ____   ______
 |    |  \_/ __ \|  | _/ __ \   __\/ __ \   |    __)|  |  \/    \_/ ___\   __\  |/  _ \ /    \ /  ___/
 |    `   \  ___/|  |_\  ___/|  | \  ___/   |     \ |  |  /   |  \  \___|  | |  (  <_> )   |  \\___ \ 
/_______  /\___  >____/\___  >__|  \___  >  \___  / |____/|___|  /\___  >__| |__|\____/|___|  /____  >
        \/     \/          \/          \/       \/             \/     \/                    \/     \/ 
     */


    /**
     * Deletes the current model from the database.
     *
     * @return bool Returns true if the deletion was successful, false otherwise.
     *
     * @throws Exception If the method is disabled.
     *
     * The method performs the following checks before attempting deletion:
     * 1. Ensures the model is updated to the database.
     * 2. Ensures the primary key is resolved.
     *
     * The deletion is performed using a prepared statement to prevent SQL injection.
     * The success of the deletion is determined by checking the row count affected by the delete operation.
     */
    public function DeleteFromDatabase() : bool
    {
        $this->ThrowableIfMethodDisabled(__FUNCTION__); // *Throws exception if the method is disabled
        
        //Restriction 1. The model must be updated to the database.
        if(!$this->IsUpdated())
        {
            return false;
        }

        //Restriction 2. ID must be resolved
        if(!$this->GetIDschema()->WasResolved())
        {
            return false;
        }

        $deletion = $this->datafetcher->Delete();
        return $deletion;
    }


    /**
     * 
  _________                    ___________                   __  .__                      
 /   _____/____ ___  __ ____   \_   _____/_ __  ____   _____/  |_|__| ____   ____   ______
 \_____  \\__  \\  \/ // __ \   |    __)|  |  \/    \_/ ___\   __\  |/  _ \ /    \ /  ___/
 /        \/ __ \\   /\  ___/   |     \ |  |  /   |  \  \___|  | |  (  <_> )   |  \\___ \ 
/_______  (____  /\_/  \___  >  \___  / |____/|___|  /\___  >__| |__|\____/|___|  /____  >
        \/     \/          \/       \/             \/     \/                    \/     \/ 

     */

    private function SaveDataToDB(array $data) : array
    {
        $id = $this->GetIDSchema();

        // Updating
        if ($this->datafetcher->ItemExists($id))
        {
            $this->datafetcher->Update($data);

            //Store a copy of the IDschema object
            $this->WORKING_ID = clone $id;

            return $id->GetPairs();
        }
        
        // Inserting
        $insertionIDcolumns = $this->datafetcher->Insert($data);
        $id->SetID($insertionIDcolumns);

        //Store a copy of the IDschema object
        $this->WORKING_ID = clone $id;

        // Reload the data model with the new ID
        $this->FetchById($this->WORKING_ID);

        return $insertionIDcolumns;
    }

    public function SaveAtDB() : array
    {
        $result = $this->SaveDataToDB($this->GetDataAsArray());
        return $result;
    }


    public final function SaveAtDBExcludingColumns(string $excludedColumns, string $separator=";") : array
    {
        $data = $this->GetDataAsArray();
        $excludedColumns = explode($separator, $excludedColumns);

        foreach($excludedColumns as $column)
        {
            unset($data[$column]);
        }

        $result = $this->SaveDataToDB($data);
        return $result;
    }

    public final function SaveAtDBSelectedColumns(string $selectedColumns, string $separator=";") : array
    {
        $data = $this->GetDataAsArray();
        $selectedColumns = explode($separator, $selectedColumns);

        foreach(array_keys($data) as $column)
        {
            if (!in_array($column, $selectedColumns))
            {
                unset($data[$column]);
            }
        }

        $result = $this->SaveDataToDB($data);
        return $result;
    }



    /**
     * Checks if the current data model has been updated compared to the database.
     *
     * This method retrieves the current data model as an array and fetches the corresponding
     * data from the database using the model's ID. It then compares each value in the current
     * data model with the corresponding value in the database. If any value differs, the method
     * returns false, indicating that the data model has been updated. If all values are the same,
     * it returns true.
     *
     * @return bool True if the data model has not been updated, false otherwise.
     */
    public function IsUpdated() : bool
    {
        // If the ID is null, the item is not in the database
        if(is_null($this->GetID()))
        {
            return false;
        }

        $data = $this->GetDataAsArray();
        $dbData = $this->datafetcher->FetchFullItemById($this->GetID());
        
        // If the item is not in the database, it is not updated
        if (!$dbData)
        {
            return false;
        }

        // Compare each value in the data model with the corresponding value in the database
        foreach($data as $key => $value)
        {
            if ($value != $dbData[$key])
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if this item exists in the database by its ID.
     *
     * This method uses the table schema and the ID of the current object
     * to determine if the item exists in the database.
     *
     * @warning This method does not check if the item has been modified since it was fetched. If ID is modified, it will check for the new ID if some other item exists with the new ID.
     * @return bool Returns true if the item exists, false otherwise.
     */
    public function ExistsByID() : bool
    {
        return $this->datafetcher->ItemExists($this->GetID());
    }

    /**
     * Checks if an item exists in a specified column.
     *
     * This method verifies if a given value exists within a specified column of the table schema.
     * It first checks if the column exists in the table schema. If the column does not exist, 
     * it returns false. If the column exists, it proceeds to check if the value exists in the column.
     *
     * @param string $column The name of the column to check.
     * @param string $value The value to search for in the column.
     * @return bool True if the value exists in the column, false otherwise.
     */
    public function ExistItemInColumn(string $column, string $value) : bool
    {
        return $this->datafetcher->ValueExists($column, $value);
    }

    /**
     * Checks if the current ID is the working ID.
     *
     * This method compares the current ID obtained from the GetID() method
     * with the WORKING_ID to determine if they are the same.
     *
     * WORKING_ID is the working ID as it is in the database. It differs from 
     * $THIS_MODEL_ID when the Datamodel modifies the ID before saving it to the database.
     *
     * @return bool True if the current ID is the working ID and the item exists in the database, false otherwise.
     */
    public function IsIDWorking() : bool
    {
        return $this->GetWorkingID() !== null && $this->GetID() == $this->WORKING_ID->GetID() && $this->datafetcher->ItemExists($this->WORKING_ID);
    }

    /**
     * Converts the data model into an associative array.
     *
     * This method retrieves the working properties of the data model and constructs
     * an associative array where the keys are the property names and the values are
     * the corresponding property values. The primary key column is also included in
     * the array with its value.
     * 
     * @param bool $includeNullProperties Optional. If set to false, null properties will be excluded from the array. Default is true.
     *
     * @return array An associative array representing the data model.
     */
    protected final function GetDatamodelAsArray(bool $includeNullProperties=true) : array
    {
        $data = [];
        $properties = $this->GetWorkingProperties();
        $id = $this->GetID();


        //Then, set by the ID column keys, even if they are not working properties
        if(is_string($id))
        {
            $data[$this->GetIDSchema()->Single()->GetColumnName()] = $id;
        }
        elseif(is_array($id))
        {
            foreach($id as $key => $value)
            {
                $data[$key] = $value;
            }
        }


        //First, set data by all working properties
        foreach($properties as $property)
        {
            $data[$property] = $this->$property ?? null;
        }

        //Remove null properties if not required
        if(!$includeNullProperties)
        {
            $data = array_filter($data, function($value) {
                return !is_null($value);
            });
        }

        return $data;
    }

    /**
     * Retrieves the data of the current object as an associative array.
     *
     * The array will contain the primary key and all working properties of the object.
     * If a property is not set, it will be included in the array with a value of null.
     *
     * @param bool $includeNullProperties Optional. If set to true, null properties will be included in the array. Default is true.
     * 
     * @return array An associative array containing the object's data.
     */
    public function GetDataAsArray(bool $includeNullProperties=true) : array
    {
        return $this->GetDatamodelAsArray($includeNullProperties);
    }

    /**
     * Converts the data to a JSON string.
     *
     * This method retrieves the data as an array and then encodes it into a JSON string.
     * 
     * @param bool $includeNullProperties Optional. If set to true, null properties will be included in the JSON string. Default is false.
     *
     * @return string The JSON-encoded string representation of the data.
     */
    public function GetAsJSON(bool $includeNullProperties=false) : string
    {
        return json_encode($this->GetDataAsArray($includeNullProperties));
    }


    /**
     * Sets the properties of the current object based on the provided data array.
     *
     * This method first assigns values to the object's working properties if they exist in the data array.
     * Then, it updates the ID schema properties using the values from the data array, if present.
     *
     * @param array $data Associative array containing property values to set.
     *
     * @return void
     */
    public function SetProperties(array $data): void
    {
        //First, set non-ID working properties
        foreach($this->workingProperties as $property)
        {
            if (isset($data[$property]))
            {
                $this->$property = $data[$property];
            }
        }

        //Then, set the ID schema properties if they are present in the data array.
        $idSchema = $this->GetIDSchema();
        foreach($idSchema->GetAllColumns() as $idColumn)
        {
            if (isset($data[$idColumn]))
            {
                $idSchema->SetSingleColumn($idColumn, $data[$idColumn]);
            }
        }
    }


    /**
     * Sets all working properties to the specified value.
     *
     * This method iterates through all working properties and assigns the given value
     * to each one.
     * 
     * WARNING: ID properties are excluded from this operation and will not be modified.
     *
     * @param mixed $value The value to assign to all working properties
     * @return void
     */
    public function SetAllProperties(mixed $value) : void
    {
        foreach($this->workingProperties as $property) // Working properties does not include the ID properties, so they will not be set by this method.
        {
            $this->$property = $value;
        }
    }


    /**
     * Checks if the datamodel associated with the given key is saved.
     *
     * This method verifies if the datamodel identified by the provided key
     * exists in the PREPARED_DATAMODELS array and is not null.
     *
     * @param string $datamodelKey The key identifying the datamodel.
     * @return bool True if the datamodel is saved, false otherwise.
     */
    private function IsDatamodelSaved(string $datamodelKey) : bool
    {
        return isset(self::$PREPARED_DATAMODELS[$datamodelKey]) && !is_null(self::$PREPARED_DATAMODELS[$datamodelKey]);
    }

    /**
     * Saves the current datamodel instance into the prepared datamodels array using the provided key.
     *
     * @param string $datamodelKey The key to identify the datamodel in the prepared datamodels array.
     */
    private function SaveDatamodelAsPrepared(string $datamodelKey)
    {
        self::$PREPARED_DATAMODELS[$datamodelKey] = &$this;
    }

    /**
     * Loads the schema from a prepared datamodel using the provided key.
     *
     * @param string $datamodelKey The key to identify the prepared datamodel.
     * 
     * @return bool Returns true if the schema is successfully loaded.
     * 
     * @throws \Exception If no prepared datamodel is found with the given key.
     */
    private function LoadSchemaFromPrepared(string $datamodelKey) : bool
    {
        if (isset(self::$PREPARED_DATAMODELS[$datamodelKey]))
        {
            $datamodel = &self::$PREPARED_DATAMODELS[$datamodelKey];

            //Properties are passed by reference
            $this->InitializeDatamodelSchema(
                $datamodel->database,
                $datamodel->tableSchema,
                $datamodel->reflectionSchema,
                $datamodel->datafetcher,
                $datamodel->DATAMODEL_NAME,
                $datamodel->TABLE_NAME,
                $datamodel->workingProperties
            );
            
            $this->IDschema = new IDschema($datamodel->tableSchema->GetTableConstraints());

            return true;
        }
        else
        {
            Composer::Throw("No prepared datamodel found with key $datamodelKey");
            return false;
        }
    }

    /**
     * Retrieves the value of a specified property from the datamodel.
     *
     * This method enforces two restrictions:
     * 1. Only properties listed in `GetWorkingProperties()` method can be accessed.
     * 2. Private or protected properties require the #[Get] attribute to be accessible.
     *
     * @param string $property The name of the property to retrieve.
     * @return mixed The value of the requested property, or null if access is denied.
     *
     * @throws Exception If the property is not a working property or is not accessible.
     */
    public function get(string $property) : mixed
    {
        //Restriction 1. Cannot get properties that are not working properties
        if(!in_array($property, $this->workingProperties))
        {
            Composer::Throw("Trying to get $property which is not a working property of this datamodel.");
            return null;
        }

        $isPublic = $this->reflectionSchema->getProperty($property)->isPublic();

        //Restriction 2. Cannot get private/protected properties without #[Get] attribute
        if(!$isPublic && !\Attributes\Attributes::PropertyHasAttribute($this, $property, Get::class))
        {
            Composer::Throw("Property $property is not accessible. Try adding #[Get] attribute into this property to perform a get operation on it.");
            return null;
        }

        return \Attributes\Attributes::ProcessAttribute($this, $property, Get::class);
    }

    public function set(string $property, ...$set) : void
    {
        //Restriction 1. Cannot set properties that are not working properties
        if(!in_array($property, $this->workingProperties))
        {
            Composer::Throw("Trying to set $property which is not a working property of this datamodel.");
            return;
        }

        //Restriction 2. Cannot set private/protected properties without #[Set] attribute
        $isPublic = $this->reflectionSchema->getProperty($property)->isPublic();

        if(!$isPublic && !\Attributes\Attributes::PropertyHasAttribute($this, $property, Set::class))
        {
            Composer::Throw("Property $property is not accessible. Try adding #[Set] attribute into this property to perform a set operation on it.");
            return;
        }

        \Attributes\Attributes::ProcessAttribute($this, $property, Set::class, ...$set);
    }


    /**
  _________       __    __                       
 /   _____/ _____/  |__/  |_  ___________  ______
 \_____  \_/ __ \   __\   __\/ __ \_  __ \/  ___/
 /        \  ___/|  |  |  | \  ___/|  | \/\___ \ 
/_______  /\___  >__|  |__|  \___  >__|  /____  >
        \/     \/                \/           \/ 

     */

    /**
     * Sets the ID for the current model.
     *
     * This method validates the provided ID against the primary key schema of the table.
     * If the ID is not valid, an exception is thrown.
     *
     * @param string|null $id The ID to set for the model. Can be null.
     * @throws \Exception If the provided ID is not valid according to the table's primary key schema.
     */
    public function SetID(string|array|null $id)
    {
        $this->GetIDSchema()->SetID($id);
    }

    /**
     * Sets the model ID to null.
     *
     * This method is useful for resetting the primary key of the model,
     * especially when using auto-incrementing primary keys.
     *
     * @return void
     */
    public function SetNullID()
    {
        $this->GetIDSchema()->SetID(null); //Useful with autoincrement primary keys
    }

    /**
     * Sets a new unique ID for the current item.
     *
     * This method generates a new ID using the primary key schema of the table
     * and assigns it to the current item.
     * 
     * @param bool $forceNotNull Optional. If true, the new ID will be forced to be not null.
     *
     * @warning If another item is created in the database with the same ID as the 
     * new ID generated before saving, it will overwrite the existing item when saved.
     */
    public function SetNewItemID(bool $forceNotNull=false)
    {
        //Warning: If another item is created at the database with the same ID as the new ID generated before saving, it will overwrite it when saved.
        $this->GetIDSchema()->TrySetNewID($forceNotNull);
    }

    /**
     * Initializes the data model schema by reference with the provided parameters.
     * 
     * Sets all shared properties of the datamodel by reference, allowing multiple datamodel instances to share the same schema and database connection.
     *
     * This method does not create new instances of any of the parameters; it simply assigns the references to the existing instances passed as arguments.
     * 
     * @param Database $database The database instance.
     * @param Tableschema $tableSchema The table schema instance.
     * @param \ReflectionClass $reflectionSchema The reflection class instance for the schema.
     * @param Datafetch $datafetcher The data fetcher instance.
     * @param string $DATAMODEL_NAME The name of the data model.
     * @param string $TABLE_NAME The name of the table.
     * @param array $workingProperties The array of working properties.
     */
    private function InitializeDatamodelSchema(Database &$database, Tableschema &$tableSchema, \ReflectionClass &$reflectionSchema, Datafetch &$datafetcher, string &$DATAMODEL_NAME, string &$TABLE_NAME, array &$workingProperties)
    {
        $this->database = &$database;
        $this->tableSchema = &$tableSchema;
        $this->reflectionSchema = &$reflectionSchema;
        $this->datafetcher = &$datafetcher;
        $this->DATAMODEL_NAME = &$DATAMODEL_NAME;
        $this->TABLE_NAME = &$TABLE_NAME;
        $this->workingProperties = &$workingProperties;
    }

    /**
  ________        __    __                       
 /  _____/  _____/  |__/  |_  ___________  ______
/   \  ____/ __ \   __\   __\/ __ \_  __ \/  ___/
\    \_\  \  ___/|  |  |  | \  ___/|  | \/\___ \ 
 \______  /\___  >__|  |__|  \___  >__|  /____  >
        \/     \/                \/           \/ 

     */

    /**
     * Returns the ID specified for the current model.
     * 
     * This ID may differ from the actual ID in the database if the ID was modified before saving.
     *
     * @return string|array|null The ID of the model, string if the Primary Key is a single column, array if it is a composite key, or null if not set.
     */
    public function GetID() : string|array|null
    {
        return $this->GetIDSchema()->GetID();
    }

    /**
     * Retrieves the current working ID.
     *
     * The working ID is the ID as it is in the database. It differs from the model ID
     * when the Datamodel modifies the ID before saving it to the database.
     * 
     * @return IDschema|null Returns the working ID if set, or null otherwise.
     */
    public function GetWorkingID() : IDschema|null
    {
        return $this->WORKING_ID ?? null;
    }

    /**
     * Retrieves the working properties.
     *
     * @return array An unidimentional array containing the working properties.
     */
    public function GetWorkingProperties() : array
    {
        return $this->workingProperties;
    }

    /**
     * Checks if a property is in the working properties list.
     * 
     * Working properties are the properties that are considered for database operations. They are defined in the datamodel and can be used to determine which properties should be included when saving to the database or when converting the datamodel to an array.
     * If some property exists in the database table but it is not defined by the datamodel as a working property, it will not be included in the array representation of the datamodel.
     * 
     * WARNING: All Primary Key Properties are excluded from the working properties list, so they will not be checked by this method. To check if a property is a primary key property, use the IsIDColumn() method instead.
     * 
     * @param string $property The property name to check.
     * @return bool True if the property exists in the working properties array, false otherwise.
     */
    public function IsAWorkingProperty(string $property) : bool
    {
        return in_array($property, $this->workingProperties);
    }

    /**
     * Determines whether the given property is an ID column.
     *
     * @param string $property The property name to check.
     * @return bool True if the property is an ID column, false otherwise.
     */
    public function IsIDColumn(string $property) : bool
    {
        return in_array($property, $this->GetIDSchema()->GetAllColumns());
    }

    /**
     * Returns the database instance associated with this data model.
     *
     * @return Database The reference to the database instance.
     */
    public function &GetDatabase() : Database
    {
        return $this->database;
    }

    /**
     * Returns the deduced table schema for the data model.
     * 
     * The table schema is deduced from the class name of the data model.
     *
     * @return Tableschema The reference to the schema of the table.
     */
    public function &GetTableschema() : Tableschema
    {
        return $this->tableSchema;
    }

    /**
     * Retrieves the reflection schema of the current class.
     *
     * @return \ReflectionClass The reflection schema of the current class.
     */
    public function GetReflectionschema() : \ReflectionClass
    {
        return $this->reflectionSchema;
    }

    /**
     * Retrieves the Datafetcher instance associated with this object.
     *
     * @return Datafetch Reference to the Datafetcher instance.
     */
    public function &GetDatafetcher() : Datafetch
    {
        return $this->datafetcher;
    }

    /**
     * Retrieves the ID schema associated with the table schema.
     *
     * @return IDschema The reference to the ID schema object of the table.
     */
    public function &GetIDSchema() : IDschema
    {
        return $this->IDschema;
    }

    /**
     * Returns the deduced name of the table associated with this data model.
     * 
     * The table name is deduced from the class name of the data model.
     *
     * @return string The name of the table.
     */
    public function GetTableName() : string
    {
        return $this->TABLE_NAME;
    }

    /**
     * Returns the name of the data model.
     * 
     * The name of the datamodel is the class name of the data model.
     *
     * @return string The name of the data model.
     */
    public function GetDatamodelName() : string
    {
        return $this->DATAMODEL_NAME;
    }

    /**
     * Destroys the current instance by setting all properties to null.
     * This method will:
     * - Set all properties to null using SetAllProperties method.
     * - Set the model ID to null.
     * - Set the working ID to null.
     */
    public function Destroy()
    {
        $this->SetAllProperties(null);
    }
}