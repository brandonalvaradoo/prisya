<?php
namespace Router;

require_once "uri.php";
use Router\URI;

class ServerRequest
{
    private string $method;
    private string $uri;
    private string $realUri;
    private string $host;
    private $headers;
    private $body;
    private $queryParams;
    private $postParams;
    private $cookies;

    private URI $uriObject;

    public function __construct()
    {
        $this->method = $_SERVER["REQUEST_METHOD"];
        $this->uri = $_SERVER["REQUEST_URI"];
        $this->realUri = URI::GetRealUri($this->uri);
        $this->host = $_SERVER["HTTP_HOST"];
        $this->headers = $this->RetrieveAllHeaders();
        $this->body = file_get_contents("php://input");
        $this->queryParams = $_GET;
        $this->postParams = $_POST;
        $this->cookies = $_COOKIE;
        $this->uriObject = new URI($this->realUri);
    }

    public static function RetrieveAllHeaders() : array
    {
        if(function_exists("getallheaders"))
        {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value)
        {
            if (substr($name, 0, 5) == 'HTTP_')
            {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }

    public function GetMethod(): string
    {
        return $this->method;
    }

    public function GetUri(): string
    {
        return $this->uri;
    }

    public function GetHost(): string
    {
        return $this->host;
    }

    public function GetHeaders(): array
    {
        return $this->headers;
    }

    public function GetBody(): string
    {
        return $this->body;
    }

    public function GetQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function GetPostParam(string $key, mixed $default = null): mixed
    {
        return $this->postParams[$key] ?? $default;
    }

    public function GetCookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function HasHeader(string $key): bool
    {
        return isset($this->headers[$key]);
    }

    public function GetHeader(string $key, mixed $default = null): mixed
    {
        return $this->headers[$key] ?? $default;
    }

    public function GetJsonBody(): array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function GetClientIP(): string
    {
        return $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";
    }

    public function GetUserAgent(): string
    {
        return $_SERVER["HTTP_USER_AGENT"] ?? "Unknown";
    }

    public function GetAsJSON(): string
    {
        return json_encode([
            "method" => $this->method,
            "uri" => $this->uri,
            "headers" => $this->headers,
            "body" => $this->body,
            "queryParams" => $this->queryParams,
            "postParams" => $this->postParams,
            "cookies" => $this->cookies,
            "clientIP" => $this->GetClientIP(),
            "userAgent" => $this->GetUserAgent()
        ]);
    }

    public function GetRealUri(): string
    {
        return $this->realUri;
    }

    public function GetUriObject(): URI
    {
        return $this->uriObject;
    }
}
