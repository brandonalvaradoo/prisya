<?php
//This code Author: Brandon Alvarado
//Last review on: 16/03/2025
//Version: 2.3.1

namespace Database;


include_once 'authdata.php';
require_once 'config/datamodel/datamodel.php';


class Database
{
    protected Authdata $authdata;

    private static array $INSTANCES = array();
    private static bool $SUCCESSFUL_CONNECTION = false;
    private static string|null $CONNECT_ERROR_MESSAGE = null;

    /**
     * Constructor method for the database class.
     *
     * @param Authdata $authdata An instance of the Authdata class used for authentication purposes.
     *
     * This constructor initializes the database class by assigning the provided
     * Authdata instance to the $authdata property and adds the current instance
     * to the static $INSTANCES array for tracking purposes.
     */
    public function __construct(Authdata $authdata)
    {
        $this->authdata = $authdata;
        self::$INSTANCES[$this->GetDatabaseName()] = $this;
    }

    /**
     * Creates a new instance of the class using authentication data from a specified file.
     *
     * @param string $filename The file name to the authentication file without its extension.
     * @return self|null Returns a new instance of the class.
     */
    public static function FromAuthFile(string $filename): self|null
    {
        $authdata = Authdata::FromDatabasesJSON($filename);
        return $authdata ? new self($authdata) : null;
    }

    /**
     * Retrieves a database instance by its name.
     *
     * This method searches for a database instance in the existing instances.
     * If no instance is found, it attempts to retrieve the database from an
     * authentication file. If the database is still not found, an exception
     * is thrown.
     *
     * @param string $databaseName The name of the database to retrieve.
     * 
     * @return self|null Returns the database instance if found, or null if not.
     * 
     * @throws \Exception If no database is found with the specified name.
     */
    public static function RetrieveDatabase(string $databaseName) : self|null
    {
        // Case 1: The database instance is already in the static array.
        $expectedDatabaseWhereName = self::$INSTANCES[$databaseName] ?? null;

        if($expectedDatabaseWhereName != null)
        {
            return ($expectedDatabaseWhereName);
        }

        // Case 2: The database instance is not in the static array.
        $expected = self::FromAuthFile($databaseName);
        
        if($expected)
        {
            return $expected;
        }

        // Case 3: The database instance is not in the static array and not found in the authentication file.
        throw new \Exception("No database found with name '$databaseName'.");
        return null;
    }

    /**
     * Loads all database instances based on authentication data.
     *
     * Retrieves all available authentication configurations and initializes
     * database connections for each one. Only creates new instances for
     * databases that are not already loaded in the instances registry.
     *
     * @return void
     */
    public static function LoadAllDatabasesInstances() : void
    {
        $auths = Authdata::RetrieveAllAuthdatas();
        foreach($auths as $auth)
        {
            $databaseName = $auth->GetDatabaseAccesingName();

            if (!array_key_exists($databaseName, self::$INSTANCES))
            {
                $db = new self($auth);
                $db->connect();
            }
        }
    }

    /**
     * 
________          __                            .___     .__           .___        __                 _____                     
\______ \ _____ _/  |______    _____   ____   __| _/____ |  |   ______ |   | _____/  |_  ____________/ ____\____    ____  ____  
 |    |  \\__  \\   __\__  \  /     \ /  _ \ / __ |/ __ \|  |  /  ___/ |   |/    \   __\/ __ \_  __ \   __\\__  \ _/ ___\/ __ \ 
 |    `   \/ __ \|  |  / __ \|  Y Y  (  <_> ) /_/ \  ___/|  |__\___ \  |   |   |  \  | \  ___/|  | \/|  |   / __ \\  \__\  ___/ 
/_______  (____  /__| (____  /__|_|  /\____/\____ |\___  >____/____  > |___|___|  /__|  \___  >__|   |__|  (____  /\___  >___  >
        \/     \/          \/      \/            \/    \/          \/           \/          \/                  \/     \/    \/ 
     */

    /**
     * Resolves the path to a datamodel file and includes it if found.
     *
     * This method attempts to locate a datamodel file by its name within the
     * directory structure of the current database. It first checks for the
     * datamodel file in the expected directory. If not found, it searches
     * recursively within the directory.
     *
     * @param string $datamodelName The name of the datamodel file to resolve.
     * @return string|null The path to the datamodel file if found, or null if not found.
     */
    public final function ResolveDatamodel(string $datamodelName) : string|null
    {
        $thisDatabaseDatamodelsFolder = Database::GetDatamodelsFolderPath() . $this->GetDatabaseName() . "/";
        $expectedPath =  $thisDatabaseDatamodelsFolder . $datamodelName . ".php";

        if(file_exists($expectedPath))
        {
            include_once $expectedPath;
            return $expectedPath;
        }


        $expectedPath = self::SearchRecursively($thisDatabaseDatamodelsFolder, $datamodelName . ".php");

        if($expectedPath)
        {
            include_once $expectedPath;
            return $expectedPath;
        }

        return null;
    }


    /**
     * Resolves the path to a datamodel file from the root directory.
     *
     * This function searches recursively for a PHP file with the specified datamodel name
     * within the "datamodels" directory. If the file is found, it includes the file and
     * returns the path to the file. If the file is not found, it returns null.
     *
     * @param string $datamodelName The name of the datamodel to search for.
     * @return string|null The path to the datamodel file if found, otherwise null.
     */
    public static final function ResolveDatamodelFromRoot(string $datamodelName) : string|null
    {
        $expected_path = self::SearchRecursively(Database::GetDatamodelsFolderPath(), $datamodelName . ".php");

        if($expected_path)
        {
            include_once $expected_path;
            return $expected_path;
        }

        return null;
    }

    /**
     * Searches for a file recursively within a given directory.
     *
     * This method traverses the directory and its subdirectories to find a file
     * that matches the specified file name, ignoring case sensitivity.
     *
     * @param string $directory The directory to start the search from.
     * @param string $fileName The name of the file to search for.
     * @return string|null The full path of the file if found, or null if not found.
     */
    private static function SearchRecursively(string $directory, string $fileName) : string|null
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file)
        {
            if ($file->isFile() && strtolower($file->getFilename()) === strtolower($fileName))
            {
                return $file->getPathname();
            }
        }

        return null; 
    }

    /**
     * 
_________                                     __  .__                .___        __                 _____                     
\_   ___ \  ____   ____   ____   ____   _____/  |_|__| ____   ____   |   | _____/  |_  ____________/ ____\____    ____  ____  
/    \  \/ /  _ \ /    \ /    \_/ __ \_/ ___\   __\  |/  _ \ /    \  |   |/    \   __\/ __ \_  __ \   __\\__  \ _/ ___\/ __ \ 
\     \___(  <_> )   |  \   |  \  ___/\  \___|  | |  (  <_> )   |  \ |   |   |  \  | \  ___/|  | \/|  |   / __ \\  \__\  ___/ 
 \______  /\____/|___|  /___|  /\___  >\___  >__| |__|\____/|___|  / |___|___|  /__|  \___  >__|   |__|  (____  /\___  >___  >
        \/            \/     \/     \/     \/                    \/           \/          \/                  \/     \/    \/ 
     */

    /**
     * connect
     * 
     * This method establishes a connection to the database.
     * 
     * @param bool $logs Optional. If true, logs the connection process. Default is false.
     * @return PDO The PDO instance representing the database connection.
     * @throws PDOException If the connection to the database fails.
     */
    public final function connect(int $fetch_mode = \PDO::FETCH_ASSOC) : \PDO|null
    {
        $connection = "mysql:host=" . $this->authdata->GetHostLocation() . ";dbname=" . $this->authdata->GetDatabaseAccesingName() . ";charset=utf8mb4";
        $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false, \PDO::ATTR_DEFAULT_FETCH_MODE => $fetch_mode];

        try
        {
            $pdo = new \PDO($connection, $this->authdata->GetUsername(), $this->authdata->GetPassword(), $options);
            Database::$SUCCESSFUL_CONNECTION = true;
            Database::$CONNECT_ERROR_MESSAGE = "Successful connection.";
            return $pdo;

        }
        catch (\PDOException $pe)
        {
            if ($this->authdata->GetFailDie())
            {
                $this->FailApplication();
            }

            Database::$SUCCESSFUL_CONNECTION = false;
            Database::$CONNECT_ERROR_MESSAGE = $pe->getMessage();
        }

        return null;
    }

    /**
     * Pings the database to check if the connection is successful.
     *
     * This method attempts to connect to the database and prints a message indicating
     * whether the connection was successful or not. If the connection is successful, it
     * returns true; otherwise, it returns false.
     *
     * @return bool
     */
    public function Ping()
    {
        $ping = $this->connect() !== null;
        echo $ping ? "Connected to the database " . $this->GetDatabaseName() : "Could not connect to the database " . $this->GetDatabaseName();

        return $ping;
    }


    /**
     * Terminates the application execution due to a critical database connection failure.
     *
     * This method outputs an HTML message indicating that the application has stopped
     * because of a database connection issue, and advises the user to contact the system administrator.
     *
     * Warning: This method will terminate the script execution. Can be faked for testing purposes.
     * It doesn't check any condition before terminating; it is assumed that the caller has already determined the failure.
     * 
     * @return void
     */
    public function FailApplication()
    {
        Database::$SUCCESSFUL_CONNECTION = false;

        die("
        <h1>Critical Database Connection Failure</h1>
        <p>
        The application has been stopped due to a critical database connection failure.
        Please contact the system administrator to resolve this issue.
        </p>
        ");
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
     * Retrieves the absolute path to the "databases" folder within the current directory.
     *
     * @return string|null The absolute path to the "databases" folder with a trailing slash,
     *                     or throws an exception if the directory is not found.
     * 
     * @throws \Exception If the "databases" directory does not exist or cannot be resolved.
     */
    public static function GetDatabasesFolderPath() : string|null
    {
        $result = realpath(__DIR__ . "/databases");
        return empty($result) ?  throw new \Exception("Databases directory not found at $result.") : $result . '/';
    }

    /**
     * Retrieves the absolute path to the "datamodels" folder within the current directory.
     *
     * @return string|null The absolute path to the "datamodels" folder with a trailing slash,
     *                     or throws an exception if the folder is not found.
     * 
     * @throws \Exception If the "datamodels" directory does not exist or cannot be resolved.
     */
    public static function GetDatamodelsFolderPath() : string|null
    {
        $result = realpath(__DIR__ . "/datamodels");
        return empty($result) ?  throw new \Exception("Datamodels directory not found at $result.") : $result . '/';
    }

    /**
     * Retrieves the file path of the datamodel key dictionary file.
     *
     * This method constructs the path to the "dictionary.json" file located
     * within the datamodels folder. It verifies the existence of the file
     * and returns its absolute path if found. If the file does not exist,
     * an exception is thrown.
     *
     * @return string|null The absolute file path of the datamodel key dictionary file,
     *                     or null if the file is not found.
     *
     * @throws \Exception If the datamodel key dictionary file is not found.
     */
    public static function GetDatamodelKeyDictionaryFilePath() : string|null
    {
        $path = realpath(self::GetDatamodelsFolderPath() . "dictionary.json");
        return empty($path) ? throw new \Exception("Datamodels key dictionary file not found at $path.") : $path;
    }

    /**
     * Checks if the application is running on a server.
     *
     * This method determines if the application is running on a server by checking
     * the server address and server name. It returns true if the application is 
     * running on a server, and false if it is running locally (e.g., on localhost).
     *
     * @return bool True if running on a server, false if running locally.
     */
    public static function IsRunningOnServer() : bool
    {
        $IS_RUNNING_ON_SERVER =
            !(
                $_SERVER['SERVER_ADDR'] == '127.0.0.1'
                || strpos($_SERVER['SERVER_NAME'], 'localhost') !== false
            );

        return $IS_RUNNING_ON_SERVER;
    }

    /**
     * Returns the name of the database being accessed.
     *
     * @return string The name of the database.
     */
    public function GetDatabaseName(): string
    {
        return $this->authdata->GetDatabaseAccesingName();
    }

    /**
     * Retrieves all database instances.
     *
     * @return array An array containing all database instances.
     */
    public function GetAllDatabasesInstances() : array
    {
        return array_values(self::$INSTANCES);
    }

    /**
     * Checks if the database connection was successful.
     *
     * @return bool True if the connection was successful, false otherwise.
     */
    public static function SuccessfulConnection() : bool
    {
        return Database::$SUCCESSFUL_CONNECTION;
    }

    /**
     * Retrieves the error message from the last connection attempt, if any.
     *
     * @return string|null The connection error message, or null if there was no error.
     */
    public static function GetConnectionErrorMessage() : string|null
    {
        return Database::$CONNECT_ERROR_MESSAGE;
    }
}

//MUST BE LOADED FIRST OF ALL, OR NEXT TO COMPOSER, IF USING IT WITH COMPOSER.
Database::LoadAllDatabasesInstances();