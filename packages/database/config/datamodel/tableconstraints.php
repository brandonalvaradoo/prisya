<?php
namespace Database;
use Database\Tableschema;
use Database\Tableconstraintschema;
require_once "schemas/tableschema.php";
require_once "schemas/tableconstraintschema.php";

class Tableconstraints
{
    protected Tableschema $tableSchema;

    /**
     * An array containing all the constraints for the table.
     *
     * @var array<Tableconstraintschema> An array of Tableconstraintschema objects.
     */
    protected array $allConstraints;

    /**
     * An array that holds references to the primary key constraints
     * associated with the table.
     *
     * @var array<Tableconstraintschema> An array of Tableconstraintschema objects representing primary key constraints.
     */
    protected array $primaryKeyConstraintReferences;

    /**
     * An array that holds the references for foreign key constraints.
     *
     * Each entry in the array is an instance of Tableconstraintschema,
     * representing a foreign key reference, including details such as
     * the referenced table and column.
     *
     * @var array<Tableconstraintschema> An array of Tableconstraintschema objects.
     */
    protected array $foreignKeyConstraintReferences;

    /**
     * Constructor for the TableConstraints class.
     *
     * @param TableSchema $tableSchema Reference to the table schema object.
     *                                 This object is used to resolve table constraints.
     *
     * @return void
     */
    public function __construct(Tableschema &$tableSchema)
    {
        $this->tableSchema = &$tableSchema;
        $this->ResolveTableConstraints();
    }

    /**
     * 
__________                __                 __             .___ .___        __                 _____                     
\______   \_______  _____/  |_  ____   _____/  |_  ____   __| _/ |   | _____/  |_  ____________/ ____\____    ____  ____  
 |     ___/\_  __ \/  _ \   __\/ __ \_/ ___\   __\/ __ \ / __ |  |   |/    \   __\/ __ \_  __ \   __\\__  \ _/ ___\/ __ \ 
 |    |     |  | \(  <_> )  | \  ___/\  \___|  | \  ___// /_/ |  |   |   |  \  | \  ___/|  | \/|  |   / __ \\  \__\  ___/ 
 |____|     |__|   \____/|__|  \___  >\___  >__|  \___  >____ |  |___|___|  /__|  \___  >__|   |__|  (____  /\___  >___  >
                                   \/     \/          \/     \/           \/          \/                  \/     \/    \/ 
    */
    
    /**
     * Retrieve all constraints and their associated column information for a specific table.
     *
     * This method fetches all constraints (e.g., primary keys, foreign keys, unique constraints) 
     * for a given table from the database schema and retrieves detailed information about the 
     * columns involved in these constraints. The constraints and their associated column details 
     * are returned as an array.
     *
     * @return array An array of constraints, where each constraint includes:
     *               - 'CONSTRAINT_NAME': The name of the constraint.
     *               - 'CONSTRAINT_TYPE': The type of the constraint (e.g., PRIMARY KEY, FOREIGN KEY).
     *               - 'POSITION_IN_UNIQUE_CONSTRAINT': The position of the column in the unique constraint (if applicable).
     *               - 'COLUMN_NAME': The name of the column involved in the constraint.
     *               - 'REFERENCED_TABLE_NAME': The name of the referenced table (for foreign keys).
     *               - 'REFERENCED_COLUMN_NAME': The name of the referenced column (for foreign keys).
     *
     * Notes:
     * - This method uses two separate queries to retrieve constraints and their column details, 
     *   which is more efficient than using a single JOIN query.
     * - The database connection is obtained from the table schema's associated database object.
     *
     * @throws \PDOException If there is an error executing the database queries.
     */
    protected function RetrieveAllConstraintsArrayFrom() : array
    {
        $db = $this->tableSchema->GetDatabase()->connect();
        $tableName = $this->tableSchema->GetTableName();
        $dbName = $this->tableSchema->GetDatabase()->GetDatabaseName();

        // 1. Retrieve all constraints for the table
        $getConstraints = "SELECT 
            tc.CONSTRAINT_NAME,
            tc.CONSTRAINT_TYPE
        FROM 
            INFORMATION_SCHEMA.TABLE_CONSTRAINTS AS tc
        WHERE 
            tc.TABLE_NAME = :tableName
            AND tc.TABLE_SCHEMA = :databaseName;
        ";

        $statement = $db->prepare($getConstraints);
        $statement->bindParam(':tableName', $tableName, \PDO::PARAM_STR);
        $statement->bindParam(':databaseName', $dbName, \PDO::PARAM_STR);
        $statement->execute();
        $constraints = $statement->fetchAll(\PDO::FETCH_ASSOC);

        // 2. Retrieve the columns and their info involved in the constraints
        $getColumns = "SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            POSITION_IN_UNIQUE_CONSTRAINT,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE
            TABLE_NAME = :tableName AND
            TABLE_SCHEMA = :databaseName
        ";

        $statement = $db->prepare($getColumns);
        $statement->bindParam(':tableName', $tableName, \PDO::PARAM_STR);
        $statement->bindParam(':databaseName', $dbName, \PDO::PARAM_STR);
        $statement->execute();
        $columns = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $allConstraints = array();
        
        // Combine the constraints and columns into a single array
        foreach ($constraints as $constraint)
        {
            $constraintName = $constraint['CONSTRAINT_NAME'];
            $constraintType = $constraint['CONSTRAINT_TYPE'];

            foreach ($columns as $column)
            {
                if ($column['CONSTRAINT_NAME'] === $constraintName)
                {
                    $allConstraints[] = array(
                        'CONSTRAINT_NAME' => $constraintName,
                        'CONSTRAINT_TYPE' => $constraintType,
                        'POSITION_IN_UNIQUE_CONSTRAINT' => $column['POSITION_IN_UNIQUE_CONSTRAINT'],
                        'COLUMN_NAME' => $column['COLUMN_NAME'],
                        'REFERENCED_TABLE_NAME' => $column['REFERENCED_TABLE_NAME'],
                        'REFERENCED_COLUMN_NAME' => $column['REFERENCED_COLUMN_NAME']
                    );
                }
            }
        }
        
        // 3. Return the constraints
        // This metology is more efficient than the JOIN quey method. The JOIN query method is slower.
        return $allConstraints;
    }

    /**
     * Resolves and initializes the table constraints for the current table schema.
     *
     * This method retrieves all constraints associated with the table schema,
     * processes them, and categorizes them into primary key and foreign key constraints.
     * It populates the following properties:
     * - `$this->allConstraints`: An array containing all table constraints.
     * - `$this->primaryKeyConstraintReferences`: An array containing references to primary key constraints.
     * - `$this->foreignKeyConstraintReferences`: An array containing references to foreign key constraints.
     *
     * @return void
     */
    protected function ResolveTableConstraints() : void
    {
        $this->allConstraints = array();
        $this->primaryKeyConstraintReferences = array();
        $this->foreignKeyConstraintReferences = array();

        $constraints = self::RetrieveAllConstraintsArrayFrom(); // Aprox. 0.009 seconds

        foreach($constraints as $constraint)
        {
            $tableconstraint = new Tableconstraintschema($constraint, $this->tableSchema);
            $this->allConstraints[] = $tableconstraint;

            if($tableconstraint->IsPrimaryKey())
            {
                $this->primaryKeyConstraintReferences[] = $tableconstraint; // Saves the reference to the primary key constraint
            }
            else if($tableconstraint->IsForeignKey())
            {
                $this->foreignKeyConstraintReferences[] = $tableconstraint; // Saves the reference to the foreign key constraint
            }
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
     * Retrieves all constraints associated with the table.
     *
     * @return array An array containing all the constraints.
     */
    public function &GetAllConstraints() : array
    {
        return $this->allConstraints;
    }

    /**
     * Retrieves the primary key constraint references for the table.
     *
     * @return Tableconstraintschema[] An array containing the primary key constraint references.
     */
    public function &GetPrimaryKeyConstraints() : array
    {
        return $this->primaryKeyConstraintReferences;
    }

    /**
     * Retrieves the foreign key constraints for the table.
     *
     * @return Tableconstraintschema[] An array containing the foreign key constraint references.
     */
    public function &GetForeignKeyConstraints() : array
    {
        return $this->foreignKeyConstraintReferences;
    }

    /**
     * Retrieves the total count of all constraints.
     *
     * @return int The number of constraints stored in the $allConstraints property.
     */
    public function GetAllConstraintsCount() : int
    {
        return count($this->allConstraints);
    }

    /**
     * Retrieves the count of primary key constraints.
     *
     * This method returns the total number of primary key constraint references
     * associated with the current table.
     *
     * @return int The count of primary key constraints.
     */
    public function GetPrimaryKeyConstraintsCount() : int
    {
        return count($this->primaryKeyConstraintReferences);
    }

    /**
     * Retrieves the count of foreign key constraints associated with the table.
     *
     * @return int The number of foreign key constraints.
     */
    public function GetForeignKeyConstraintsCount() : int
    {
        return count($this->foreignKeyConstraintReferences);
    }

    /**
     * Retrieves the column names that comprise the primary key constraint.
     *
     * @return string[] An array of column names that are part of the primary key constraint.
     *               Returns an empty array if no primary key constraints are defined.
     */
    public function GetPrimaryKeyColumnNames() : array
    {
        $columnNames = array();
        foreach ($this->primaryKeyConstraintReferences as $pkConstraint)
        {
            $columnNames[] = $pkConstraint->GetDefinitionColumnName();
        }
        return $columnNames;
    }

    /**
     * Retrieves the column names that are referenced by foreign key constraints.
     *
     * @return string[] An array of column names that are part of foreign key constraint definitions.
     */
    public function GetForeignKeyColumnNames() : array
    {
        $columnNames = array();
        foreach ($this->foreignKeyConstraintReferences as $fkConstraint)
        {
            $columnNames[] = $fkConstraint->GetDefinitionColumnName();
        }
        return $columnNames;
    }

    /**
     * Retrieves the table schema associated with this instance.
     *
     * @return Tableschema A reference to the table schema object.
     */
    public function &GetTableSchema() : Tableschema
    {
        return $this->tableSchema;
    }
}