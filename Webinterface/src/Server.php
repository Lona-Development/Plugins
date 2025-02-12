<?php

namespace LonaDB\Plugin\Webinterface;

define('AES_256_CBC', 'aes-256-cbc');

use LonaDB\Plugin\Webinterface\Request;
use LonaDB\Plugin\Webinterface\Response;
use LonaDB\Plugin\Webinterface\Main;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;

class Server extends ThreadSafe
{
    private int $port;
    private $socket;
    private ThreadSafeArray $routes;
    private bool $listening = false;
    private Main $plugin;

    public function __construct(Main $plugin, int $port) {
        $this->plugin = $plugin;
        $this->port = $port;
        $this->routes = new ThreadSafeArray();
        $this->initializeSocket();
    }

    public function listen(): void {
        if (!$this->listening) {
            $this->listening = true;
            $this->startServer();
        }
    }

    public function stop(): void {
        socket_close($this->socket);
        exit('Server shutting down...' . PHP_EOL);
    }

    private function initializeSocket(): void {
        error_reporting(E_ERROR | E_PARSE);
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        //$this->socket = socket_create_listen($this->port);

        if ($this->socket == false) {
            $this->plugin->getLogger()->Plugin($this->plugin->getName(), "Failed to create socket: " . socket_strerror(socket_last_error()) . "\n");
            return;
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEPORT, 1);

        if (!socket_bind($this->socket, '0.0.0.0', $this->port)) {
            $this->plugin->getLogger()->Plugin($this->plugin->getName(), "Failed to bind socket: " . socket_strerror(socket_last_error()) . "\n");
            return;
        }

        $this->plugin->getLogger()->Plugin($this->plugin->getName(), "Server running on port {$this->port}\n");
    }

    public function get(string $path, callable $handler): void {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable $handler): void {
        $regex = preg_replace('/:\w+/', '(\w+)', $path); // Ersetzt `:name` durch `(\w+)` (Regex-Pattern)
        $regex = str_replace('/', '\/', $regex); // Escape `/` für Regex
        $this->routes[] = ThreadSafeArray::fromArray([
            'method' => $method,
            'path' => $path,
            'regex' => '/^' . $regex . '$/', // Dynamisches Regex-Matching
            'handler' => $handler,
        ]);
    }
 
    private function startServer(): void {
        if(!$this->socket || !socket_listen($this->socket)) return;

        while (true) {
            $client = socket_accept($this->socket);
            if ($client === false) {
                print("Failed to accept client connection: " . socket_strerror(socket_last_error()) . "\n");
                continue;
            }

            $data = socket_read($client, 10240);
            if ($data === false) {
                print("Failed to read data from client: " . socket_strerror(socket_last_error()) . "\n");
                continue;
            }

            $this->handleRequest($data, $client);
        }
    }

    private function handleRequest(string $data, $client): void {
        try {
            // Session handling (same as your implementation)
            $sessionId = $this->getSessionIdFromRequest($data);
            $sessionData = $this->getSessionData($sessionId);
    
            // Split headers and body
            $lines = explode("\r\n", $data);
            $requestLine = explode(" ", $lines[0]);
            $method = $requestLine[0] ?? 'GET';
            $uri = $requestLine[1] ?? '/';
            $path = parse_url($uri, PHP_URL_PATH);
    
            $headers = new ThreadSafeArray();
            $body = '';
            $isBody = false;
    
            // Separate headers from body
            foreach ($lines as $line) {
                if (strlen($line) == 0) {
                    $isBody = true;  // Blank line indicates the start of the body
                    continue;
                }
                if (!$isBody) {
                    // Extract headers
                    list($key, $value) = explode(":", $line, 2);
                    if (isset($key, $value)) {
                        $headers[trim($key)] = trim($value);
                    }
                } else {
                    // Collect body data
                    $body .= $line . "\r\n";  // Append body content
                }
            }
    
            // Parse query parameters
            $queryParams = new ThreadSafeArray();
            $queryString = parse_url($uri, PHP_URL_QUERY) ?? '';
            parse_str($queryString, $queryParams);
            // If type is array, convert to ThreadSafeArray, safety check because of the parse_str function
            if(is_array($queryParams))
                $queryParams = ThreadSafeArray::fromArray($queryParams);
    
            // Parse body content based on Content-Type
            $parsedBody = new ThreadSafeArray();
            if (isset($headers['content-type']) || isset($headers['Content-Type'])) {
                if (strpos($headers['content-type'], 'application/json') !== false || strpos($headers['Content-Type'], 'application/json') !== false) {
                    // Parse JSON body
                    $parsedBody = ThreadSafeArray::fromArray(json_decode($body, true)) ?? new ThreadSafeArray();
                } elseif (strpos($headers['content-type'], 'application/x-www-form-urlencoded') !== false || strpos($headers['Content-Type'], 'application/x-www-form-urlencoded') !== false) {
                    // Parse URL-encoded body
                    parse_str($body, $parsedBody);
                }
            }
            // If type is array, convert to ThreadSafeArray, safety check because of the parse_str function
            if(is_array($parsedBody))
                $parsedBody = ThreadSafeArray::fromArray($parsedBody);
    
            // Routing logic
            $params = new ThreadSafeArray();
            $routed = false;
            $response = new Response($client, $this->plugin, $sessionId, $sessionData);  // Pass session data
    
            // Route matching logic
            foreach ($this->routes as $route) {
                if ($route['method'] === $method && preg_match($route['regex'], $path, $matchesTemp)) {
                    if(is_array($matchesTemp))
                        $matchesTemp = ThreadSafeArray::fromArray($matchesTemp);
                    $matchesTemp->shift();
                    // Remove 1 from every index (if int) and delete the last element
                    $highest = 0;
                    $matches = new ThreadSafeArray();
                    foreach ($matchesTemp as $key => $match) {
                        if (is_int($key)) {
                            $matches[$key-1] = $match;
                            if($key > $highest) $highest = $key;
                        }
                    }
                    preg_match_all('/:([\w]+)/', $route['path'], $paramKeys);
                    $paramKeys = ThreadSafeArray::fromArray($paramKeys);
                    $paramKeys = $paramKeys[1];
                    $params = $this->threadSafeArrayCombine($paramKeys, $matches);

                    // Create request with parsed body
                    $request = new Request($method, $path, $headers, $queryParams, $parsedBody, $params, $sessionData);
                    $route['handler']($request, $response);
                    $routed = true;
                    break;
                }
            }
    
            if (!$routed) {
                $response->send('404 Not Found');
            }
    
            socket_close($client);
        } catch (\Exception $e) {
            print("Error: " . $e->getMessage() . "\n");
        }
    }        

    private function getSessionIdFromRequest(string $data): ?string {
        // Match the PHPSESSID cookie value in the request headers
        preg_match('/PHPSESSID=([^;]+)LDBCKI/', $data, $matches);
        // Return the session ID if found, or generate a new one
        $id = str_replace("PHPSESSID=", "", $matches[0]); 
        if($id == "") $id = bin2hex(random_bytes(16)) . "LDBCKI"; 
        return $id;
    }
    
    private function encryptData(ThreadSafeArray $data, string $key): string {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = openssl_encrypt(json_encode($data->toArray()), 'AES-256-CBC', $key, 0, $iv);
        return $encrypted . ':' . base64_encode($iv);
    }

    private function decryptData(string $data, string $key): ThreadSafeArray {
        $parts = explode(':', $data);
        if (count($parts) !== 2) {
            return new ThreadSafeArray();
        }

        $decrypted = openssl_decrypt($parts[0], 'AES-256-CBC', $key, 0, base64_decode($parts[1]));
        return ThreadSafeArray::fromArray(json_decode($decrypted, true) ?? []);
    }

    private function getSessionData(?string $sessionId): ThreadSafeArray {
        if (!is_dir("./data/plugins")) mkdir("./data/plugins");
        if (!is_dir("./data/plugins/Webinterface")) mkdir("./data/plugins/Webinterface");
        if (!is_dir("./data/plugins/Webinterface/sessions")) mkdir("./data/plugins/Webinterface/sessions");
        
        $basePath = $this->plugin->getLonaDB()->getBasePath();
        $filePath = "{$basePath}/data/plugins/Webinterface/sessions/{$sessionId}.lona";
    
        // Überprüfe, ob die Datei existiert
        if (file_exists($filePath)) {
            $encryptedData = file_get_contents($filePath);
    
            // Entschlüssele die Daten
            $decryptedData = $this->decryptData($encryptedData, $this->plugin->getLonaDB()->config["encryptionKey"]);
            if ($decryptedData !== null) {
                if(is_array($decryptedData))
                    $decryptedData = ThreadSafeArray::fromArray($decryptedData);
                return $decryptedData;
            }
        }
    
        // Rückgabe leerer Daten, falls keine gültigen Daten vorhanden sind
        return new ThreadSafeArray();
    }    
    
    private function createSessionFile(string $sessionId): void {
        $basePath = $this->plugin->getLonaDB()->getBasePath();
        $filePath = "{$basePath}/data/plugins/Webinterface/sessions/{$sessionId}.lona";
    
        if (!file_exists($filePath)) {
            // Verschlüsselte leere Session-Daten speichern
            $encryptedData = $this->encryptData([], $this->plugin->getLonaDB()->config["encryptionKey"]);
            file_put_contents($filePath, $encryptedData);
        }
    }    
    
    private function setSessionIdCookie(string $sessionId): void {
        // Set the session ID as a cookie in the response headers
        setcookie("PHPSESSID", $sessionId, time() + 3600, "/");  // Expires in 1 hour
    }

    public static function threadSafeArrayCombine(ThreadSafeArray $keys, ThreadSafeArray $values): ThreadSafeArray {
        if (count($keys) !== count($values)) {
            return null; // array_combine returns false if the sizes don't match
        }

        $combined = new ThreadSafeArray();
        foreach ($keys as $index => $key) {
            if (!is_scalar($key)) {
                return null; // array_combine does not allow non-scalar keys
            }
            $combined[$key] = $values[$index];
        }

        return $combined;
    }
}
