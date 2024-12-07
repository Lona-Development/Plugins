<?php

namespace LonaDB\Plugins;

use LonaDB\Plugins\Request;
use LonaDB\Plugins\Response;
use LonaDB\Plugins\Webinterface;

class Server {
    private int $port;
    private $socket;
    private array $routes = [];
    private bool $listening = false;
    private Webinterface $plugin;

    public function __construct(Webinterface $plugin, int $port) {
        $this->plugin = $plugin;
        $this->port = $port;
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
        $this->socket = socket_create_listen($this->port);

        if ($this->socket == false) {
            $this->plugin->getLogger()->Plugin($this->plugin->getName(), "Failed to create socket: " . socket_strerror(socket_last_error()) . "\n");
            return;
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
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
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'regex' => '/^' . $regex . '$/', // Dynamisches Regex-Matching
            'handler' => $handler,
        ];
    }

    private function startServer(): void {
        if(!$this->socket) return;

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
    
            $headers = [];
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
            $queryParams = [];
            $queryString = parse_url($uri, PHP_URL_QUERY) ?? '';
            parse_str($queryString, $queryParams);
    
            // Parse body content based on Content-Type
            $parsedBody = [];
            if (isset($headers['content-type']) || isset($headers['Content-Type'])) {
                if (strpos($headers['content-type'], 'application/json') !== false || strpos($headers['Content-Type'], 'application/json') !== false) {
                    // Parse JSON body
                    $parsedBody = json_decode($body, true) ?? [];
                } elseif (strpos($headers['content-type'], 'application/x-www-form-urlencoded') !== false || strpos($headers['Content-Type'], 'application/x-www-form-urlencoded') !== false) {
                    // Parse URL-encoded body
                    parse_str($body, $parsedBody);
                }
            }
    
            // Routing logic
            $params = [];
            $routed = false;
            $response = new Response($client, $this->plugin, $sessionData, $sessionId);  // Pass session data
    
            // Route matching logic
            foreach ($this->routes as $route) {
                if ($route['method'] === $method && preg_match($route['regex'], $path, $matches)) {
                    array_shift($matches);  // Remove full match
                    preg_match_all('/:([\w]+)/', $route['path'], $paramKeys);
                    $paramKeys = $paramKeys[1];
                    $params = array_combine($paramKeys, $matches);
    
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
    
    private function encryptData(array $data, string $key): string {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(AES_256_CBC));
        $encrypted = openssl_encrypt(json_encode($data), AES_256_CBC, $key, 0, $iv);
        return $encrypted . ':' . base64_encode($iv);
    }
    
    private function decryptData(string $data, string $key): ?array {
        $parts = explode(':', $data);
        if (count($parts) !== 2) {
            return null; // Ungültiges Format
        }
    
        $decrypted = openssl_decrypt($parts[0], AES_256_CBC, $key, 0, base64_decode($parts[1]));
        return json_decode($decrypted, true);
    }   
    
    private function getBasePath(): string {
        return \Phar::running() ? dirname(dirname(\Phar::running(false))) : ".";
    }    

    private function getSessionData(?string $sessionId): array {
        if (!is_dir("./data/plugins")) mkdir("./data/plugins");
        if (!is_dir("./data/plugins/Webinterface")) mkdir("./data/plugins/Webinterface");
        if (!is_dir("./data/plugins/Webinterface/sessions")) mkdir("./data/plugins/Webinterface/sessions");
        
        $basePath = $this->getBasePath();
        $filePath = "{$basePath}/data/plugins/Webinterface/sessions/{$sessionId}.lona";
    
        // Überprüfe, ob die Datei existiert
        if (file_exists($filePath)) {
            $encryptedData = file_get_contents($filePath);
    
            // Entschlüssele die Daten
            $decryptedData = $this->decryptData($encryptedData, $this->plugin->getLonaDB()->config["encryptionKey"]);
            if ($decryptedData !== null) {
                return $decryptedData;
            }
        }
    
        // Rückgabe leerer Daten, falls keine gültigen Daten vorhanden sind
        return [];
    }    
    
    private function createSessionFile(string $sessionId): void {
        $basePath = $this->getBasePath();
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
}
