<?php
namespace Router;

use Composer\Composer;
use Router\URI;

require_once "uri.php";


class RouterAmbit
{
    /**
     * @var string $routingAmbit
     * Represents the routing ambit configuration for the application.
     * This property is used to define the scope or context of routing.
     * Is defined as a string that typically represents a URL path or a specific routing context.
     * For example: "views/*" or "private/".
     */
    private string $routingAmbit;
    /**
     * @var string $trimmedAmbit
     * A private property that stores the trimmed ambit value.
     * This property is derived from the routing ambit and is used to remove unnecessary characters or whitespace.
     */
    private string $trimmedAmbit;

    /**
     * @var array $routeringDictionary
     * 
     * This private property holds the routing dictionary used for mapping routes 
     * within the application. It is an associative array where keys represent route 
     * identifiers and values contain corresponding route configurations or handlers.
     */
    private array $routeringDictionary;

    /**
     * @var URI[] $parametrizedUris
     * 
     * An array that stores URIs with parameters for routing purposes.
     * These URIs are used to match and extract dynamic segments from the request URL.
     */
    private array $parametrizedUris;

    /**
     * @var self[] $allRouterAmbitObjects
     * A static array that holds all router ambit objects.
     * This property is used to store and manage router ambit configurations.
     * It stores every instance of RouterAmbit created,
     * using the trimmed ambit as the key.
     */
    private static array $allRouterAmbitObjects;

    /**
     * @var self[] $laxRouterAmbitObjects
     * A private static array that stores router ambit objects.
     * This array contains all router ambit objects that are considered lax.
     * A lax router ambit is one that allows for flexible routing, typically indicated by an asterisk (*) at the end of the routing ambit string.
     */
    private static array $laxRouterAmbitObjects = [];

    /**
     * @var Router $router
     * 
     * A protected property that holds the router instance associated with this ambit.
     * This property is used to access the router functionality within the ambit.
     */
    protected Router $router;

    /**
     * Constructor for the RouterAmbit class.
     *
     * Initializes the routing ambit, routing dictionary, and creates parameterized URIs.
     * Also registers the router ambit object in the static array of all router ambit objects.
     *
     * @param string $routeringAmbit The routing ambit to be set.
     * @param array $routeringDictionary An associative array representing the routing dictionary.
     * @param Router &$router The router instance to be associated with this ambit.
     */
    public function __construct(string $routeringAmbit, array $routeringDictionary)
    {
        $this->SetRoutingAmbit($routeringAmbit);
        $this->SetRouteringDictionary($routeringDictionary);
        $this->CreateParametrizedUris();
        self::$allRouterAmbitObjects[$this->trimmedAmbit] = $this;
        // Store the router instance for later use.
        $this->router = Router::Singleton();

        if($this->IsLax())
        {
            // If the routing ambit is lax, store it in a separate array for lax router ambits.
            // This allows for flexible routing and handling of URIs that may not strictly match the defined routes.
            self::$laxRouterAmbitObjects[$this->trimmedAmbit] = $this;
        }
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
     * Sets the routing ambit for the router and trims the URI.
     *
     * @param string $routingAmbit The routing ambit to be set.
     * 
     * @return void
     */
    private function SetRoutingAmbit(string $routingAmbit) : void
    {
        $this->routingAmbit = $routingAmbit;
        $this->trimmedAmbit = URI::TrimUri($routingAmbit);
    }

    /**
     * Sets the routing dictionary for the router.
     *
     * @param array $routeringDictionary An associative array representing the routing dictionary.
     * @return void
     */
    private function SetRouteringDictionary(array $routeringDictionary) : void
    {
        $this->routeringDictionary = $routeringDictionary;
    }

    /**
     * Creates a list of parameterized URIs based on the routing dictionary.
     *
     * This method initializes the `parametrizedUris` property as an empty array
     * and iterates through the `routeringDictionary`. For each URI in the dictionary,
     * it creates a new `URI` object and adds it to the `parametrizedUris` array.
     *
     * @return void
     */
    public function CreateParametrizedUris() : void
    {
        $this->parametrizedUris = [];
        foreach($this->routeringDictionary as $searchKeyword => $destinationFile)
        {
            $paramUri = new URI($searchKeyword);
            $this->parametrizedUris[$searchKeyword] = $paramUri;
        }
    }


    /**
     * 
__________     ___.   .__  .__         .___        __                 _____                     
\______   \__ _\_ |__ |  | |__| ____   |   | _____/  |_  ____________/ ____\____    ____  ____  
 |     ___/  |  \ __ \|  | |  |/ ___\  |   |/    \   __\/ __ \_  __ \   __\\__  \ _/ ___\/ __ \ 
 |    |   |  |  / \_\ \  |_|  \  \___  |   |   |  \  | \  ___/|  | \/|  |   / __ \\  \__\  ___/ 
 |____|   |____/|___  /____/__|\___  > |___|___|  /__|  \___  >__|   |__|  (____  /\___  >___  >
                    \/             \/           \/          \/                  \/     \/    \/ 
     */

    /**
     * Creates an array of RouterAmbit objects arranged from a key-value dictionary array.
     *
     * This method takes an associative array where the keys represent routing ambits
     * and the values represent their corresponding routing dictionaries. It creates
     * RouterAmbit objects for each entry and organizes them into an array, using the
     * trimmed URI of the routing ambit as the key.
     *
     * @param array $routerDictionary An associative array where keys are routing ambits
     *                                and values are routing dictionaries.
     * @return self[] An array of RouterAmbit objects indexed by their trimmed URI.
     */
    public static function CreateArrangedFromKeyValueDictionaryArray(array $routerDictionary) : array
    {
        $routerAmbits = [];

        foreach($routerDictionary as $routeringAmbit => $routeringDictionary)
        {
            $routerAmbit = new self($routeringAmbit, $routeringDictionary);
            $routerAmbits[URI::TrimUri($routeringAmbit)] = $routerAmbit;
        }

        return $routerAmbits;
    }

    public function LoadViewFrom(string $viewName) : bool
    {
        $viewPath = $this->GetPHPFileFromThisAmbit($viewName);

        if (file_exists($viewPath))
        {
            require_once $viewPath;
            return true;
        }

        throw new \Exception("The view $viewName does not exist in the ambit $this->routingAmbit.");
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
     * Determines if the routing ambit is lax. An ambit is considered lax if it allows for flexible routing,
     *
     * A lax routing ambit is identified by an asterisk (*) at the end of the string.
     *
     * @return bool Returns true if the routing ambit ends with an asterisk (*), otherwise false.
     */
    public function IsLax() : bool
    {
        return substr($this->routingAmbit, -1) === '*';
    }

    /**
     * Retrieves the routing ambit.
     *
     * @return string The current routing ambit.
     */
    public function GetRoutingAmbit() : string
    {
        return $this->routingAmbit;
    }

    /**
     * Retrieves the trimmed version of the routing ambit.
     *
     * This method utilizes the URI::TrimUri function to remove unnecessary
     * characters or whitespace from the routing ambit property.
     *
     * @return string The trimmed routing ambit.
     */
    public function GetTrimmedRoutingAmbit() : string
    {
        return $this->trimmedAmbit;
    }

    /**
     * Retrieves the routing dictionary.
     *
     * @return array The routing dictionary containing route mappings.
     */
    public function GetRouteringDictionary() : array
    {
        return $this->routeringDictionary;
    }

    /**
     * Retrieves the trimmed ambit path with a trailing slash.
     *
     * @return string The trimmed ambit path followed by a '/' character.
     */
    public function GetAmbitPath() : string
    {
        return $this->trimmedAmbit . '/';
    }

    /**
     * Retrieves the absolute path of the current ambit by combining the project's root path
     * and the relative ambit path.
     *
     * @return string The absolute path of the current ambit.
     */
    public function GetAmbitAbsolutePath() : string
    {
        return Composer::GetAbsoluteProjectRoot() . $this->GetAmbitPath();
    }

    /**
     * Retrieves the absolute path to a PHP file based on the provided view name.
     *
     * This method constructs the full file path by combining the absolute path
     * of the current ambit with the trimmed URI of the view name, appending
     * the '.php' extension.
     *
     * @param string $viewName The name of the view for which the PHP file path is required.
     * @return string The absolute path to the corresponding PHP file.
     */
    public function GetPHPFileFromThisAmbit(string $viewName) : string
    {
        return $this->GetAmbitAbsolutePath() . URI::TrimUri($viewName) . '.php';
    }

    /**
     * Retrieves an array of parameterized URIs.
     *
     * @return URI[] An array of URI objects containing the parameterized URIs.
     */
    public function GetParametrizedUris() : array
    {
        return $this->parametrizedUris;
    }

    /**
     * Retrieves the destination URI for a given parameterized URI.
     *
     * A destination URI is the final URI that a parameterized URI maps to.
     * 
     * This method looks up the provided parameterized URI in the routing dictionary
     * and returns the corresponding destination URI if it exists. If the URI is not
     * found in the dictionary, it returns null.
     *
     * @param string $parametrizedUri The parameterized URI to look up.
     * @return string|null The destination URI if found, or null if not found.
     */
    public function GetDestinationUriFor(string $parametrizedUri) : ?string
    {
        $expextedUri = $this->routeringDictionary[$parametrizedUri] ?? null;
        return $expextedUri;
    }

    /**
     * Retrieves the current Router instance.
     *
     * @return Router Reference to the Router object managed by this class.
     */
    public function &GetRouter() : Router
    {
        return $this->router;
    }

    /**
     * Checks if the given URI head matches the head particle of any parametrized URI.
     *
     * This method iterates through the list of parametrized URIs and compares their
     * head particle with the provided URI head. If a match is found, it returns true.
     * Otherwise, it returns false.
     *
     * @param string $uriHead The URI head to check against the head particles of parametrized URIs.
     * @return bool Returns true if a matching head particle is found, otherwise false.
     */
    public function HasSomeUriHead(string $uriHead) : bool
    {
        foreach ($this->parametrizedUris as $paramUri)
        {
            if ($paramUri->GetHeadParticle() === $uriHead)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Matches a formatted URI against a list of parameterized URIs to find a match.
     * 
     * Useful for determining if a given formatted URI corresponds to any of the
     * parameterized URIs defined in the router ambit.
     * 
     * A parameterized URI is a URI that contains dynamic segments or parameters,
     * which can be matched against a formatted URI to extract specific values.
     *
     * This method iterates through all parameterized URIs and checks if any of them
     * match the given formatted URI. If a match is found, the matching parameterized
     * URI is returned; otherwise, null is returned.
     *
     * @param URI $formattedUri The formatted URI to match against parameterized URIs.
     * @return URI|null Returns the matching parameterized URI if found, or null if no match is found.
     */
    public function MatchParametrizedUri(URI &$formattedUri) : URI|null
    {
        // Iterate through all parametrized URIs to find a match with the formatted URI.
        foreach($this->GetParametrizedUris() as $paramUri)
        {
            $match = $formattedUri->MatchesUriParticleFormmat($paramUri);

            if($match)
            {
                // If a match is found, return the corresponding destination URI.
                return $paramUri;
            }
        }

        return null;
    }


    /**
     * Retrieves the destination URI and ambit path where the given formatted URI matches
     * a parameterized URI within the current ambit.
     *
     * This method attempts to match the provided formatted URI with a parameterized URI
     * defined in the ambit. If a match is found, it returns an array containing the ambit
     * path and the destination URI associated with the matched URI. If no match is found,
     * it returns null.
     *
     * @param URI $formattedUri The formatted URI to match against parameterized URIs.
     * 
     * @return array|null Returns an associative array with the following keys:
     *                    - 'ambitName': The path of the current ambit.
     *                    - 'destinationUri': The destination URI associated with the matched URI.
     *                    - 'matchedParameterizedUri': The matched parameterized URI.
     * 
     *                    Returns null if no match is found.
     */
    public function GetDestinationUriWhereFormattedURIMatches(URI $formattedUri) : array|null
    {
        // Attempt to match the formatted URI with a parameterized URI in this ambit.
        // Result is an URI object like 'user/id/:id'.
        $matchedUri = $this->MatchParametrizedUri($formattedUri);

        if($matchedUri !== null)
        {
            // If a match is found, return the PHP file path associated with the matched URI.
            $destinationUri = $this->GetDestinationUriFor($matchedUri->GetUri());
            $this->router->GetRequestUriObject()->SetParametrizedUriRefered($matchedUri->GetUri());

            return [
                'ambitName' => $this->GetAmbitPath(), // Includes slash '/'
                'destinationUri' => $destinationUri,
                'matchedParameterizedUri' => $matchedUri->GetUri()
            ];
        }

        return null;
    }

    /**
     * Checks if a specified file exists within the current ambit.
     *
     * @param string $fileName The name of the file to check for existence. (Do not include the .php extension)
     * @return bool Returns true if the file exists in the current ambit, otherwise false.
     */
    public function FileExistsInThisAmbit(string $fileName) : bool
    {
        // Check if the specified file exists in the current ambit.
        return file_exists($this->GetPHPFileFromThisAmbit($fileName));
    }

    /**
     * Retrieves all Router Ambit objects.
     *
     * This method returns an array containing all the Router Ambit objects
     * stored in the static property `self::$allRouterAmbitObjects`.
     *
     * @return RouterAmbit[] An array of all Router Ambit objects.
     */
    public static function GetAllRouterAmbitObjects() : array
    {
        return self::$allRouterAmbitObjects;
    }

    /**
     * Retrieves all lax router ambit objects.
     *
     * This method returns an array containing all the lax router ambit objects
     * stored in the static property `$laxRouterAmbitObjects`.
     *
     * @return RouterAmbit[] An array of lax router ambit objects.
     */
    public static function GetAllLaxRouterAmbitObjects() : array
    {
        return self::$laxRouterAmbitObjects;
    }
    
   
    
    public static function TryGetLaxInPairs(string $uri) : array|null
    {
        $uri = URI::TrimUri($uri); // Formmat the given requested URI
        $uriObject = new URI($uri); // Create the URI object

        // Iterate through all lax router ambit objects to find a matching URI head.
        foreach (self::$laxRouterAmbitObjects as $routerAmbitObject)
        {
            // Compare among all the searchKeywords parametrized URIs from the LAX and the given URI
            $expectedMatchedUri = $routerAmbitObject->MatchParametrizedUri($uriObject);

            // If a match is found when URI Format is deffined, return the PHP file path associated with the matched URI.
            if($expectedMatchedUri !== null)
            {
                // Set the requested URI as a parameterized URI refered in the request URI object.
                Router::Singleton()->GetRequestUriObject()->SetParametrizedUriRefered($expectedMatchedUri->GetUri());

                // Get the destination URI for the matched parameterized URI.
                $targetFile = $routerAmbitObject->GetDestinationUriFor($expectedMatchedUri->GetUri());

                return [
                    'ambitName' => $routerAmbitObject->GetAmbitPath(), // Includes slash '/'
                    'destinationUri' => $targetFile,
                    'matchedParameterizedUri' => $expectedMatchedUri->GetUri()
                ];
            }
            // When the URI Format is not explicit deffined, we check if the file exists in the lax router ambit.
            else if($routerAmbitObject->FileExistsInThisAmbit($uri))
            {
                // Set the requested URI as a parameterized URI refered in the request URI object.
                Router::Singleton()->GetRequestUriObject()->SetParametrizedUriRefered($uri);

                // If the file exists in the lax router ambit, return its file head.
                return [
                    'ambitName' => $routerAmbitObject->GetAmbitPath(), // Included slash '/'
                    'destinationUri' => $uri,
                    'matchedParameterizedUri' => $uri
                ];
            }

        }

        return null;
    }

    /**
     * Attempts to retrieve a RouterAmbit object based on the provided trimmed URI.
     *
     * This method checks if the given trimmed URI exists in the collection of all 
     * RouterAmbit objects. If it exists, the corresponding RouterAmbit object is returned.
     * Otherwise, null is returned.
     *
     * @param string $trimmedAmbitUri The URI string that has been trimmed and is used 
     *                                to identify the RouterAmbit object.
     * 
     * @return RouterAmbit|null Returns the RouterAmbit object if found, or null if 
     *                          no matching object exists.
     */
    public static function TryGetRouterAmbit(string $trimmedAmbitUri) : ?RouterAmbit
    {
        $trimmedAmbitUri = URI::TrimUri($trimmedAmbitUri);

        // Check if the trimmed ambit URI exists in the all router ambit objects.
        if (isset(self::$allRouterAmbitObjects[$trimmedAmbitUri]))
        {
            return self::$allRouterAmbitObjects[$trimmedAmbitUri];
        }

        return null;
    }

    /**
     * Checks if a router ambit exists for the given trimmed URI.
     *
     * This method verifies whether the specified trimmed URI corresponds
     * to an existing router ambit object in the collection.
     *
     * @param string $trimmedAmbitUri The trimmed URI to check.
     * @return bool Returns true if the router ambit exists, false otherwise.
     */
    public static function RouterAmbitExists(string $trimmedAmbitUri) : bool
    {
        $trimmedAmbitUri = URI::TrimUri($trimmedAmbitUri);
        return isset(self::$allRouterAmbitObjects[$trimmedAmbitUri]);
    }
}