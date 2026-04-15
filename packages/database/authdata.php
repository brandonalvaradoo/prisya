<?php
namespace Database;

use Database\Database;
require_once "database.php";

class Authdata extends Database
{
    private bool $serverAuth;
    private string $hostlocation;
    private string $username;
    private string $password;
    private string $databaseAccesingName;
    private bool $faildie;

    private static array $INSTANCES = [];

    public function __construct(string $hostlocation, string $username, string $password, string $databaseAccesingName, bool $serverAuth = false)
    {
        $this->__init($hostlocation, $username, $password, $databaseAccesingName, $serverAuth);
        self::$INSTANCES[$databaseAccesingName] = $this;
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
     * Initializes the database authentication data.
     *
     * @param string $hostlocation The location of the database host.
     * @param string $username The username for database authentication.
     * @param string $password The password for database authentication.
     * @param string $databaseAccesingName The name of the database to access.
     * @param bool $serverAuth Optional. Indicates whether server authentication is enabled. Default is false.
     *
     * @return void
     */
    protected function __init(string $hostlocation, string $username, string $password, string $databaseAccesingName, bool $faildie = true, bool $serverAuth = false)
    {
        $this->hostlocation = $hostlocation;
        $this->username = $username;
        $this->password = $password;
        $this->databaseAccesingName = $databaseAccesingName;
        $this->faildie = $faildie;
        $this->serverAuth = $serverAuth;
    }

    /**
__________     ___.   .__  .__         .___        __                 _____                     
\______   \__ _\_ |__ |  | |__| ____   |   | _____/  |_  ____________/ ____\____    ____  ____  
 |     ___/  |  \ __ \|  | |  |/ ___\  |   |/    \   __\/ __ \_  __ \   __\\__  \ _/ ___\/ __ \ 
 |    |   |  |  / \_\ \  |_|  \  \___  |   |   |  \  | \  ___/|  | \/|  |   / __ \\  \__\  ___/ 
 |____|   |____/|___  /____/__|\___  > |___|___|  /__|  \___  >__|   |__|  (____  /\___  >___  >
                    \/             \/           \/          \/                  \/     \/    \/ 
     */

    /**
     * Creates an instance of Authdata from a JSON file.
     *
     * This method reads a JSON file containing database authentication details
     * and initializes an Authdata object based on the environment (server or local).
     *
     * WARNING: If other Authdata instances with the same name have already been created, it will return the existing instance by default.
     * 
     * @param string $authFileName The name of the JSON file (without extension) containing the authentication data.
     * 
     * @return Authdata|null Returns an Authdata instance if the file is valid and properly structured, otherwise null.
     * 
     * @throws \Exception If the JSON file is not found or has an invalid structure.
     */
    public static function FromDatabasesJSON(string $authFileName) : Authdata|null
    {
        if(array_key_exists($authFileName, self::$INSTANCES))
        {
            return self::$INSTANCES[$authFileName]; // Return existing instance if already created.
        }

        $expectedFile = Database::GetDatabasesFolderPath() . $authFileName . ".json";

        if (!file_exists($expectedFile))
        {
            throw new \Exception("JSON file not found for Authdata at " . $expectedFile);
            return null;
        }

        $runningOn = Database::IsRunningOnServer() ? "server" : "local";

        try
        {
            $content = file_get_contents($expectedFile);
            $data = json_decode($content, true)[$runningOn];
        }
        catch (\Exception $e)
        {
            throw new \Exception("Invalid JSON file structure for Authdata at " . $expectedFile);
            return null;
        }

        return new self(
            $data['host'],
            $data['username'],
            $data['password'],
            $data['database'],
            $data['faildie'] ?? true,
            Database::IsRunningOnServer()
        );
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

    protected static function RetrieveAllAuthdatas() : array
    {
        $databasesFolder = self::GetDatabasesFolderPath();
        $files = scandir($databasesFolder);

        foreach ($files as $file)
        {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json')
            {
                $authName = pathinfo($file, PATHINFO_FILENAME);
                $authdataInstance = self::FromDatabasesJSON($authName);

                if (!array_key_exists($authName, self::$INSTANCES) && $authdataInstance !== null)
                {
                    self::$INSTANCES[$authName] = $authdataInstance;
                }
            }
        }

        return array_values(self::$INSTANCES);
    }

    protected static function CountAuthdatas() : int
    {
        return count(self::RetrieveAllAuthdatas());
    }

    /**
     * Checks if the server authentication is enabled.
     *
     * @return bool Returns true if server authentication is enabled, false otherwise.
     */
    protected function IsServerAuth() : bool
    {
        return $this->serverAuth;
    }

    /**
     * Retrieves the host location.
     *
     * @return string The host location.
     */
    protected function GetHostLocation() : string
    {
        return $this->hostlocation;
    }

    /**
     * Retrieves the username.
     *
     * @return string The username associated with the current instance.
     */
    protected function GetUsername() : string
    {
        return $this->username;
    }

    /**
     * Retrieves the password associated with the current instance.
     *
     * @return string The password as a string.
     */
    protected function GetPassword() : string
    {
        return $this->password;
    }

    /**
     * Retrieves the name used for accessing the database.
     *
     * @return string The database accessing name.
     */
    protected function GetDatabaseAccesingName() : string
    {
        return $this->databaseAccesingName;
    }

    /**
     * Indicates whether the faildie option is enabled.
     *
     * @return bool Returns true if faildie is enabled, false otherwise.
     */
    protected function GetFailDie() : bool
    {
        return $this->faildie;
    }
}