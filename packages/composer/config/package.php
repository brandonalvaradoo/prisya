<?php
namespace Composer;
require_once "composer.php";

final class Package
{
    private string $packagePath;
    private array $packageJSONData;
    private bool $wasRequired = false;

    public function __construct(string $packagePath)
    {
        $this->packagePath = $packagePath;

        // Try to get expected package JSON file data
        $expectedJSON = Composer::CorrectPath($packagePath . DIRECTORY_SEPARATOR . Composer::PackageFileName());

        if(!file_exists($expectedJSON))
        {
            Composer::Throw("Cannot load package from path '$packagePath'. Expected package JSON file not found at '$expectedJSON'. Check the path in the dictionary.json file.", false);
        }

        $this->packageJSONData = json_decode(file_get_contents($expectedJSON), true);
        self::ValidatePackageJSONData($this->packageJSONData);
    }



    public static function ValidatePackageJSONData(array $data) : void
    {
        $requiredFields = ["name", "description", "version", "type", "autoload", "require"];

        foreach($requiredFields as $field)
        {
            if(!array_key_exists($field, $data))
            {
                Composer::Throw("Invalid package JSON data. Missing required field '$field'. Each package JSON file must contain the following fields: " . implode(", ", $requiredFields) . ".");
            }

            if($field === "autoload" || $field === "require")
            {
                if(!is_array($data[$field]))
                {
                    Composer::Throw("Invalid package JSON data. Field '$field' must be an array.");
                }
            }

            if($field === "version")
            {
                if(!Composer::ValidateVersionString($data[$field]))
                {
                    Composer::Throw("Invalid package JSON data. Field '$field' must be a valid version string (e.g., 1.0.0, 2.1.3).");
                }
            }
        }
    }

    public function Require() : bool
    {
        if($this->WasRequired())
        {
            return false;
        }

        // Require dependencies first
        foreach($this->GetRequirements() as $dependency => $expectedVersion)
        {
            if($dependency === 'php' && !Composer::CompareVersions($expectedVersion, PHP_VERSION))
            {
                Composer::Throw("PHP version mismatch for package '" . $this->GetName() . "'. Expected PHP version '$expectedVersion', but found PHP version '" . PHP_VERSION . "'. Please update your PHP version to meet the package requirements.", false);
                return false;
            }

            Composer::Require($dependency, $expectedVersion);
        }

        // Then require autoload files
        foreach($this->GetAutoloadFiles() as $autoloadFile)
        {
            $autoloadFilePath = Composer::CorrectPath($this->packagePath . DIRECTORY_SEPARATOR . $autoloadFile);

            if(!file_exists($autoloadFilePath))
            {
                Composer::Throw("Cannot autoload file '$autoloadFile' for package '" . $this->GetName() . "'. Expected autoload file not found at '$autoloadFilePath'. Check the path in the package JSON file.");
            }

            require_once $autoloadFilePath;
        }

        $this->wasRequired = true;
        return true;
    }

    /**
  ________        __    __                       
 /  _____/  _____/  |__/  |_  ___________  ______
/   \  ____/ __ \   __\   __\/ __ \_  __ \/  ___/
\    \_\  \  ___/|  |  |  | \  ___/|  | \/\___ \ 
 \______  /\___  >__|  |__|  \___  >__|  /____  >
        \/     \/                \/           \/ 

     */

    public function GetName()
    {
        return strtolower($this->packageJSONData['name']);
    }

    public function GetDescription()
    {
        return $this->packageJSONData['description'];
    }

    public function GetVersion()
    {
        return $this->packageJSONData['version'];
    }

    public function GetType()
    {
        return $this->packageJSONData['type'];
    }

    public function GetAutoloadFiles() : array
    {
        return $this->packageJSONData['autoload'];
    }

    public function GetRequirements() : array
    {
        return $this->packageJSONData['require'];
    }

    public function WasRequired()
    {
        return $this->wasRequired;
    }
}