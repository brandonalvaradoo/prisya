<?php
namespace Composer;
use Exception;

include_once "package.php";

/**
 * Class Composer
 *
 * This class is responsible for managing package loading and resolving dependencies
 * for the project. It uses a dictionary and autoresolve mechanism to handle package
 * paths and ensure that required packages are loaded correctly.
 *
 * @package Composer
 */
class Composer
{
    private static array $loaded;
    private static array $autoresolve;
    private static array $processedPackages;

    private static self $instance;
    private static string $absoluteProjectPath;

    public function __construct()
    {
        $this->Load();
        $this->LoadAutoresolve();
        
        self::$instance = $this;
    }

    /**
     * Returns the singleton instance of the class.
     *
     * This method ensures that only one instance of the class is created and returned.
     * If the instance does not exist, it creates a new one.
     *
     * @return self The singleton instance of the class.
     */
    public static function Singleton() : self
    {
        return self::$instance ?? new self();
    }

    public static function Require(string $packageName, string|null $version = null, bool $throwable = false) : bool
    {
        $packageName = strtolower($packageName);
        $package = self::Singleton()->GetPackage($packageName);

        if($package)
        {
            if($version !== null)
            {
                if(!self::CompareVersions($version, $package->GetVersion()))
                {
                    self::Throw("Version mismatch for package '$packageName'. Expected version '$version', but found version '" . $package->GetVersion() . "'.", false);
                }
            }

            if($package->WasRequired())
            {
                return true;
            }

            $package->Require();
            return true;
        }

        if($throwable)
        {
            self::Throw("Package '$packageName' not found. Make sure the package is defined in the dictionary.json file and that the path to the package is correct.");
        }
        
        return false;
    }


    private function Load(): void
    {
        $loadedJSONPath = realpath(__DIR__ . "/../" . self::LoadedFileName());

        if(!file_exists($loadedJSONPath))
        {
            self::Throw("The loaded.json file is missing. This file is required to keep track of loaded packages. Please create an empty loaded.json file in the root directory of the project.", false);
        }

        try
        {
            self::$loaded = json_decode(file_get_contents($loadedJSONPath), true);
        }
        catch(\Exception $e)
        {
            self::Throw("The loaded.json file is not a valid JSON file. Please check the file and try again.", false);
        }

        foreach(self::$loaded as $packageName => $packagePath)
        {
            self::$processedPackages[strtolower($packageName)] = new Package($packagePath);
        }
    }

    /**
     * Loads the autoresolve configuration from a JSON file.
     * 
     * AUTO RESOLVE: If the class is not found in the dictionary, it will try to resolve the class by looking at the paths in the autoresolve.json file.
     *
     * This method attempts to load and decode the `autoresolve.json` file located
     * in the parent directory of the current directory. If the file does not exist,
     * an empty array is assigned to the `self::$autoresolve` property. If the file
     * exists but is not a valid JSON file, an exception is thrown.
     *
     * @throws \Exception If the `autoresolve.json` file is not a valid JSON file.
     */
    private function LoadAutoresolve(): void
    {
        $autoresolvePath = realpath(__DIR__ . "/../autoresolve.json");

        if(empty($autoresolvePath))
        {
            self::$autoresolve = array();
            return;
        }

        try
        {
            self::$autoresolve = json_decode(file_get_contents($autoresolvePath), false);
            self::$autoresolve  = array_map(function($path) {
                return self::CorrectPath($path);
            }, self::$autoresolve);
        }
        catch(\Exception $e)
        {
            self::Throw("The autoresolve.json file is not a valid JSON file. Please check the file and try again.", false);
        }
    }

    public function Resolve(string $className) : bool
    {
        $require = $this->Require($className);

        if($require)
        {
            return true;
        }
        else foreach(self::$autoresolve as $path)
        {
            $expected = realpath($path . DIRECTORY_SEPARATOR . strtolower($className) . ".php");

            if(!empty($expected))
            {
                require_once($expected);
                return true;
            }
        }

        self::Throw("Composer is executing by default. It encountered an error while trying to resolve the class '$className'. Try to define the package or php file path that contains the class in the dictionary.json file or include them directly.", true);

        return false;
    }

/**
 _   _       _ _     _       _                 
| | | |     | (_)   | |     | |                
| | | | __ _| |_  __| | __ _| |_ ___  _ __ ___ 
| | | |/ _` | | |/ _` |/ _` | __/ _ \| '__/ __|
\ \_/ / (_| | | | (_| | (_| | || (_) | |  \__ \
 \___/ \__,_|_|_|\__,_|\__,_|\__\___/|_|  |___/
 */
    
    /**
     * Corrects and normalizes a file path by removing leading/trailing slashes and whitespace,
     * and converting mixed slash types to the system's directory separator.
     *
     * This method handles malformed paths with multiple consecutive slashes, backslashes,
     * and mixed separators, converting them to a consistent format using the appropriate
     * directory separator for the current operating system.
     *
     * @param string $path The file path to correct. Can contain forward slashes, backslashes,
     *                      leading/trailing whitespace, or mixed separators.
     * @param bool $includeRoot Whether to prepend the absolute project root to the path
     *                           if the original path started with a slash. Default is true.
     *
     * @return string The corrected path with normalized separators and whitespace removed.
     *                If $includeRoot is true and the path started with a slash,
     *                the path will be prefixed with the absolute project root.
     *
     * @example
     * ```php
     * // Example 1: Normalize mixed separators
     * $corrected = self::CorrectPath("//path\/to\/\file/ ");
     * // Returns: "/absolute/project/root/path/to/file" (or with backslashes on Windows)
     * ```
     * 
     * @example
     * ```php
     * // Example 2: Handle leading slashes with root inclusion
     * $corrected = self::CorrectPath("/path/to/file", true);
     * // Returns: "/absolute/project/root/path/to/file"
     * ```
     *
     * @example
     * ```php
     * // Example 3: Without root inclusion
     * $corrected = self::CorrectPath("/path/to/file", false);
     * // Returns: "path/to/file"
     * ```
     *
     * @example
     * ```php
     * // Example 4: When path is a directory, this method ensure it ends with a directory separator
     * $corrected = self::CorrectPath("/path/to/directory");
     * // Returns: "/absolute/project/root/path/to/directory/"
     * ```
     */
    public static function CorrectPath(string $path, bool $rootCorrection = true, string $separator = DIRECTORY_SEPARATOR) : string
    {
        // Must correct given paths like
        // "//path\/to\/\file/ "
        // to
        // "root/path/to/file"

        $firstCharIsSlash = str_starts_with($path, "/") || str_starts_with($path, "\\");
        $trimmed = trim($path, " \t\n\r\0\x0B/\\");

        if(empty($trimmed))
        {
            return $rootCorrection ? self::GetAbsoluteProjectRoot() : $separator;
        }

        // Manage cases when includes multiple slashes at the middle of the path like "path//to///file"
        $trimmed = preg_replace('/[\/\\\\]+/', $separator, $trimmed);

        if($firstCharIsSlash && $rootCorrection)
        {
            $trimmed = self::GetAbsoluteProjectRoot() . $trimmed;
        }
        else if($firstCharIsSlash)
        {
            $trimmed = $separator . $trimmed;
        }

        // Check if the path drives to a folder to add last slash
        if(is_dir($trimmed))
        {
            $trimmed .= $separator;
        }

        return $trimmed;
    }

    public static function ValidateVersionString(string $version) : bool
    {
        // Simple regex to validate version format (e.g., 1.0.0, 2.1.3, etc.)
        return preg_match('/^\d+\.\d+\.\d+$/', $version) === 1;
    }

    public static function CompareVersions(string $expectedVersion, string $actualVersion) : bool
    {
        // Operator	Example	Meaning
        // ^	^1.2.3	Compatible with version 1.2.3 (i.e., >=1.2.3 <2.0.0)
        // ~	~1.2.3	Just patches (i.e., >=1.2.3 <1.3.0)
        // *	1.2.*	Any version in the 1.2 series (i.e., >=1.2.0 <1.3.0)
        // exacto	1.2.3	Exactly version 1.2.3
        // rango	>=1.2 <2.0	Manual

        $expectedVersion = trim($expectedVersion);
        $actualVersion = trim($actualVersion);

        $firstUntilNumber = strcspn($expectedVersion, "0123456789");
        $operator = substr($expectedVersion, 0, $firstUntilNumber);


        // Case 1. Exact version match
        if(empty($operator))
        {
            return $expectedVersion === $actualVersion;
        }

        // Case 2. Range operator
        if(in_array($operator, [">=", "<", ">", "<="]))
        {
            // Can has two comparison operators like ">=1.2 <2.0"
            $secondUntilNumber = strcspn($expectedVersion, "0123456789", $firstUntilNumber);
            $secondOperator = substr($expectedVersion, $firstUntilNumber, $secondUntilNumber);
            if(!empty($secondOperator))
            {
                $version1 = substr($expectedVersion, $firstUntilNumber);
                $version2 = substr($expectedVersion, $firstUntilNumber + $secondUntilNumber);

                return version_compare($actualVersion, $version1, $operator) && version_compare($actualVersion, $version2, $secondOperator);
            }
            else
            {
                $version = substr($expectedVersion, $firstUntilNumber);
                return version_compare($actualVersion, $version, $operator);
            }
        }

        // Case 3. Wildcard operator
        if(str_contains($expectedVersion, "*"))
        {
            $baseVersion = str_replace("*", "0", $expectedVersion);
            $upperBound = preg_replace('/\d+$/', '9999', $baseVersion);
            return version_compare($actualVersion, $baseVersion, ">=") && version_compare($actualVersion, $upperBound, "<");
        }

        // Case 4. Compatible operator
        if($operator === "^")
        {
            $baseVersion = substr($expectedVersion, 1);
            $upperBound = preg_replace('/\d+$/', '9999', $baseVersion);
            return version_compare($actualVersion, $baseVersion, ">=") && version_compare($actualVersion, $upperBound, "<");
        }

        // Case 5. Patch operator
        if($operator === "~")
        {
            $baseVersion = substr($expectedVersion, 1);
            $upperBound = preg_replace('/\.\d+$/', '.0', $baseVersion) . '.9999';
            return version_compare($actualVersion, $baseVersion, ">=") && version_compare($actualVersion, $upperBound, "<");
        }

        return false;
    }

    private static function GenericThrow(string $message) : void
    {
        set_exception_handler(function (\Throwable $e)
        {
            echo "<b>Error:</b> ". trim($e->getMessage(), " \t\n\r\0\x0B.") .". (No file and line information available)";
        });

        throw new \Exception($message);
    }

    public static function Throw(string $message, bool $showThrownDetails = true) : void
    {
        restore_exception_handler();
        // Callable from packages to hide detailed error messages from the user and throw a generic message instead.
    
        // Intercept the file location of the INVOCATOR to hide internal stack frames from the working package when the throw file is located in the same package.
        // Filter the stack trace to hide package/* calls chain.

        $InvocatorFile = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 1)[0]['file'] ?? null;

        if(!$InvocatorFile)
        {
            self::GenericThrow($message);
            return;
        }

        try
        {
            $loadedJSONPath = realpath(__DIR__ . "/../" . self::LoadedFileName());
            $packagesPaths = array_values(json_decode(file_get_contents($loadedJSONPath), true));
            $matchPath = array_filter($packagesPaths, function (string $packagePath) use ($InvocatorFile) {
                return str_starts_with(Composer::CorrectPath($InvocatorFile), Composer::CorrectPath($packagePath));
            });
            $matchPath = array_values($matchPath)[0] ?? null;
        }
        catch(\Exception $e)
        {
            self::GenericThrow($message);
            return;
        }
        
        if(!$matchPath)
        {
            self::GenericThrow($message);
            return;
        }


        set_exception_handler(function (\Throwable $e) use ($matchPath, $showThrownDetails)
        {
            $trace = $e->getTrace();

            // Filter to hide internal frames from the working package when the throw file is located in the same package.
            $filtered = array_filter($trace, fn($item) => isset($item['file']) && strpos(Composer::CorrectPath($item['file']), Composer::CorrectPath($matchPath)) === false);
            $filtered = array_values($filtered);

            $upperClass = $trace[1]['class'] ?? 'UnknownClass'; // Get error class from the second frame of the stack trace, which is the caller of the throw method. If not available, use 'UnknownClass'.

            http_response_code(500);
            if(isset($filtered[0]['file']) && isset($filtered[0]['line']) && $showThrownDetails)
            {
                echo "<b>$upperClass error:</b> ". trim($e->getMessage(), " \t\n\r\0\x0B.") .". Captured in <b>" . $filtered[0]['file'] . " on line " . $filtered[0]['line'] . ".</b>";
                return;
            }

            echo "<b>$upperClass error:</b> ". trim($e->getMessage(), " \t\n\r\0\x0B.") .".";   
        });

        // Throw the original error to be captured by the custom exception handler.
        // Hide the stack trace of the throw method by throwing the error from a different file (the invocator file) to make it look like the error is thrown from the invocator file instead of the throw method.

        throw new \Exception($message);
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
     * Attempts to retrieve a package by its name.
     *
     * @param string $packageName The name of the package to retrieve.
     * @return Package|null The package if found, or null if not found.
     */
    public function GetPackage(string $packageName) : Package|null
    {
        return self::$processedPackages[$packageName] ?? null;
    }

    public function GetLoadedArray() : array
    {
        return $this->loaded;
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
    public static function GetAbsoluteProjectRoot()
    {
        return self::$absoluteProjectPath ?? self::$absoluteProjectPath = realpath("") . DIRECTORY_SEPARATOR;
    }

    /**
     * Get the server root directory.
     *
     * This method returns the absolute path to the server's document root directory.
     * Note that this is not necessarily the same as the project root, which may be
     * located in a subfolder within the server root.
     *
     * @return string|false The absolute path to the server's document root, or false on failure.
     */
    public static function GetServerRoot()
    {
        return realpath($_SERVER["DOCUMENT_ROOT"]);
    }

    /**
     * Retrieves the project's base URL by converting backslashes to forward slashes
     * in the path returned from GetInscribedFoldersFromServerRoot().
     *
     * @return string The normalized project base URL.
     */
    public static function GetProjectBaseUrl()
    {
        return str_replace("\\", "/", Composer::GetInscribedFoldersFromServerRoot());
    }

    /**
     * AbsoluteProjectRoot minus SERVER_ROOT.
     * 
     * Get the relative path of the project root from the server root.
     *
     * This method calculates the relative path from the server's document root
     * to the project's absolute root directory. It subtracts the server's document
     * root path from the project's absolute root path and returns the result.
     *
     * @return string The relative path from the server's document root to the project's root directory, ending with a directory separator.
     */
    public static function GetInscribedFoldersFromServerRoot()
    {
        return str_replace(
            self::GetServerRoot(),
            "",
            self::GetAbsoluteProjectRoot()
        );
    }

    public static function LoadedFileName() : string
    {
        return "loaded.json";
    }

    public static function PackageFileName() : string
    {
        return ".package.json";
    }
}