<?php
namespace Database;

use LDAP\Result;
use Composer\Composer;

include_once 'datamodel.php';

class DCollection implements \IteratorAggregate, \ArrayAccess
{
    /** @var Datamodel[] */
    private array $items = [];
    /** @var string The class name of the datamodel instances stored in the collection */
    private string $datamodelClass;
    /** @var Datamodel The base model instance for the collection */
    private Datamodel $baseModel;

    public function __construct(string $datamodelClass)
    {
        // Restriction 1. Ensure that the provided class name is a valid class that extends Datamodel
        if(!is_subclass_of($datamodelClass, Datamodel::class))
        {
            Composer::Throw("Invalid datamodel class. Expected a class that extends " . Datamodel::class);
        }

        $this->items = [];
        $this->datamodelClass = $datamodelClass;
        $this->baseModel = new $datamodelClass();
    }

    /**
     * Adds a Datamodel instance to the collection.
     *
     * @param Datamodel $datamodel The datamodel instance to add.
     * 
     * @throws Exception If the provided datamodel is not of the expected class type.
     */
    public function add(Datamodel $datamodel): void
    {
        // Restriction 1. Ensure that the datamodel being added is of the correct class type
        if (get_class($datamodel) !== $this->datamodelClass)
        {
            Composer::Throw("Invalid datamodel type. Expected instance of " . $this->datamodelClass);
        }

        $this->items[] = $datamodel;
    }

    /**
     * Adds multiple datamodel instances to the collection.
     *
     * Iterates over the provided array of datamodels and adds each to the collection.
     * 
     * Restriction:
     * - Ensures that each datamodel in the array is an instance of the expected class type.
     * - Throws an exception if any datamodel does not match the required class.
     *
     * @param Datamodel[] $datamodels Array of datamodel instances to add.
     * @throws Exception If any datamodel is not of the expected class type.
     * @return void
     */
    public function addRange(array $datamodels): void
    {
        foreach ($datamodels as $datamodel)
        {
            // Restriction 1. Ensure that each datamodel being added is of the correct class type
            if (get_class($datamodel) !== $this->datamodelClass)
            {
                Composer::Throw("Invalid datamodel type in range. Expected instance of " . $this->datamodelClass);
            }

            $this->items[] = $datamodel;
        }
    }

    /**
     * Adds array data to the collection by converting each array element into a model instance.
     *
     * @param array $dataArray The array of data to be added to the collection.
     *                         Each element should be an array of properties, not an instance of the model class.
     * @param string $modelClass The fully qualified class name of the model to instantiate.
     *                           Each array element will be converted to an instance of this class.
     * @return void
     */
    public function addArrayData(array $dataArray): void
    {
        foreach ($dataArray as $data)
        {
            if (is_array($data))
            {
                $datamodel = new ($this->datamodelClass)();
                $datamodel->SetProperties($data);
                $this->items[] = $datamodel;
            }
        }
    }

    /**
     * Retrieve a data model from the collection by index.
     *
     * @param int $index The index of the item to retrieve.
     * @return Datamodel|null The data model at the specified index, or null if the index does not exist.
     */
    public function get(int $index): ?Datamodel
    {
        return $this->items[$index] ?? null;
    }

    /**
     * Remove an item from the collection at the specified index.
     *
     * @param int $index The index of the item to remove
     * @return void
     */
    public function remove(int $index): void
    {
        if (isset($this->items[$index]))
        {
            array_splice($this->items, $index, 1);
        }
    }

    /**
     * Clears all items from the collection.
     *
     * Removes all elements from the collection by resetting the items array to empty.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->items = [];
    }

    /**
     * Finds the first item in the collection that matches the given callback condition.
     *
     * @param callable $callback A callback function that receives each item and returns a boolean.
     *                            The callback should return true if the item matches the condition.
     *
     * @return ?Datamodel The first item that matches the callback condition, or null if no match is found.
     */
    public function find(callable $callback): ?Datamodel
    {
        foreach ($this->items as $item)
        {
            if ($callback($item))
            {
                return $item;
            }
        }
        return null;
    }

    /**
     * Filters the collection items based on a callable condition.
     *
     * Iterates through all items in the collection and adds those items
     * to a new collection if they satisfy the condition defined by the callback.
     *
     * @param callable $callback A callback function that receives an item and returns
     *                           a boolean value. Items for which the callback returns
     *                           true are included in the filtered collection.
     *
     * @return DCollection A new DCollection instance containing only the items
     *                     that passed the filter condition.
     */
    public function filter(callable $callback): DCollection
    {
        $filteredCollection = new DCollection($this->datamodelClass);
        foreach ($this->items as $item)
        {
            if ($callback($item))
            {
                $filteredCollection->add($item);
            }
        }
        return $filteredCollection;
    }

    /**
     * Applies a callback function to each item in the collection and returns a new collection with the results.
     *
     * @param callable $callback The function to apply to each item. Should accept a Datamodel instance
     *                           and return a Datamodel instance or null.
     * @return DCollection A new DCollection instance containing only the items that were successfully
     *                     mapped (i.e., items for which the callback returned a Datamodel instance).
     */
    public function map(callable $callback): DCollection
    {
        $mappedCollection = new DCollection($this->datamodelClass);
        foreach ($this->items as $item)
        {
            $mappedItem = $callback($item);
            if ($mappedItem instanceof Datamodel)
            {
                $mappedCollection->add($mappedItem);
            }
        }
        return $mappedCollection;
    }

    /**
     * Determine if an item exists in the collection that satisfies the given callback.
     *
     * @param callable $callback A function that accepts an item and returns a boolean value.
     *                           Return true if the item matches the condition, false otherwise.
     * @return bool True if at least one item in the collection satisfies the callback condition,
     *              false otherwise.
     */
    public function exists(callable $callback): bool
    {
        foreach ($this->items as $item)
        {
            if ($callback($item))
            {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the number of items in the collection.
     *
     * @return int The count of items.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Determines whether the collection is empty.
     *
     * @return bool Returns true if the collection contains no items; otherwise, false.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Returns an iterator for traversing the collection items.
     *
     * @return \Traversable An iterator for the collection items.
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * Converts the collection items to an array.
     *
     * @return array The array representation of the collection items.
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Converts each item in the collection to an associative array of its properties.
     *
     * Iterates over all items in the collection and calls the `GetDataAsArray()` method
     * on each item, returning an array of these arrays.
     *
     * @return array An array of associative arrays representing the properties of each item.
     */
    public function toPropertiesArray(): array
    {
        return array_map(function($item) {
            return $item->GetDataAsArray();
        }, $this->items);
    }

    /**
     * Retrieves the first item in the collection.
     *
     * @return Datamodel|null Returns the first Datamodel instance if available, or null if the collection is empty.
     */
    public function first(): ?Datamodel
    {
        return $this->items[0] ?? null;
    }

    /**
     * Retrieves the last item in the collection based on the highest index.
     *
     * This method is designed to work with collections that may have non-sequential or custom indexes.
     * It returns the item associated with the highest index, not necessarily the last item added to the internal array.
     *
     * @return Datamodel|null The last item in the collection, or null if the collection is empty.
     */
    public function last(): ?Datamodel
    {
        // Must be working with non-progressive indexes to ensure that the last item is correctly retrieved
        // For example, if items are added with specific offsets (e.g., $collection[5] = $item), the last item should be the one with the highest index, not necessarily the last one in the internal array.
        if (empty($this->items))
        {
            return null;
        }

        $lastIndex = max(array_keys($this->items));
        return $this->items[$lastIndex] ?? null;
    }

    /**
     * Returns the index of the specified Datamodel object within the collection.
     *
     * Iterates through the collection items and checks for strict equality with the provided Datamodel instance.
     * If found, returns the index of the item; otherwise, returns -1.
     *
     * @param Datamodel $datamodel The Datamodel instance to search for.
     * @return int The index of the Datamodel in the collection, or -1 if not found.
     */
    public function indexOf(Datamodel $datamodel): int
    {
        foreach ($this->items as $index => $item)
        {
            if ($item === $datamodel)
            {
                return $index;
            }
        }
        return -1;
    }

    /**
     * Checks if the given Datamodel instance exists in the collection.
     *
     * @param Datamodel $datamodel The Datamodel instance to search for.
     * @return bool Returns true if the Datamodel is found in the collection, false otherwise.
     */
    public function contains(Datamodel $datamodel): bool
    {
        return $this->indexOf($datamodel) !== -1;
    }

    /**
     * Retrieves the name of the data model class associated with this collection.
     *
     * @return string The fully qualified class name of the data model.
     */
    public function GetDatamodelClass(): string
    {
        return $this->datamodelClass;
    }

    /**
     * Retrieves the working properties from the base model.
     *
     * @return array An array containing the working properties of the base model.
     */
    public function GetWorkingProperties(): array
    {
        return $this->baseModel->GetWorkingProperties();
    }

    /*
    ________                              __  .__                      
\_____  \ ______   ________________ _/  |_|__| ____   ____   ______
 /   |   \\____ \_/ __ \_  __ \__  \\   __\  |/  _ \ /    \ /  ___/
/    |    \  |_> >  ___/|  | \// __ \|  | |  (  <_> )   |  \\___ \ 
\_______  /   __/ \___  >__|  (____  /__| |__|\____/|___|  /____  >
        \/|__|        \/           \/                    \/     \/ 
     */

    


    /*
     __________                       .___           .__                
\______   \ ____  ___________  __| _/___________|__| ____    ____  
 |       _// __ \/  _ \_  __ \/ __ |/ __ \_  __ \  |/    \  / ___\ 
 |    |   \  ___(  <_> )  | \/ /_/ \  ___/|  | \/  |   |  \/ /_/  >
 |____|_  /\___  >____/|__|  \____ |\___  >__|  |__|___|  /\___  / 
        \/     \/                 \/    \/              \//_____/  
     */

    /**
     * Mutates the collection by reversing the order of its items.
     *
     * This method modifies the collection in place by reversing the order
     * of the elements in the `$items` array.
     *
     * @return void
     */
    public function reverse(): void
    {
        $this->items = array_reverse($this->items);
    }

    /**
     * Mutates the collection by shuffling its items into a random order.
     *
     * This method randomizes the order of elements in the collection using PHP's
     * built-in shuffle function. The shuffling is done in-place, modifying the
     * collection directly.
     *
     * @return void
     */
    public function shuffle(): void
    {
        shuffle($this->items);
    }

    /**
     * Sorts the collection items based on a callback function.
     * 
     *
     * @param callable $callback A callback function that extracts the value to sort by from each item.
     *                           The callback receives an item and returns a comparable value.
     * 
     * @param bool $ascending Determines the sort order. True for ascending order (default),
     *                        false for descending order.
     * @return void
     */
    public function sortBy(callable $callback, bool $ascending = true): void
    {
        usort($this->items, function ($a, $b) use ($callback, $ascending) {
            $valueA = $callback($a);
            $valueB = $callback($b);

            if ($valueA == $valueB)
            {
                return 0;
            }

            // Use spaceship operator for comparison, adjusting for ascending or descending order
            return $ascending ? ($valueA <=> $valueB) : ($valueB <=> $valueA);
        });
    }

    /*
        _____                               _____                                    
  /  _  \___________________  ___.__. /  _  \   ____  ____  ____   ______ ______
 /  /_\  \_  __ \_  __ \__  \<   |  |/  /_\  \_/ ___\/ ___\/ __ \ /  ___//  ___/
/    |    \  | \/|  | \// __ \\___  /    |    \  \__\  \__\  ___/ \___ \ \___ \ 
\____|__  /__|   |__|  (____  / ____\____|__  /\___  >___  >___  >____  >____  >
        \/                  \/\/            \/     \/    \/    \/     \/     \/ 
    */

    /**
     * Checks if the specified offset exists in the collection.
     *
     * @param mixed $offset The offset to check for existence.
     * @return bool Returns true if the offset exists, false otherwise.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Retrieves the item at the specified offset.
     *
     * @param mixed $offset The offset to retrieve.
     * @return Datamodel|null Returns an object of the model class inheriting from Datamodel if found, or null if the offset does not exist.
     */
    public function offsetGet($offset) : ?Datamodel
    {
        // Restriction 1. Ensure that the offset being accessed is logical (non-negative integer)
        if (!is_int($offset) || $offset < 0)
        {
            Composer::Throw("Offset must be a non-negative integer.");
        }

        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // Restriction 1. Ensure that the value being set is of the correct class type
        if (get_class($value) !== $this->datamodelClass)
        {
            Composer::Throw("Value must be an instance of Datamodel.");
        }

        // Restriction 2. Ensure that the offset being set is logical (non-negative integer)
        if (!is_int($offset) || $offset < 0)
        {
            Composer::Throw("Offset must be a non-negative integer.");
        }

        // If no offset is provided, append the value to the end of the collection
        if ($offset === null)
        {
            $this->items[] = $value;
            return;
        }

        $this->items[$offset] = $value;
    }

    /**
     * Unsets the item at the specified offset.
     *
     * Removes the element from the internal items array at the given offset.
     *
     * @param mixed $offset The offset of the item to unset.
     * 
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /*
___________                             __   
\_   _____/__  _________   ____________/  |_ 
 |    __)_\  \/  /\____ \ /  _ \_  __ \   __\
 |        \>    < |  |_> >  <_> )  | \/|  |  
/_______  /__/\_ \|   __/ \____/|__|   |__|  
        \/      \/|__|                       
    */

    /**
     * Renders a collection of items as an HTML table.
     *
     * Generates a formatted HTML table from the items stored in the collection.
     * If no items are available, displays a "No data available" message.
     *
     * The table includes:
     * - Dynamic column headers based on the properties of the first item
     * - Table rows for each item in the collection
     * - CSS styling for borders, text wrapping, and responsive layout
     * - HTML escaping for all column names and cell values to prevent XSS attacks
     *
     * @param bool $echo If true, the generated HTML will be echoed directly. If false, the HTML string will be also returned.
     * @return string The generated HTML table string. Returns an empty string if the collection is empty.
     *
     * @example
     * ```php
     * $collection = new DCollection();
     * $collection->add($item1);
     * $collection->add($item2);
     * $html = $collection->Render(); // Returns formatted HTML table
     *```
     * 
     */
    public function Render(bool $echo = true): string
    {
        if (empty($this->items))
        {
            if ($echo) {
                echo "<p>No data available.</p>";
            }
            return "";
        }

        $html = "<table style='
        width: 100%;
        max-width: 100%;
        border:1px solid black;
        table-layout: fixed;
        flex:1;
        '><thead><tr>";

        // Get column headers from the first item's properties
        $firstItem = $this->items[0];
        $properties = $firstItem->GetDataAsArray();

        foreach (array_keys($properties) as $column)
        {
            $html .= "<th style='
            border:1px solid black;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            '>" . htmlspecialchars($column) . "</th>";
        }
        $html .= "</tr></thead><tbody>";

        // Populate table rows
        foreach ($this->items as $item)
        {
            $html .= "<tr>";
            $properties = $item->GetDataAsArray();
            foreach ($properties as $value)
            {
                $html .= "<td style='
                border:1px solid black;
                word-wrap: break-word;
                overflow-wrap: break-word;
                white-space: normal;
                '>" . htmlspecialchars((string)$value) . "</td>";
            }
            $html .= "</tr>";
        }

        $html .= "</tbody></table>";
        if ($echo) {
            echo $html;
        }
        return $html;
    }

    /**
     * Converts the collection items to a CSV formatted string.
     *
     * @param string $delimiter The delimiter to use between values (default is comma).
     * @param string $breakLine The line break character(s) to use between rows (default is "\n").
     * @return string The CSV representation of the collection, or an empty string if there are no items.
     *
     * The method generates a CSV string with a header row containing property names,
     * followed by rows for each item in the collection. Values containing the delimiter,
     * double quotes, backslashes, or line breaks are properly escaped according to CSV rules.
     */
    public function ToCSV(string $delimiter = ",", string $breakLine = "\n") : string
    {
        if (empty($this->items))
        {
            return "";
        }

        $csv = "";
        $properties = $this->GetWorkingProperties();
        // Add CSV header
        $csv .= implode($delimiter, $properties) . $breakLine;
        
        // Add CSV rows
        foreach ($this->items as $item)
        {
            $row = [];
            foreach ($properties as $property)
            {
                $value = $item->get($property);
                $escapeIf = is_string($value) && (strpos($value, $delimiter) !== false || strpos($value, '"') !== false || strpos($value, "\\") !== false || strpos($value, $breakLine) !== false);
                
                if($escapeIf)
                {
                    $value = '"' . str_replace('"', '""', $value) . '"';
                }
                $row[] = $value;
            }
            $csv .= implode($delimiter, $row) . $breakLine;
        }

        return $csv;
    }

    /**
     * Converts the current object's properties to an associative array and encodes it as a JSON string.
     *
     * @return string The JSON-encoded representation of the object's properties.
     */
    public function ToJSON(): string
    {
        $arrayRepresentation = $this->toPropertiesArray();
        return json_encode($arrayRepresentation);
    }

    public function ToXML(bool $htmlEscape = false, $pretty = false): string
    {
        // 1. Create a new SimpleXMLElement with a root element
        $xml = new \SimpleXMLElement('<root/>');

        // 2. Iterate over each item in the collection and add it as a child element to the XML
        // (Ensure that using pretty printing for better readability, use Tab as the indent character and a newline for line breaks)
    
        foreach ($this->items as $item)
        {
            $itemElement = $xml->addChild('item');
            $properties = $item->GetDataAsArray();
            foreach ($properties as $key => $value)
            {
                // If the value is a string and HTML escaping is enabled, escape special characters
                if (is_string($value) && $htmlEscape)
                {
                    $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }

                $itemElement->addChild($key, (string)$value);
            }
        }

        // 3. If pretty printing is enabled, format the XML output with indentation and line breaks
        if ($pretty)
        {
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml->asXML());
            $result = $dom->saveXML();
        }
        else
        {
            $result = $xml->asXML();
        }

        // 4. Return the XML string, ensuring that it is properly escaped if HTML escaping is enabled
        return $htmlEscape ? htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $result;
    }

    public function ExportToExcelXLSX(string $fileName="export.xlsx")
    {
        if(empty($this->items))
        {
            Composer::Throw("Collection is empty.");
        }

        include_once "simplezip.php";

        $props = $this->GetWorkingProperties();

        $rows = "";

        // header
        $rows .= "<row>";
        foreach($props as $i=>$p)
        {
            $col = chr(65+$i);
            $rows .= "<c r='{$col}1' t='inlineStr'><is><t>".htmlspecialchars($p)."</t></is></c>";
        }
        $rows .= "</row>";

        $r = 2;

        foreach($this->items as $item)
        {
            $rows .= "<row r='$r'>";

            foreach($props as $i=>$p)
            {
                $col = chr(65+$i);
                $v = htmlspecialchars((string)$item->get($p));

                $rows .= "<c r='{$col}{$r}' t='inlineStr'><is><t>$v</t></is></c>";
            }

            $rows .= "</row>";
            $r++;
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8"?>
    <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>'.$rows.'</sheetData>
    </worksheet>';

        $workbook = '<?xml version="1.0" encoding="UTF-8"?>
    <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
    xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
    <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
    </sheets>
    </workbook>';

        $rels = '<?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
    Target="worksheets/sheet1.xml"/>
    </Relationships>';

        $rootrels = '<?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="xl/workbook.xml"/>
    </Relationships>';

        $types = '<?xml version="1.0" encoding="UTF-8"?>
    <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels"
    ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    </Types>';

        $zip = new \SimpleZip();

        $zip->addFile($types,"[Content_Types].xml");
        $zip->addFile($rootrels,"_rels/.rels");
        $zip->addFile($workbook,"xl/workbook.xml");
        $zip->addFile($rels,"xl/_rels/workbook.xml.rels");
        $zip->addFile($sheet,"xl/worksheets/sheet1.xml");

        $xlsx = $zip->output();

        // Create a temporary file to store the XLSX content and return the download link
        $tempFilePath = tempnam(sys_get_temp_dir(), 'export_') . '.xlsx';
        file_put_contents($tempFilePath, $xlsx);
        return $tempFilePath;
    }
}