<?php
use Composer\Composer;
require_once "composer.php";

define("BASE_URL", Composer::GetProjectBaseUrl());

/**
 * Returns the singleton instance of the Composer class.
 *
 * @return Composer The singleton instance of the Composer class.
 */
function composer() : Composer
{
    return Composer::Singleton();
}

spl_autoload_register(function($retrieved) {
    $namespaceParts = explode("\\", trim($retrieved, " \n\r\t\\/"));
    $packageName = $namespaceParts[0] ?? null;
    $className = array_pop($namespaceParts);

    if($packageName)
    {
        $requirement = composer()->Require($packageName);
        $classLoaded = class_exists($className, false);

        if($requirement && $classLoaded)
        {
            return;
        }
    }

    composer()->Resolve($className); // Resolve instead if package name is not found or does not resolve to a package.
});

