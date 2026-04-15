<?php
namespace Autoload;

/**
 * Class Autoload
 * 
 * This class is responsible for loading and managing autoload configurations.
 * It reads dictionaries from JSON files, processes them into absolute paths,
 * and includes the specified files.
 * 
 * @property array $dictionary The main dictionary loaded from autoload.json.
 * @property array $privateDictionary The private dictionary loaded from mandatorypackages.json.
 * @property array $absolutes The absolute paths derived from the dictionaries.
 * 
 * @method void __construct() Initializes the class by loading dictionaries and processing absolute paths.
 * @method void LoadDictionary() Loads the main dictionary from autoload.json.
 * @method void LoadPrivateDictionary() Loads the private dictionary from mandatorypackages.json.
 * @method array GetDictionary() Merges and returns the main and private dictionaries.
 * @method array GetAsAbsolutes() Processes and returns the absolute paths from the dictionaries.
 * @method void Execute() Includes the files specified in the absolute paths.
 * @method array GetPublicDictionary() Returns the main dictionary.
 * @method array GetPrivateDictionary() Returns the private dictionary.
 * @method array GetAbsolutes() Returns the absolute paths.
 * @method string GetAbsoluteProjectRoot() Returns the absolute project root path.
 * 
 * @throws \Exception If a specified file for autoload does not exist.
 */
class Autoload
{
    private array $dictionary;
    private array $privateDictionary;
    private array $absolutes;

    /**
     * Constructor method.
     * 
     * This method initializes the class by performing the following actions:
     * 1. Loads the dictionary by calling the LoadDictionary() method.
     * 2. Loads the private dictionary by calling the LoadPrivateDictionary() method.
     * 3. Sets the absolutes property by calling the GetAsAbsolutes() method.
     */
    public function __construct()
    {
        $this->LoadDictionary();
        $this->LoadPrivateDictionary();
        $this->absolutes = $this->GetAsAbsolutes();
    }

    /**
     * Loads the dictionary from a JSON file.
     *
     * This method reads the contents of the autoload.json file located in the
     * parent directory of the current directory, decodes the JSON data, and
     * assigns it to the $dictionary property.
     *
     * @return void
     */
    public function LoadDictionary() : void
    {
        $this->dictionary = json_decode(file_get_contents(realpath(__DIR__ . "/../autoload.json")), true);
    }

    /**
     * Loads the private dictionary from a JSON file.
     *
     * This method reads the contents of the "mandatorypackages.json" file,
     * decodes the JSON data, and assigns it to the privateDictionary property.
     *
     * @return void
     */
    public function LoadPrivateDictionary() : void
    {
        $this->privateDictionary = json_decode(file_get_contents(realpath(__DIR__ . "/mandatorypackages.json")), true);
        $this->privateDictionary = array_shift($this->privateDictionary);
    }

    /**
     * Merges and returns the public and private dictionaries.
     *
     * @return array The merged array of public and private dictionaries.
     */
    public function GetDictionary() : array
    {
        return array_merge($this->dictionary, $this->privateDictionary);
    }

    /**
     * GetAsAbsolutes method
     *
     * This method retrieves a dictionary of paths and processes them to ensure they are absolute paths.
     * It trims each path and checks if it starts with a "/" or "\\". If so, it replaces the first character
     * with the absolute project root path.
     *
     * @return array An array of absolute paths.
     */
    public function GetAsAbsolutes() : array
    {
        $dictionary = array_merge($this->dictionary, $this->privateDictionary);
        $absolutes = array();

        foreach($dictionary as $key => $value)
        {
            $absolutes[$key] = trim($value);

            if($absolutes[$key][0] == "/" || $absolutes[$key][0] == "\\")
            {
                //Replace first character with GetAbsoluteProjectRoot()
                $absolutes[$key] = $this->GetAbsoluteProjectRoot() . substr($absolutes[$key], 1);
            }
        }

        return $absolutes;
    }

    /**
     * Executes the autoload process by including files specified in the autoload configuration.
     *
     * This method retrieves an array of absolute file paths using the GetAsAbsolutes() method.
     * It then iterates over each file path and includes the file if it exists.
     * If a file does not exist, an exception is thrown with a message indicating the missing file.
     *
     * @throws \Exception If a specified file for autoload does not exist.
     */
    public function Execute() : void
    {
        foreach($this->absolutes as $value)
        {
            if(file_exists($value))
            {
                include_once $value;
            }

            else throw new \Exception("The specified file '$value' for autoload does not exist. Try to update its path in the autoload.json file.");
        }
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
     * Retrieves the public dictionary.
     *
     * @return array The dictionary array.
     */
    public function GetPublicDictionary() : array
    {
        return $this->dictionary;
    }

    /**
     * Retrieves the private dictionary.
     *
     * @return array The private dictionary.
     */
    public function GetPrivateDictionary() : array
    {
        return $this->privateDictionary;
    }

    /**
     * Get the absolute paths.
     *
     * This method returns the absolute paths stored in the $absolutes property.
     *
     * @return array The array of absolute paths.
     */
    public function GetAbsolutes() : array
    {
        return $this->absolutes;
    }

    /**
     * Get the absolute path to the project root directory.
     * 
     * Absolute path is the full path to the root directory of the project. Relative to the index file and the project definition.
     *
     * This method returns the absolute path to the root directory of the project.
     * It uses the `realpath` function to resolve the absolute path and appends a
     * trailing slash to ensure the path ends with a directory separator.
     *
     * @return string The absolute path to the project root directory.
     */
    public function GetAbsoluteProjectRoot() : string
    {
        $projectRoot = realpath("") . "/";
        return $projectRoot;
    }
}