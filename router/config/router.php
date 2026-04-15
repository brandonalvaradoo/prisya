<?php
namespace Router;
require_once "serverrequest.php";
require_once "uri.php";
require_once "routerambit.php";
require_once "selfrouter.php";

use Composer\Composer;
use Router\ServerRequest;
use Router\URI;
use Router\RouterAmbit;
use SelfRouter;

class Router
{
    /**
     * @var array $routerDictionary
     * Represents the router dictionary used for routing requests.
     * This property is used to define the mapping of URIs to views or controllers.
     * Is a result of the GetFullRouterDictionary() method.
     * Is used to store the routing configuration for the application,
     * preventing the need to load the router dictionary from a file every time.
     */
    private array $routerDictionary;

    /**
     * @var ServerRequest $serverRequest
     * Represents the current server request instance.
     * This property is used to handle and process incoming HTTP requests.
     */
    private ServerRequest $serverRequest;

    /**
     * @var URI $requestUriObject
     * Represents the URI object for the current request.
     * This property is used to handle and process the request URI.
     * Is a result of the ServerRequest::GetUriObject() method.
     */
    private URI $requestUriObject;

    /**
     * Instance of SelfRouter used to handle routing logic.
     * SelfRouter is responsible for managing the routing process on the self request
     * and determining which view to load based on the request URI.
     * @var SelfRouter
     */
    private SelfRouter $selfRouter;

    private static self $instance;

    public function __construct()
    {
        self::$instance = $this;
        $this->__init();
    }

    /**
     * Initializes the router configuration.
     *
     * This method performs the following tasks:
     * 1. Loads the server request and sets the request URI object.
     * 2. Loads the router dictionary and creates the self router instance.
     *
     * Steps:
     * - Calls `LoadServerRequest()` to initialize the server request.
     * - Calls `SetRequestUriObject()` to set the request URI object.
     * - Initializes the router dictionary using `GetFullRouterDictionary()` if not already set.
     * - Retrieves all router ambits using `GetRouterAmbitsFromArrayStructure()` if not already set.
     * - Creates a new instance of `SelfRouter` using the retrieved router ambits.
     *
     * @return void
     */
    public function __init() : void
    {
        // First, load the server request and set the request URI object.
        $this->LoadServerRequest();
        $this->SetRequestUriObject();

        // Then, load the router dictionary and create the self router.
        $this->routerDictionary = $this->routerDictionary ?? self::GetRouterDictionaryStructure();
        $allRouterAmbits = RouterAmbit::CreateArrangedFromKeyValueDictionaryArray($this->routerDictionary);

        // Set the SelfRouter object to handle routing logic.
        $this->selfRouter = new SelfRouter($allRouterAmbits, $this);
    }

    /**
     * 
__________        .__               __           .___        __                 _____                     
\______   \_______|__|__  _______ _/  |_  ____   |   | _____/  |_  ____________/ ____\____    ____  ____  
 |     ___/\_  __ \  \  \/ /\__  \\   __\/ __ \  |   |/    \   __\/ __ \_  __ \   __\\__  \ _/ ___\/ __ \ 
 |    |     |  | \/  |\   /  / __ \|  | \  ___/  |   |   |  \  | \  ___/|  | \/|  |   / __ \\  \__\  ___/ 
 |____|     |__|  |__| \_/  (____  /__|  \___  > |___|___|  /__|  \___  >__|   |__|  (____  /\___  >___  >
                                 \/          \/           \/          \/                  \/     \/    \/ 
     */

    /**
     * Initializes the server request by creating a new instance of the ServerRequest class.
     * This method is responsible for setting up the `$serverRequest` property
     * with the current server request data.
     *
     * @return void
     */
    private function LoadServerRequest()
    {
        $this->serverRequest = new ServerRequest();
    }

    /**
     * Sets the request URI object by retrieving it from the server request.
     * This method assigns the URI object obtained from the server request
     * to the `requestUriObject` property.
     *
     * @return void
     */
    private function SetRequestUriObject() : void
    {
        $this->requestUriObject = $this->serverRequest->GetUriObject();
    }


    /**
  _________ __          __  .__         .___        __                 _____                     
 /   _____//  |______ _/  |_|__| ____   |   | _____/  |_  ____________/ ____\____    ____  ____  
 \_____  \\   __\__  \\   __\  |/ ___\  |   |/    \   __\/ __ \_  __ \   __\\__  \ _/ ___\/ __ \ 
 /        \|  |  / __ \|  | |  \  \___  |   |   |  \  | \  ___/|  | \/|  |   / __ \\  \__\  ___/ 
/_______  /|__| (____  /__| |__|\___  > |___|___|  /__|  \___  >__|   |__|  (____  /\___  >___  >
        \/           \/             \/           \/          \/                  \/     \/    \/ 
    */

    /**
     * Returns the singleton instance of the class.
     *
     * This method ensures that only one instance of the class exists.
     * If the instance does not exist, it creates a new one.
     *
     * @return self The singleton instance of the class.
     */
    public static function Singleton() : self
    {
        return self::$instance ?? new self();
    }

    /**
     * Retrieves the full router dictionary from a JSON file.
     *
     * This method attempts to read the router dictionary file and decode its contents
     * as a JSON array. If the file cannot be read or the contents are not valid JSON,
     * an exception is thrown.
     *
     * @return array|null The decoded router dictionary as an associative array, or null if an error occurs.
     * @throws \Exception If the router dictionary file is not a valid JSON file.
     */
    private static function GetRouterDictionaryStructure() : array|null
    {
        $dictionaryPath = self::GetRouterDictionaryPath();

        try
        {
            return json_decode(file_get_contents($dictionaryPath), true);
        }
        catch(\Exception $e)
        {
            throw new \Exception("The router dictionary file is not a valid JSON file.");
        }

        return null;
    }

    /**
  _________       __    __                       
 /   _____/ _____/  |__/  |_  ___________  ______
 \_____  \_/ __ \   __\   __\/ __ \_  __ \/  ___/
 /        \  ___/|  |  |  | \  ___/|  | \/\___ \ 
/_______  /\___  >__|  |__|  \___  >__|  /____  >
        \/     \/                \/           \/ 
    */

    public function RetrievePublicItem(string $item) : bool
    {
        $itemPath = URI::TrimUri(Composer::CorrectPath($item));

        // if "public" folder in the path, get a new sub string from the first character after "public/".
        $findPublic = strpos($itemPath, $this->GetPublicFolder());
        $itemPath = $findPublic !== false ? substr($itemPath, $findPublic + strlen($this->GetPublicFolder())) : $itemPath;
        $itemPath = $this->GetPublicFolder() . $itemPath;

        if(file_exists($itemPath))
        {
            $mimeType = mime_content_type($itemPath);
            header("Content-Type: $mimeType");
            readfile($itemPath);

            return true;
        }

        return false;
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
     * Retrieves the current server request instance.
     *
     * @return ServerRequest The server request object.
     */
    public function GetServerRequest() : ServerRequest
    {
        return $this->serverRequest;
    }

    /**
     * Retrieves the URI object representing the current request.
     *
     * @return URI The URI object of the current request.
     */
    public function &GetRequestUriObject() : URI
    {
        return $this->requestUriObject;
    }

    /**
     * Retrieves the router dictionary.
     *
     * @return array The router dictionary containing routing configurations.
     */
    public function GetRouterDictionary() : array
    {
        return $this->routerDictionary;
    }

    /**
     * Retrieves the instance of the SelfRouter object.
     * 
     * This method provides access to the SelfRouter instance associated with this Router object.
     * It allows you to interact with the SelfRouter, which is responsible for handling routing logic
     * and managing the routing process within the application. It is useful for accessing routing methods,
     * such as routing to specific views or handling requests based on the current URI.
     * 
     * Also has information about the routing file name, the routing ambit and the destination URI.
     *
     * @return SelfRouter The SelfRouter instance associated with this object.
     */
    public function GetSelfRouterObject() : SelfRouter
    {
        return $this->selfRouter;
    }

    /**
     * Retrieves all router ambits.
     *
     * This method fetches an array of router ambits by delegating the call
     * to the `GetAllRouterAmbits` method of the `selfRouter` instance.
     *
     * @return array An array containing all router ambits.
     */
    public function GetRouterAmbits() : array
    {
        return $this->selfRouter->GetAllRouterAmbits();
    }

    /**
     * Retrieves the value of a specific parameter from the request URI.
     *
     * @param string $paramName The name of the parameter to retrieve.
     * @return string The value of the specified parameter.
     */
    public function GetParam(string $paramName) : string
    {
        return $this->GetRequestUriObject()->GetParam($paramName);
    }

    /**
     * Retrieves the head particle of the URI from the request object.
     *
     * This method utilizes the `GetRequestUriObject()` to obtain the URI object
     * and then calls `GetHeadParticle()` on it to fetch the head particle.
     *
     * @return string The head particle of the URI.
     */
    public function GetHeadUriParticle() : string
    {
        return $this->GetRequestUriObject()->GetHeadParticle();
    }

    /**
     * Retrieves the determinant URI particle from the request URI object.
     *
     * This method calls the `GetDeterminantParticle` method on the request URI object
     * to obtain the specific particle that determines the URI's behavior or routing.
     *
     * @return string The determinant URI particle.
     */
    public function GetDeterminantUriParticle() : string
    {
        return $this->GetRequestUriObject()->GetDeterminantParticle();
    }

    /**
     * Retrieves the real URI of the current petition/request.
     *
     * This method utilizes the server request object to obtain the actual
     * URI being requested by the client.
     *
     * @return string The real URI of the current request.
     */
    public function GetRealUriPetition() : string
    {
        return $this->GetServerRequest()->GetRealUri();
    }

    /**
     * Retrieves the name of the current view file being routed.
     *
     * @return string The name of the view file.
     * @throws \Exception If no view has been loaded to determine the view name.
     */
    public function GetThisViewName() : string
    {
        return $this->selfRouter->GetRoutingFileName() ?? throw new \Exception("You're trying to retrieve the view name, but no one view was loaded to determine the thisViewName.");
    }

    /**
     * Determines if the current request is targeting the index page.
     *
     * This method checks the request URI object to see if it corresponds
     * to the index page of the application.
     *
     * @return bool Returns true if the request is for the index page, false otherwise.
     */
    public function IsRequestingIndex() : bool
    {
        return $this->GetRequestUriObject()->IsIndex();
    }

    /**
     * Determines if the current request is targeting a public item.
     *
     * This method checks if the head URI particle matches the trimmed URI
     * of the public folder. It returns true if the request is for a public item,
     * otherwise false.
     *
     * @return bool True if the request is for a public item, false otherwise.
     */
    public function IsRquestingPublicItem() : bool
    {
        return $this->GetHeadUriParticle() === URI::TrimUri($this->GetPublicFolder());
    }

    /**
     * Determines if the current request is targeting the API.
     *
     * This method checks if the determinant URI particle matches the string "api".
     *
     * @return bool Returns true if the request is targeting the API, otherwise false.
     */
    public function IsRequestingApi() : bool
    {
        return $this->GetDeterminantUriParticle() === "api";
    }

    /**
     * 
__________         __  .__            
\______   \_____ _/  |_|  |__   ______
 |     ___/\__  \\   __\  |  \ /  ___/
 |    |     / __ \|  | |   Y  \\___ \ 
 |____|    (____  /__| |___|  /____  >
                \/          \/     \/ 
     */

    /**
     * Retrieves the absolute path to the router dictionary file (router.json).
     *
     * @return string The absolute path to the router dictionary file.
     * @throws \Exception If the router dictionary file does not exist.
     */
    public static function GetRouterDictionaryPath() : string
    {
        $path = realpath(__DIR__ . "/../router.json");

        return empty($path)
         ? throw new \Exception("The router dictionary file does not exist. The file is required to enrutate the requests.")
         : $path;
    }

    /**
     * Retrieves the relative path to the views folder.
     *
     * @return string The relative path to the views folder.
     */
    public static function GetViewsFolder() : string
    {
        return "views/";
    }

    /**
     * Retrieves the relative path to the public folder.
     *
     * @return string The relative path to the public folder, typically "public/".
     */
    public static function GetPublicFolder() : string
    {
        return "public/";
    }

    /**
     * Retrieves the relative path to the CSS folder.
     *
     * @return string The relative path to the CSS folder, typically "public/css/".
     */
    public static function GetCSSFolder() : string
    {
        return "public/css/";
    }

    /**
.____                 .__        
|    |    ____   ____ |__| ____  
|    |   /  _ \ / ___\|  |/ ___\ 
|    |__(  <_> ) /_/  >  \  \___ 
|_______ \____/\___  /|__|\___  >
        \/    /_____/         \/ 
    */

    /**
     * Executes the routing logic for the current request.
     *
     * This method determines how to handle the incoming request based on its URI and various conditions.
     * It performs the following steps:
     *
     * 1. Checks if the request is for the index page and routes it accordingly.
     * 2. Attempts to retrieve a public item based on the URI. If found, no further routing is performed.
     * 3. Checks if the request is for an API and rejects the loading of views if true.
     * 4. Determines the expected router ambit from the URI and checks if it exists in the router dictionary:
     *    - If the router ambit exists, it attempts to match the URI with a parametrized URI in the router ambit.
     *    - If a match is found, it routes the request within the router ambit.
     * 5. If no router ambit is included in the URI, it attempts to route the request in "Lax Mode" by including a file.
     * 6. If no router ambit or lax route file is found, it triggers a "Not Found" response.
     *
     * @return bool Returns true if the request is successfully routed or handled, false if the request is for an API.
     */
    public function Execute() : bool
    {
        // URI like it is requested like 'user/id/1'
        $uriObject = $this->GetRequestUriObject();
        $uriText = $this->GetRealUriPetition();

        if($this->IsRequestingIndex())
        {
            $this->selfRouter->RouteIndex();
            return true;
        }

        if($this->RetrievePublicItem($uriText))
        {
            // If the public item is found, it is returned and no further routing is needed.
            return true;
        }

        // Is it is requesting an API, reject the loading of views and return false.                                                                                                                    
        if($this->IsRequestingApi())
        {
            return false;
        }

        // When the ambit is defined in the URI, then, it's not LAX
        $expectedRouterAmbit = $uriObject->GetHeadParticle();

        // 1. NO LAX: Check if the expected router ambit exists in the router dictionary.
        if(RouterAmbit::RouterAmbitExists($expectedRouterAmbit))
        {
            $routerAmbit = RouterAmbit::TryGetRouterAmbit($expectedRouterAmbit);
            $matchUri = $routerAmbit->MatchParametrizedUri($this->requestUriObject);

            if($matchUri)
            {
                // If the URI matches a parametrized URI in the router ambit, route it.
                $this->selfRouter->RouteInAmbit($routerAmbit, $uriText);
                return true;
            }
        }

        // 2. LAX: If the URI does not include a router ambit, go Lax Mode.
        $routeLax = $this->selfRouter->RouteLax($uriText);

        if($routeLax)
        {
            // If the lax routing was successful, it means a file was included.
            return true;
        }


        // 3. If no router ambit is found, try to route the URI in a lax manner.
        $this->selfRouter->TriggerNotFound();
        return true;
    }
}