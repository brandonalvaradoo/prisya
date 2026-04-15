<?php
namespace Database;

use Database\Tableschema;
use Database\Tablecolumnschema;
require_once "tableschema.php";
require_once "tablecolumnschema.php";

/**
 * Class Tableconstraintschema
 *
 * Represents the schema of a table constraint in a database. This class provides
 * methods to retrieve details about the constraint, such as its name, type, position,
 * and associated columns or tables. It supports various types of constraints, including
 * PRIMARY KEY, FOREIGN KEY, UNIQUE, and CHECK constraints.
 *
 * Properties:
 * - $constraintName: The name of the constraint.
 * - $constraintType: The type of the constraint (e.g., PRIMARY KEY, FOREIGN KEY).
 * - $ordinalPosition: The ordinal position of the constraint.
 * - $positionInUniqueConstraint: The position in the unique constraint (if applicable).
 * - $constraintCatalog: The catalog of the constraint.
 * - $checkClause: The check clause for the constraint (if applicable).
 * - $uniqueConstraintName: The name of the unique constraint (if applicable).
 * - $matchOption: The match option for foreign keys (if applicable).
 * - $updateRule: The update rule for foreign keys (if applicable).
 * - $deleteRule: The delete rule for foreign keys (if applicable).
 * - $definitionColumnschema: The schema of the column associated with the constraint.
 * - $referenceColumnschema: The schema of the referenced column (for foreign keys).
 * - $definitionTableschema: The schema of the table associated with the constraint.
 * - $referenceTableschema: The schema of the referenced table (for foreign keys).
 *
 * Methods:
 * - __construct(array $constraintArray, Tableschema &$definitionTableschema):
 *   Initializes the object with constraint details and table schema definition.
 * - SetFromConstraintArray(array $constraintArray, Tableschema &$definitionTableschema):
 *   Sets the properties of the constraint schema from the provided constraint array.
 * - GetConstraintName(): Retrieves the name of the constraint.
 * - GetConstraintType(): Retrieves the type of the constraint.
 * - IsPrimaryKey(): Determines if the constraint is a primary key.
 * - IsForeignKey(): Determines if the constraint is a foreign key.
 * - IsUnique(): Determines if the constraint is of type UNIQUE.
 * - IsCheck(): Determines if the constraint is of type CHECK.
 * - GetOrdinalPosition(): Retrieves the ordinal position of the constraint.
 * - GetPositionInUniqueConstraint(): Retrieves the position in the unique constraint.
 * - GetConstraintCatalog(): Retrieves the catalog name of the constraint.
 * - GetCheckClause(): Retrieves the check clause of the constraint.
 * - GetUniqueConstraintName(): Retrieves the name of the unique constraint.
 * - GetMatchOption(): Retrieves the match option for foreign keys.
 * - GetUpdateRule(): Retrieves the update rule for foreign keys.
 * - GetDeleteRule(): Retrieves the delete rule for foreign keys.
 * - GetDefinitionColumnschema(): Retrieves the schema of the column associated with the constraint.
 * - GetReferenceColumnschema(): Retrieves the schema of the referenced column (for foreign keys).
 * - GetDefinitionTableschema(): Retrieves the schema of the table associated with the constraint.
 * - GetReferenceTableschema(): Retrieves the schema of the referenced table (for foreign keys).
 */
class Tableconstraintschema
{
    private string $constraintName;
    private string $constraintType;
    private int|null $positionInUniqueConstraint;
    private string $constraintCatalog;
    private string|null $uniqueConstraintName;
    private string|null $matchOption;
    private string|null $updateRule;
    private string|null $deleteRule;

    private Tablecolumnschema $definitionColumnschema;
    private Tablecolumnschema|null $referenceColumnschema;
    private Tableschema $definitionTableschema;
    private Tableschema|null $referenceTableschema;

    /**
     * Constructor for the TableConstraintSchema class.
     *
     * Initializes the object by setting its properties based on the provided
     * constraint array and table schema definition.
     *
     * @param array $constraintArray An associative array containing the constraint details.
     * @param Tableschema $definitionTableschema A reference to the table schema definition object.
     */
    public function __construct(array $constraintArray, Tableschema &$definitionTableschema)
    {
        $this->SetFromConstraintArray($constraintArray, $definitionTableschema);
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
     * Sets the properties of the constraint schema from the provided constraint array.
     *
     * @param array $constraintArray An associative array containing constraint details.
     *                               Keys to include:
     *                               - 'CONSTRAINT_NAME': The name of the constraint.
     *                               - 'CONSTRAINT_TYPE': The type of the constraint.
     *                               - 'ORDINAL_POSITION': The ordinal position of the constraint.
     *                               - 'POSITION_IN_UNIQUE_CONSTRAINT': The position in the unique constraint (if applicable).
     *                               - 'CONSTRAINT_CATALOG': The catalog of the constraint.
     *                               - 'CHECK_CLAUSE': The check clause for the constraint (if applicable).
     *                               - 'UNIQUE_CONSTRAINT_NAME': The name of the unique constraint (if applicable).
     *                               - 'MATCH_OPTION': The match option for foreign keys (if applicable).
     *                               - 'UPDATE_RULE': The update rule for foreign keys (if applicable).
     *                               - 'DELETE_RULE': The delete rule for foreign keys (if applicable).
     *                               - 'COLUMN_NAME': The name of the column associated with the constraint.
     *                               - 'REFERENCED_TABLE_NAME': The name of the referenced table (for foreign keys).
     *                               - 'REFERENCED_COLUMN_NAME': The name of the referenced column (for foreign keys).
     * 
     * @param Tableschema $definitionTableschema A reference to the table schema definition.
     *
     * @return void
     */
    protected function SetFromConstraintArray(array $constraintArray, Tableschema &$definitionTableschema) : void
    {
        $this->constraintName = $constraintArray['CONSTRAINT_NAME'] ?? "UNKNOWN";
        $this->constraintType = $constraintArray['CONSTRAINT_TYPE'] ?? "UNKNOWN";
        $this->positionInUniqueConstraint = $constraintArray['POSITION_IN_UNIQUE_CONSTRAINT'] ?? null;
        $this->constraintCatalog = $constraintArray['CONSTRAINT_CATALOG'] ?? "UNKNOWN";
        $this->uniqueConstraintName = $constraintArray['UNIQUE_CONSTRAINT_NAME'] ?? null;
        $this->matchOption = $constraintArray['MATCH_OPTION'] ?? null;
        $this->updateRule = $constraintArray['UPDATE_RULE'] ?? null;
        $this->deleteRule = $constraintArray['DELETE_RULE'] ?? null;

        $this->definitionTableschema = $definitionTableschema;
        $this->definitionColumnschema = new Tablecolumnschema($definitionTableschema, $constraintArray['COLUMN_NAME']);

        if($this->IsForeignKey() && isset($constraintArray['REFERENCED_TABLE_NAME']) && isset($constraintArray['REFERENCED_COLUMN_NAME']))
        {
            $database = $definitionTableschema->GetDatabase();
            
            $this->referenceTableschema = Tableschema::Builder($database, $constraintArray['REFERENCED_TABLE_NAME']);
            $this->referenceColumnschema = new Tablecolumnschema($this->referenceTableschema, $constraintArray['REFERENCED_COLUMN_NAME']);
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
     * Retrieves the name of the constraint.
     *
     * @return string The name of the constraint.
     */
    public function GetConstraintName(): string
    {
        return $this->constraintName;
    }
    
    /**
     * Retrieves the type of the table constraint.
     *
     * @return string The type of the constraint.
     */
    public function GetConstraintType(): string
    {
        return $this->constraintType;
    }
    
    /**
     * Determines if the current constraint is a primary key.
     *
     * @return bool Returns true if the constraint type is "PRIMARY KEY", otherwise false.
     */
    public function IsPrimaryKey(): bool
    {
        return $this->constraintType == "PRIMARY KEY";
    }

    /**
     * Determines if the current table constraint is a foreign key.
     *
     * @return bool Returns true if the constraint type is "FOREIGN KEY", otherwise false.
     */
    public function IsForeignKey(): bool
    {
        return $this->constraintType == "FOREIGN KEY";
    }

    /**
     * Determines if the table constraint is of type "UNIQUE".
     *
     * @return bool Returns true if the constraint type is "UNIQUE", otherwise false.
     */
    public function IsUnique(): bool
    {
        return $this->constraintType == "UNIQUE";
    }

    /**
     * Determines if the current constraint type is a CHECK constraint.
     *
     * @return bool Returns true if the constraint type is "CHECK", otherwise false.
     */
    public function IsCheck(): bool
    {
        return $this->constraintType == "CHECK";
    }

    /**
     * Retrieves the position of the current column within a unique constraint.
     *
     * @return int The position of the column in the unique constraint.
     */
    public function GetPositionInUniqueConstraint(): int
    {
        return $this->positionInUniqueConstraint;
    }

    /**
     * Retrieves the catalog name associated with the table constraint.
     *
     * @return string The name of the constraint catalog.
     */
    public function GetConstraintCatalog(): string
    {
        return $this->constraintCatalog;
    }

    /**
     * Retrieves the name of the unique constraint.
     *
     * If the constraint is of type UNIQUE, a unique constraint name exists.
     * Otherwise, this will return null.
     *
     * @return string|null The name of the unique constraint if set, or null if not defined.
     */
    public function GetUniqueConstraintName(): string|null
    {
        return $this->uniqueConstraintName;
    }

    /**
     * Retrieves the match option associated with the table constraint.
     *
     * If the constraint is of type FOREIGN KEY, a match option exists.
     * Otherwise, this will return null.
     *
     * @return string|null The match option if set, or null if not defined.
     */
    public function GetMatchOption(): string|null
    {
        return $this->matchOption;
    }

    /**
     * Retrieves the update rule associated with the table constraint.
     *
     * If the constraint is of type FOREIGN KEY, an update rule exists.
     * Otherwise, this will return null.
     *
     * @return string|null The update rule if set, or null if not defined.
     */
    public function GetUpdateRule(): string|null
    {
        return $this->updateRule;
    }

    /**
     * Retrieves the delete rule associated with the table constraint.
     *
     * If the constraint is of type FOREIGN KEY, a delete rule exists.
     * Otherwise, this will return null.
     *
     * @return string|null The delete rule if set, or null if not defined.
     */
    public function GetDeleteRule(): string|null
    {
        return $this->deleteRule;
    }


    /**
     * Retrieves the definition column schema associated with the table.
     *
     * @return Tablecolumnschema The schema definition of the column.
     */
    public function &GetDefinitionColumnschema(): Tablecolumnschema
    {
        return $this->definitionColumnschema;
    }

    /**
     * Retrieves the schema of the reference column.
     *
     * If the constraint is of type FOREIGN KEY, a reference Tablecolumnschema exists.
     * Otherwise, this will return null.
     * 
     * @return Tablecolumnschema|null The schema of the reference column, or null if not defined.
     */
    public function &GetReferenceColumnschema(): Tablecolumnschema|null
    {
        return $this->referenceColumnschema;
    }

    /**
     * Retrieves the definition table schema.
     *
     * @return Tableschema The table schema definition associated with this instance.
     */
    public function &GetDefinitionTableschema(): Tableschema
    {
        return $this->definitionTableschema;
    }

    /**
     * Retrieves the reference table schema associated with this constraint.
     *
     * If the constraint is of type FOREIGN KEY, a reference Tableschema exists.
     * Otherwise, this will return null.
     *
     * @return Tableschema|null The reference table schema if set, or null if not defined.
     */
    public function &GetReferenceTableschema(): Tableschema|null
    {
        return $this->referenceTableschema;
    }

    /**
     * Retrieves the name of the column associated with the definition schema.
     *
     * @return string The name of the column.
     */
    public function GetDefinitionColumnName(): string
    {
        return $this->definitionColumnschema->GetColumnName();
    }

    /**
     * Retrieves the name of the reference column.
     *
     * @return string The name of the reference column associated with the schema.
     */
    public function GetReferenceColumnName(): string
    {
        return $this->referenceColumnschema->GetColumnName();
    }
}