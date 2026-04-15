<?php
namespace API;
require_once "contenttype.php";
require_once "method.php";

use Composer\Composer;
use API\ContentType;
use API\Method;

/**
 * Class MessageAPI
 * 
 * This class handles the API management for messages. It processes incoming requests,
 * validates and sets the API name and parameters, and prepares and sends responses.
 * 
 * @property string $apiName The name of the API being called.
 * @property array $apiParamsArray The parameters for the API in array format.
 * @property string $apiParamsJSON The parameters for the API in JSON format.
 * @property array $receivedContentArray The content received from the request.
 * @property array $response The response to be sent back to the client.
 * @property Method $apiMethod The HTTP method used for the request.
 * @property ContentType $receivedContentProcessedType The content type of the received request.
 * @property static self $instance The singleton instance of the class.
 * 
 * @method __construct() Initializes the class and sets the singleton instance.
 * @method void __init() Initializes the class properties and processes the request.
 * @method void SetReceivedContent(array $receivedContentArray) Sets the received content and validates the API name and parameters.
 * @method void SetApiParamsArrayFromJSON(string $apiParamsJSON) Sets the API parameters array from a JSON string.
 * @method void PrepareResponse(string $key, string $value) Prepares a response with a key-value pair.
 * @method void PrepareCompoundResponse(array $responseArray) Prepares a compound response by merging with the existing response.
 * @method bool PrepareResponseFromAssociativeJSON(string $json) Prepares a response from an associative JSON string.
 * @method void SendResponse() Sends the prepared response as a JSON string and exits.
 * @method Method GetMethod() Retrieves the HTTP method used for the request.
 * @method array GetReceivedContentTypes() Retrieves the content types from the request.
 * @method ContentType ReceivedContentProcessedType() Determines the processed content type from the request.
 * @method static self GetInstance() Retrieves the singleton instance of the class.
 * @method void ServeApi() Serves the API by including the API file.
 * @method string GetApiName() Retrieves the API name.
 * @method string GetApiParamsJSON() Retrieves the API parameters in JSON format.
 * @method object GetApiParamsObject() Retrieves the API parameters as an object.
 * @method array GetInputSendedData() Retrieves the input data sent in the request.
 * @method array GetTrueMethodArray() Retrieves the true method array based on the HTTP method.
 * @method array GetAllSendedContentAccordingToReceivedContentType() Retrieves all sent content according to the received content type.
 * @method mixed GetReceivedParamValue(string $paramName) Retrieves the value of a received parameter.
 * @method Method GetApiMethod() Retrieves the API method.
 * @method ContentType GetApiContentType() Retrieves the API content type.
 * @method array GetApiReceivedContentArray() Retrieves the received content array.
 * @method string GetApiPath() Retrieves the path to the API file.
 * @method bool ApiExists() Checks if the API file exists.
 * @method static string GetApisFolderAbsolutePath() Retrieves the absolute path to the APIs folder.
 */
class MessageAPI
{
    private string $apiName;
    private array $apiParamsArray;
    private string $apiParamsJSON;
    private array $receivedContentArray;
    private array $response;

    private Method|null $apiMethod;
    private ContentType|null $receivedContentProcessedType;
    private static self $instance;

    /**
     * Constructor for the class.
     * 
     * This constructor checks if an instance of the class already exists.
     * If an instance exists, it returns that instance.
     * Otherwise, it initializes the class and sets the instance.
     */
    public function __construct()
    {
        if (self::$instance ?? null)
        {
            return self::$instance;
        }

        $this->__init();
        self::$instance = $this;
    }

    /**
     * This class handles the management of API requests and responses.
     *
     * The class is responsible for initializing the API management process,
     * processing the received content, and setting up the necessary properties
     * for handling the API request.
     *
     * Methods:
     *   - Sets the API method using GetMethod().
     *   - Sets the received content processed type using ReceivedContentProcessedType().
     *   - Retrieves all sent content according to the received content type using GetAllSendedContentAccordingToReceivedContentType().
     *   - Sets the received content using SetReceivedContent() with the array from GetApiReceivedContentArray().
     *   - Initializes the response property as an empty array.
     *
     * @return void
     */
    private function __init() : void
    {
        // Order of execution is important here
        $this->apiMethod = $this->GetMethod();
        $this->receivedContentProcessedType = $this->ReceivedContentProcessedType();

        if($this->receivedContentProcessedType !== null)
        {
            $this->receivedContentArray = $this->GetAllSendedContentAccordingToReceivedContentType();
            $this->SetReceivedContent($this->GetApiReceivedContentArray());

            $this->response = [];
            return;
        }

        $this->response = [];
        $this->apiName = "not_received";
    }

    /**
     * Sets the received content from the API request.
     *
     * @param array $receivedContentArray The array containing the API request data.
     *
     * @throws \Exception If the API name or API parameters are not received in the request.
     *
     * The function expects the array to contain the following keys:
     * - "API_NAME": The name of the API being called.
     * - "API_PARAMS": The parameters for the API call, which can be either an array or a JSON string.
     *
     * If the "API_PARAMS" is an array, it will be stored in $this->apiParamsArray and also encoded to JSON and stored in $this->apiParamsJSON.
     * If the "API_PARAMS" is a JSON string, it will be decoded and stored in $this->apiParamsArray and also stored as is in $this->apiParamsJSON.
     *
     * The API name will be stored in $this->apiName.
     */
    private function SetReceivedContent(array $receivedContentArray) : void
    {
        $apiName = $receivedContentArray["API_NAME"] ?? null;
        $apiParams = $receivedContentArray["API_PARAMS"] ?? $this->GetTrueMethodArray();

        if(!$apiName)
        {
            throw new \Exception("The API name was not received in the API request.");
        }

        if(!$apiParams)
        {
            throw new \Exception("The API parameters were not received for the API $apiName.");
        }

        if(is_array($apiParams))
        {
            $this->apiParamsArray = $apiParams;
            $this->apiParamsJSON = json_encode($apiParams);
        }

        if(is_string($apiParams))
        {
            $this->SetApiParamsArrayFromJSON($apiParams);
            $this->apiParamsJSON = $apiParams;
        }

        $this->apiName = $apiName;
    }

    /**
     * Sets the API parameters array from a JSON string.
     *
     * This method decodes a JSON string into an associative array and assigns it
     * to the `apiParamsArray` property. If the JSON string is invalid, an exception
     * is thrown.
     *
     * @param string $apiParamsJSON The JSON string containing the API parameters.
     * 
     * @throws \Exception If the JSON string is not in a valid format.
     */
    private function SetApiParamsArrayFromJSON(string $apiParamsJSON) : void
    {
        try
        {
            $this->apiParamsArray = json_decode($apiParamsJSON, true);
        }
        catch (\Exception $e)
        {
            throw new \Exception("Invalid JSON format for API parameters.");
        }
    }

    /**
     * Prepares a response by associating a key with a value.
     *
     * @param string $key The key to associate with the value in the response.
     * @param string|bool|int|float|array $value The value to be associated with the key. 
     *        It can be of type string, boolean, integer, float, or array.
     *
     * @return void This method does not return a value.
     */
    public function PrepareResponse(string $key, string|bool|int|float|array $value) : void
    {
        $this->response[$key] = $value;
    }

    /**
     * Prepares a compound response by merging the given response array with the existing response.
     *
     * @param array $responseArray The array to be merged with the existing response.
     *
     * @return void
     */
    public function PrepareCompoundResponse(array $responseArray) : void
    {
        $this->response += $responseArray;
    }

    /**
     * Prepares a response from an associative JSON string.
     *
     * This method decodes a JSON string into an associative array and then
     * prepares a compound response using the decoded array.
     *
     * @param string $json The JSON string to be decoded and processed.
     * @return bool Returns true if the response was successfully prepared.
     * @throws \Exception If the JSON string is invalid or cannot be decoded.
     */
    public function PrepareResponseFromAssociativeJSON(string $json) : bool
    {
        try
        {
            $responseArray = json_decode($json, true);
            $this->PrepareCompoundResponse($responseArray);
            return true;
        }
        catch (\Exception $e)
        {
            throw new \Exception("Invalid JSON format trying to prepare a response.");
        }

        return false;
    }

    /**
     * Sends the response as a JSON-encoded string and terminates the script.
     *
     * This method encodes the response data stored in the `$this->response` property
     * to a JSON format and outputs it. After sending the response, it calls `exit()`
     * to terminate the script execution.
     *
     * @return void
     */
    public function SendResponse() : void
    {
        echo json_encode($this->response);
        exit();
    }

    /**
     * Retrieves the HTTP request method and returns the corresponding Method enum.
     *
     * @return Method The HTTP request method as a Method enum.
     * @throws \Exception If the request method is not supported.
     */
    public function GetMethod() : Method|null
    {
        $method = $_SERVER["REQUEST_METHOD"];

        if ($method == "GET")
        {
            return Method::GET;
        }
        else if ($method == "POST")
        {
            return Method::POST;
        }

        //throw new \Exception("The method '$method' is not supported.");

        return null;
    }

    /**
     * Retrieves the content types from the received HTTP request.
     *
     * This method extracts the content types from the `CONTENT_TYPE` header
     * of the incoming HTTP request and returns them as an array.
     *
     * @return array An array of content types extracted from the `CONTENT_TYPE` header.
     */
    public function GetReceivedContentTypes() : array
    {
        return isset($_SERVER["CONTENT_TYPE"]) ? explode(";", $_SERVER["CONTENT_TYPE"]) : [];
    }

    /**
     * Determines the processed content type of the received content.
     *
     * This method retrieves the content types of the received content and returns
     * the corresponding ContentType enum based on the first content type in the list.
     *
     * @return ContentType The processed content type.
     * @throws \Exception If the content type is not supported.
     */
    public function ReceivedContentProcessedType() : ContentType|null
    {
        $contentTypes = $this->GetReceivedContentTypes();
        $contentType = $contentTypes[0] ?? null;

        if ($contentType == "application/json")
        {
            return ContentType::JSON_TEXT;
        }
        else if ($contentType == "multipart/form-data")
        {
            return ContentType::FORM_DATA;
        }
        else if ($contentType == "application/x-www-form-urlencoded")
        {
            return ContentType::FORM_URLENCODED;
        }
        else if ($contentType == "application/xml")
        {
            return ContentType::XML;
        }

        //throw new \Exception("The content type '$contentType' is not supported.");
        return null;
    }

    /**
     * Retrieves the files received in a multipart/form-data request.
     *
     * This method checks if the content type of the API request is 'multipart/form-data'.
     * If it is, it returns the $_FILES array containing the uploaded files.
     * If the content type is not 'multipart/form-data', it throws an exception.
     *
     * @return array|null The array of uploaded files if the content type is 'multipart/form-data', otherwise null.
     * @throws \Exception If the content type is not 'multipart/form-data'.
     */
    public function GetReceivedFiles() : array|null
    {
        return $this->GetApiContentType() === ContentType::FORM_DATA ? $_FILES : throw new \Exception("The content type is not 'multipart/form-data'. This method only works for 'multipart/form-data'.");
    }

    /**
     * Retrieves a received file based on the provided file header.
     *
     * @param string $fileHead The header of the file to retrieve.
     * @param bool $throwError Optional. Whether to throw an exception if the file is not found. Default is true.
     * @return array|null The received file as an associative array, or null if not found.
     * @throws \Exception If the file is not found and $throwError is true.
     */
    public function GetReceivedFile(string $fileHead, bool $throwError = true) : array|null
    {
        $file = $this->GetReceivedFiles()[$fileHead] ?? null;

        if (!$file && $throwError)
        {
            throw new \Exception("The file with the header '$fileHead' was not received.");
        }

        return $file;
    }

    /**
     * Get the singleton instance of the class.
     *
     * @return self The singleton instance of the class.
     */
    public static function GetInstance(): self
    {
        return self::$instance ?? new self();
    }

    /**
     * ServeApi method
     *
     * This method includes the API file specified by the GetApiPath method.
     * It is used to serve the API by requiring the appropriate file.
     *
     * @return void
     */
    public function ServeApi()
    {
        require_once $this->GetApiPath();
    }

    /**
     * Retrieves the name of the API.
     *
     * @return string The name of the API.
     */
    public function GetApiName() : string
    {
        return $this->apiName;
    }

    /**
     * GetApiParamsJSON
     *
     * This method returns the API parameters in JSON format.
     *
     * @return string The API parameters in JSON format.
     */
    public function GetApiParamsJSON() : string
    {
        return $this->apiParamsJSON;
    }

    /**
     * Retrieves the API parameters as an object.
     *
     * This method decodes the JSON string stored in the `apiParamsJSON` property
     * and returns it as a PHP object.
     *
     * @return object The decoded JSON object containing the API parameters.
     */
    public function GetApiParamsObject() : object
    {
        return json_decode($this->apiParamsJSON);
    }

    /**
     * Retrieves and decodes JSON input data from the request body.
     *
     * This method reads the raw input data from the 'php://input' stream,
     * which contains the request body, and decodes it from JSON format
     * into an associative array.
     *
     * @return array The decoded JSON data as an associative array.
     */
    public function GetInputSendedData() : array|null
    {
        //Only works for JSON content type
        return json_decode(file_get_contents('php://input'), true) ?? null;
    }

    /**
     * Retrieves the request parameters based on the HTTP method.
     *
     * This method returns the parameters from the global $_GET array if the HTTP method is GET,
     * or from the global $_POST array if the HTTP method is POST. If the HTTP method is neither
     * GET nor POST, an exception is thrown.
     *
     * @return array The request parameters.
     * @throws \Exception If the HTTP method is not supported.
     */
    public function GetTrueMethodArray() : array
    {
        if ($this->apiMethod == Method::GET)
        {
            return $_GET;
        }
        else if ($this->apiMethod == Method::POST)
        {
            return $_POST;
        }
        else
        {
            throw new \Exception("The method '$this->apiMethod' is not supported.");
        }
    }

    /**
     * Retrieves all sent content based on the type of received content.
     *
     * This method processes the received content type and returns the appropriate
     * sent data. It supports JSON text, form data, URL-encoded form data, and XML.
     *
     * @return array The array of sent content data.
     * @throws \Exception If the received content type is not supported.
     */
    public function GetAllSendedContentAccordingToReceivedContentType() : array
    {
        if ($this->receivedContentProcessedType == ContentType::JSON_TEXT)
        {
            return $this->GetInputSendedData();
        }
        else if ($this->receivedContentProcessedType == ContentType::FORM_DATA
        || $this->receivedContentProcessedType == ContentType::FORM_URLENCODED
        || $this->receivedContentProcessedType == ContentType::XML)
        {
            return $this->GetTrueMethodArray();
        }
        else
        {
            throw new \Exception("The content type '$this->receivedContentProcessedType' is not supported.");
        }
    }


    public static function ValidRequest() : bool
    {
        return isset($_SERVER["REQUEST_METHOD"]) && in_array($_SERVER["REQUEST_METHOD"], ["GET", "POST"]) && isset($_SERVER["CONTENT_TYPE"]);
    }

    /**
     * Checks if a specific parameter has been received in the API request.
     *
     * @param string $paramName The name of the parameter to check.
     * @return bool Returns true if the parameter exists in the API parameters array, false otherwise.
     */
    public function HasReceivedParam(string $paramName) : bool
    {
        return isset($this->apiParamsArray[$paramName]);
    }

    /**
     * Retrieves the value of a received parameter from the API parameters array.
     *
     * @param string $paramName The name of the parameter to retrieve.
     * @return mixed The value of the requested parameter.
     * @throws \Exception If the parameter is not found in the API parameters array.
     */
    public function GetReceivedParamValue(string $paramName) : mixed
    {
        return $this->apiParamsArray[$paramName] ?? throw new \Exception("The parameter '$paramName' was not received for the API $this->apiName.");
    }

    /**
     * Retrieves the API method.
     *
     * @return Method The API method.
     */
    public function GetApiMethod() : Method
    {
        return $this->apiMethod;
    }

    /**
     * Retrieves the content type of the received API content.
     *
     * @return ContentType The processed type of the received content.
     */
    public function GetApiContentType() : ContentType
    {
        return $this->receivedContentProcessedType;
    }

    /**
     * Retrieves the array of received content.
     *
     * @return array The array containing the received content.
     */
    public function GetApiReceivedContentArray() : array
    {
        return $this->receivedContentArray;
    }

    /**
     * Retrieves the full path to the API file.
     *
     * This method constructs the absolute path to the API file by combining
     * the absolute path to the APIs folder, the API name, and the ".php" extension.
     *
     * @return string The full path to the API file.
     */
    public function GetApiPath() : string
    {
        return self::GetApisFolderAbsolutePath() . $this->apiName . ".php";
    }

    /**
     * Checks if the API file exists at the specified path.
     *
     * @return bool Returns true if the API file exists, false otherwise.
     */
    public function ApiExists() : bool
    {
        return file_exists($this->GetApiPath());
    }
    
    /**
     * Retrieves the absolute path to the APIs folder.
     *
     * @return string The absolute path to the APIs folder.
     */
    public static function GetApisFolderAbsolutePath(): string
    {
        return Composer::GetAbsoluteProjectRoot() . "public/apis/";
    }
}