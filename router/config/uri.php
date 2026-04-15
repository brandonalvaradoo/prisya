<?php
namespace Router;

require_once "main.php";

use Composer\Composer;

class URI
{
    private string $uri;
    private string $trimmedUri;
    private array $particles;
    private array $params;
    private string|false $querySentence;

    private self $parametrizedUriRefered;


    public function __construct(string $uri, bool $forceCanonical = false)
    {
        $this->__init($uri, $forceCanonical);
    }

    private function __init(string $uri, bool $forceCanonical) : void
    {
        $this->uri = $forceCanonical ? self::GetRealUri($uri) : $uri;
        $this->trimmedUri = self::TrimUri($uri);
        $this->SetParticles();
        $this->SetParams();
        $this->querySentence = self::GetUriQuerySentence($uri);
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
     * Checks if the given particle is a parameter.
     *
     * This function determines if the provided string starts with a colon (":"),
     * which is used to identify parameters in the URI.
     *
     * @param string $particle The particle to check.
     * @return bool Returns true if the particle starts with a colon, false otherwise.
     */
    public static function ParticleIsAParam(string $particle) : bool
    {
        return strpos($particle, ":") === 0;
    }

    /**
     * Checks if the given URI contains a query string.
     *
     * This method determines if the provided URI string includes a query 
     * component, which is indicated by the presence of a '?' character.
     *
     * @param string $uri The URI string to check.
     * @return bool Returns true if the URI contains a query string, false otherwise.
     */
    public static function UriHasQuery(string $uri) : bool
    {
        return strpos($uri, "?") !== false;
    }

    /**
     * Extracts the query string from a given URI.
     *
     * This method checks if the provided URI contains a query string. If it does,
     * it returns the query string starting from the '?' character. If the URI does
     * not contain a query string, it returns false.
     *
     * @param string $uri The URI from which to extract the query string.
     * @return string|bool The query string if it exists, or false if it does not.
     */
    public static function GetUriQuerySentence(string $uri) : string|bool
    {
        return self::UriHasQuery($uri) ? substr($uri, strpos($uri, "?")) : false;
    }

    /**
     * Trims the given URI by removing the query string, the ".php" extension, 
     * and any leading or trailing slashes, spaces, or other unwanted characters.
     *
     * Specifically, the function performs the following operations:
     * - Removes the query string (anything after a '?' character).
     * - Removes the ".php" extension if present.
     * - Trims leading and trailing slashes ('/').
     * - Trims leading and trailing spaces.
     * - Trims other unwanted characters such as backslashes ('\') and null bytes.
     *
     * @param string $uri The URI to be trimmed.
     * @return string The trimmed URI.
     */
    public static function TrimUri(string $uri) : string
    {
        //Remove query
        $uri = self::UriHasQuery($uri) ? substr($uri, 0, strpos($uri, "?")) : $uri;
        //Remove the .php extension and any leading or trailing slashes, spaces, or other unwanted characters.
        $uri = str_replace(".php", "", trim($uri, " \t\n\r\0\x0B\\/* "));
        //If the URI has multiple slashes, like /\//, replace them with a single slash.
        $uri = preg_replace('/\/+/', '/', $uri);

        return $uri;
    }

    /**
     * Processes the given URI string by trimming it and ensuring it is properly formatted.
     *
     * This method trims the input URI string and then formats it to ensure it starts and ends with a slash.
     * If the trimmed URI is an empty string, it returns a single slash ("/").
     *
     * @param string $uri The URI string to be processed.
     * @return string The formatted URI string.
     */
    public static function SandUri(string $uri) : string
    {
        $uri = self::TrimUri($uri);
        return $uri === '' ? '/' : '/' . $uri . '/';
    }

    /**
     * Concatenates two URI segments into a single URI.
     *
     * This method trims the leading and trailing slashes from both input URIs,
     * concatenates them with a single slash in between, and then sanitizes the
     * resulting URI.
     *
     * @param string $uri1 The first URI segment.
     * @param string $uri2 The second URI segment.
     * @return string The concatenated and sanitized URI.
     */
    public static function ConcatUri(string $uri1, string $uri2) : string
    {
        $result = self::TrimUri($uri1) . '/' . self::TrimUri($uri2);
        return self::SandUri($result);
    }

    /**
     * Checks if the given URI is an index.
     *
     * This method trims the given URI and checks if it matches any of the following:
     * - An empty string
     * - The string "index"
     * - The index view name from the router configuration, or "home" if not set
     *
     * @param string $uri The URI to check.
     * @return bool True if the URI is an index, false otherwise.
     */
    public static function IsUriAnIndex(string $uri) : bool
    {
        $uri = self::TrimUri($uri);
        return $uri === "" || $uri === "index" || $uri === "home";
    }


    /**
     * GetRealUri
     *
     * This method processes a given URI to return the real URI by removing the server root and any inscribed folders.
     *
     * @param string $uri The URI to be processed.
     * @return string The real URI after removing the server root and inscribed folders.
     */
    public static function GetRealUri(string $uri): string
    {
        $server = URI::TrimUri(Composer::GetServerRoot());
        $serverFolders = URI::TrimUri(Composer::GetInscribedFoldersFromServerRoot());
        $real = URI::TrimUri(str_replace($server, "", $uri));

        return URI::SandUri(str_replace($serverFolders, "", $real));
    }

    /**
     * GetRootedUri
     *
     * This method takes a URI string and returns a rooted URI by concatenating
     * the server root with the provided URI.
     *
     * @param string $uri The URI string to be rooted.
     * @return string The rooted URI.
     */
    public static function GetRootedUri(string $uri): string
    {
        return self::ConcatUri(Composer::GetServerRoot(), $uri);
    }

    /**
  _________       __    __                       
 /   _____/ _____/  |__/  |_  ___________  ______
 \_____  \_/ __ \   __\   __\/ __ \_  __ \/  ___/
 /        \  ___/|  |  |  | \  ___/|  | \/\___ \ 
/_______  /\___  >__|  |__|  \___  >__|  /____  >
        \/     \/                \/           \/ 
    */

    /**
     * Splits the trimmed URI into particles and filters out any empty values.
     *
     * $this->trimmedUri must be set before calling this method.
     * 
     * This method uses the `explode` function to split the trimmed URI string by the "/" delimiter.
     * It then filters out any empty values from the resulting array using `array_filter`.
     *
     * @return void
     */
    private function SetParticles() : void
    {
        $result = explode("/", $this->trimmedUri);
        $this->particles = array_filter($result, function($value) {
            return !empty($value);
        });
    }

    /**
     * Sets the parameters by filtering the particles array.
     * 
     * $this->particles must be set before calling this method.
     * 
     * This method filters the `$particles` array and assigns the filtered values 
     * to the `$params` property. The filtering is done using the `ParticleIsAParam` 
     * method to determine if a particle should be considered a parameter.
     * 
     * @return void
     */
    private function SetParams() : void
    {
        $this->params = array_filter($this->particles, function($value) {
            return self::ParticleIsAParam($value);
        });
    }

    /**
     * Sets the parametrized URI reference by creating a new instance of the current class
     * with the provided URI and a flag indicating it is parametrized.
     *
     * @param string $uri The URI to be used for creating the parametrized reference.
     *
     * @return void
     */
    public function SetParametrizedUriRefered(string $uri) : void
    {
        $this->parametrizedUriRefered = new self($uri, true);
    }

    /**
  ________        __    __                       
 /  _____/  _____/  |__/  |_  ___________  ______
/   \  ____/ __ \   __\   __\/ __ \_  __ \/  ___/
\    \_\  \  ___/|  |  |  | \  ___/|  | \/\___ \ 
 \______  /\___  >__|  |__|  \___  >__|  /____  >
        \/     \/                \/           \/ 
    */

    public function GetHeadParticle() : string
    {
        return $this->GetParticleByIndex(0);
    }

    public function GetDeterminantParticle() : string
    {
        return $this->GetParticleByIndex(count($this->GetParticles()) - 1);
    }

    public function GetUri(): string
    {
        return $this->uri;
    }

    public function GetTrimmedUri(): string
    {
        return $this->trimmedUri;
    }

    public function GetParticleByIndex(int $index): string|null
    {
        return $this->particles[$index] ?? '/';
    }

    public function GetParticles(): array
    {
        return $this->particles;
    }

    public function GetParticlesCount(): int
    {
        return count($this->particles);
    }

    public function GetParams(): array
    {
        return $this->params;
    }

    public function GetParametrizedUriRefered(): self
    {
        return $this->parametrizedUriRefered;
    }

    public function GetParam(string $param): string
    {
        if(is_null($this->parametrizedUriRefered))
        {
            throw new \Exception("The parametrized URI is not set. Please set it before calling GetParam.");
        }

        $index = array_search(":" . $param, $this->parametrizedUriRefered->GetParticles());
        return $this->GetParticleByIndex($index);
    }

    public function HasQuery() : bool
    {
        return self::UriHasQuery($this->uri);
    }

    public function GetQuerySentence() : string|false
    {
        return $this->querySentence;
    }

    /**
     * Checks if the current URI is an index.
     *
     * @return bool Returns true if the current URI is an index, false otherwise.
     */
    public function IsIndex() : bool
    {
        return self::IsUriAnIndex($this->trimmedUri);
    }

    /**
     * Returns the URI with all parameter placeholders replaced by empty strings.
     *
     * This method iterates through all parameters defined in the route and removes them from the trimmed URI.
     * The resulting URI is then passed to a new URI instance, and its trimmed version is returned.
     *
     * @return string The trimmed URI with parameters removed.
     */
    public function GetUnfilledUri() : string
    {
        // Every :param will be replaced with an empty string.
        $result = $this->trimmedUri;
        foreach ($this->params as $param)
        {
            $result = str_replace($param, "", $result);
        }

        return (new URI($result, true))->GetTrimmedUri();
    }


    /**
     * 
_________                                   .__                 ___________                   __  .__                      
\_   ___ \  ____   _____ ___________ _______|__| ____    ____   \_   _____/_ __  ____   _____/  |_|__| ____   ____   ______
/    \  \/ /  _ \ /     \\____ \__  \\_  __ \  |/    \  / ___\   |    __)|  |  \/    \_/ ___\   __\  |/  _ \ /    \ /  ___/
\     \___(  <_> )  Y Y  \  |_> > __ \|  | \/  |   |  \/ /_/  >  |     \ |  |  /   |  \  \___|  | |  (  <_> )   |  \\___ \ 
 \______  /\____/|__|_|  /   __(____  /__|  |__|___|  /\___  /   \___  / |____/|___|  /\___  >__| |__|\____/|___|  /____  >
        \/             \/|__|       \/              \//_____/        \/             \/     \/                    \/     \/ 
     */

    /**
     * Determines whether the current URI matches the format of the given URI particle by particle.
     *
     * This method performs the following checks:
     * 1. Verifies if the number of particles in the current URI matches the number of particles in the given URI.
     * 2. Iterates through each particle and checks if they match cardinally or as parameters.
     *
     * If all checks pass, the method sets the `parametrizedUriRefered` property to the given URI and returns true.
     * Otherwise, it returns false.
     *
     * @param URI $uri The URI object to compare against.
     * @return bool True if the URI matches the format, false otherwise.
     */
    public function MatchesUriParticleFormmat(URI $uri) : bool
    {
        $totalParticles = $this->GetParticlesCount();

        // 1. First, check if the number of particles is the same.
        if($totalParticles !== $uri->GetParticlesCount())
        {
            return false;
        }

        // 2. Then, check if each particle matches the corresponding particle in the given URI.
        for($i = 0; $i < $totalParticles; $i++)
        {
            // If the particles do not match cardinally, check if they are parameters.
            if(!$this->MatchesCardinalParticleAmongURIs($uri, $i))
            {
                return false;
            }
        }

        $this->parametrizedUriRefered = $uri;
        return true;
    }

    /**
     * Determines if the particle at the specified index matches between the current URI and the given URI.
     * A match is considered valid if the particles are equal or if either particle is a parameter.
     *
     * @param URI $uri The URI object to compare against.
     * @param int $index The index of the particle to compare.
     * @return bool Returns true if the particles match or if either particle is a parameter, false otherwise.
     */
    public function MatchesCardinalParticleAmongURIs(URI $uri, int $index) : bool
    {
        // Get the particles from both URIs at the specified index.
        $thisParticle = $this->GetParticleByIndex($index);
        $uriParticle = $uri->GetParticleByIndex($index);

        $isEqual = ($thisParticle === $uriParticle);
        $isParam = self::ParticleIsAParam($uriParticle) || self::ParticleIsAParam($thisParticle);

        return $isEqual || $isParam;
    }
}