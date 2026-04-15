<?php

use Router\Router;
use Router\RouterAmbit;
use Router\URI;
use Composer\Composer;

class SelfRouter
{
    private array $allRouterAmbits;
    private string $routingUriDestination;
    private string $routingAmbitName;
    private string $routingFileName;
    protected Router $router;


    public function __construct(array $allRouterAmbits, Router &$router)
    {
        $this->allRouterAmbits = $allRouterAmbits;
        $this->router = $router;
    }


    /**
     * 
__________               __                    _____          __  .__               .___      
\______   \ ____  __ ___/  |_  ___________    /     \   _____/  |_|  |__   ____   __| _/______
 |       _//  _ \|  |  \   __\/ __ \_  __ \  /  \ /  \_/ __ \   __\  |  \ /  _ \ / __ |/  ___/
 |    |   (  <_> )  |  /|  | \  ___/|  | \/ /    Y    \  ___/|  | |   Y  (  <_> ) /_/ |\___ \ 
 |____|_  /\____/|____/ |__|  \___  >__|    \____|__  /\___  >__| |___|  /\____/\____ /____  >
        \/                        \/                \/     \/          \/            \/    \/ 
     */

    /**
     * Routes to a specified URI within a given ambit name.
     * 
     * Delegates the routing process to the `Route` method,
     *
     * This method trims the provided ambit name and destination URI, validates them,
     * and attempts to locate and require the corresponding PHP file for routing.
     * If the file exists, it sets the routing properties and includes the file.
     * Otherwise, it throws an exception indicating the file does not exist.
     *
     * @param string|null $ambitName The name of the ambit (namespace or folder) to route to.
     * @param string|null $destinationUri The URI of the destination view or resource.
     * 
     * @throws \Exception If either $ambitName or $destinationUri is null.
     * @throws \Exception If the specified routing file does not exist.
     * 
     * @return void
     */
    public function Route(string|null $ambitName, string|null $destinationUri) : void
    {
        $ambitName = URI::TrimUri($ambitName);
        $destinationUri = URI::TrimUri($destinationUri);

        if($ambitName == null || $destinationUri == null)
        {
            throw new \Exception("Ambit name or URI cannot be null.");
            return;
        }
        
        $this->routingFileName = Composer::GetAbsoluteProjectRoot() . $ambitName . '/' . $destinationUri . '.php';

        if(file_exists($this->routingFileName))
        {
            $this->routingUriDestination = $destinationUri;
            $this->routingAmbitName = $ambitName;

            // Require the file to route
            require_once $this->routingFileName;
            return;
        }

        throw new \Exception("The view $this->routingFileName does not exist.");
    }

    /**
     * Routes the given URI in a lax manner by attempting to find and require the corresponding route file.
     *
     * @param string $uri The URI to be routed. It is expected to be a string representing the path.
     * @return bool Returns true if a route file was found and required, false otherwise.
     *
     * The method utilizes `RouterAmbit::TryGetLaxRouteFile` to determine the file associated with the URI
     * after trimming it using `URI::TrimUri`. Once the file is identified, it is passed to the `Route` method
     * for further processing.
     */
    public function RouteLax(string $uri) : bool
    {
        $laxPairs = RouterAmbit::TryGetLaxInPairs($uri);


        if(is_null($laxPairs))
        {
            return false; // No matching route found
        }

        $this->Route($laxPairs['ambitName'], $laxPairs['destinationUri']);

        return true; // Route found and processed
    }

    /**
     * Routes a formatted URI within a specific router ambit.
     *
     * This method determines the destination URI and ambit name based on the 
     * provided formatted URI and routes the request accordingly.
     *
     * @param RouterAmbit $routerAmbit The router ambit instance to use for matching the URI.
     * @param string $formmatedUri The formatted URI to be routed.
     *
     * @return void
     */
    public function RouteInAmbit(RouterAmbit $routerAmbit, string $formmatedUri) : void
    {
        $pairs = $routerAmbit->GetDestinationUriWhereFormattedURIMatches(new URI($formmatedUri));
        $this->Route($pairs['ambitName'], $pairs['destinationUri']);
    }

    /**
     * 
________          _____             .__   __    __________               __  .__                      
\______ \   _____/ ____\____   __ __|  |_/  |_  \______   \ ____  __ ___/  |_|__| ____    ____  ______
 |    |  \_/ __ \   __\\__  \ |  |  \  |\   __\  |       _//  _ \|  |  \   __\  |/    \  / ___\/  ___/
 |    `   \  ___/|  |   / __ \|  |  /  |_|  |    |    |   (  <_> )  |  /|  | |  |   |  \/ /_/  >___ \ 
/_______  /\___  >__|  (____  /____/|____/__|    |____|_  /\____/|____/ |__| |__|___|  /\___  /____  >
        \/     \/           \/                          \/                           \//_____/     \/ 
     */

    /**
     * Routes the application to the "index" route.
     *
     * This method sets up routing to the "index" route using the RouteLax method.
     * It does not return any value.
     *
     * @return void
     */
    public function RouteIndex() : void
    {
        $this->RouteInAmbit(
            RouterAmbit::TryGetRouterAmbit("views"),
            "/"
        );
    }

    /**
     * Routes the application to the "notfound" page when a requested route is not found.
     *
     * This method is used to handle cases where the requested route does not exist
     * by redirecting the user to a predefined "notfound" page.
     *
     * @return void
     */
    public function TriggerNotFound() : void
    {
        $this->RouteLax("notfound");
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
     * Retrieves the routing URI destination.
     *
     * @return string The destination URI for routing.
     */
    public function GetRoutingUriDestination() : string
    {
        return $this->routingUriDestination;
    }

    /**
     * Retrieves the name of the current routing ambit.
     *
     * @return string The name of the routing ambit.
     */
    public function GetRoutingAmbitName() : string
    {
        return $this->routingAmbitName;
    }

    /**
     * Retrieves the name of the routing file.
     *
     * @return string The name of the routing file.
     */
    public function GetRoutingFileName() : string
    {
        return $this->routingFileName;
    }

    /**
     * Retrieves all router ambits.
     *
     * @return array An array containing all router ambits.
     */
    public function GetAllRouterAmbits() : array
    {
        return $this->allRouterAmbits;
    }

    /**
     * Retrieves the router instance.
     *
     * @return Router The router instance associated with this self-router.
     */
    public function &GetRouter() : Router
    {
        return $this->router;
    }
}