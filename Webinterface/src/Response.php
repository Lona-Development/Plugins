<?php

namespace LonaDB\Plugin\Webinterface;

use LonaDB\Plugin\Webinterface\Main;

class Response {
    private int $status;
    private string $content;
    private string $type;
    private $client;
    private array $session = [];
    private $sessionId;
    private Main $plugin;

    public function __construct($client, Main $plugin, array $session = [], $sessionId, int $status = 200, string $content = '', string $type = 'text/html') {
        $this->status = $status;
        $this->session = $session;
        $this->content = $content;
        $this->type = $type;
        $this->client = $client;
        $this->sessionId = $sessionId;
        $this->plugin = $plugin;
    }

    public function send(string $content): void {
        $this->content = $content;
        $this->type = 'text/plain';  // Standard type for 'send'
        $this->sendResponse();
    }

    public function json(array $data): void {
        $this->content = json_encode($data);
        $this->type = 'application/json';
        $this->sendResponse();
    }

    public function render(string $file, array $arguments = []): void {
        // Base directory for files
        $basePath = \Phar::running(true) ?: __DIR__;
        if(str_ends_with($basePath, ".phar")) $basePath .= "/src";
    
        // Get the absolute path for the file
        $absolutePath = $basePath . DIRECTORY_SEPARATOR . $file;
    
        if (file_exists($absolutePath)) {
            ob_start();
            include $absolutePath;
            $this->content = ob_get_clean();
            $this->type = 'text/html';
            $this->sendResponse();
        } else {
            $this->status = 404;
            $this->content = "File not found: $absolutePath";
            $this->type = 'text/plain';
            $this->sendResponse();
        }
    }

    public function redirect(string $url): void {
        $this->content = "<script>window.location.replace('{$url}');</script>";
        $this->type = 'text/html';  // Standard type for 'send'
        $this->sendResponse();
    }

    public function setSessionValue(string $key, mixed $value): void {
        $this->session[$key] = $value;  // Set session data
    }

    private function encryptData(array $data, string $key): string {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(AES_256_CBC));
        $encrypted = openssl_encrypt(json_encode($data), AES_256_CBC, $key, 0, $iv);
        return $encrypted . ':' . base64_encode($iv);
    }

    private function getBasePath(): string {
        return \Phar::running() ? dirname(dirname(\Phar::running(false))) : ".";
    }    

    private function saveSessionData(): void {
        // Überprüfe, ob eine Session-ID existiert
        if ($this->sessionId) {
            $basePath = $this->getBasePath();
            $filePath = "{$basePath}/data/plugins/Webinterface/sessions/{$this->sessionId}.lona";
    
            // Verschlüssele die Session-Daten
            $encryptedData = $this->encryptData($this->session, $this->plugin->getLonaDB()->config["encryptionKey"]);
    
            // Schreibe die verschlüsselten Daten in die Datei
            file_put_contents($filePath, $encryptedData);
        }
    }    

    private function sendResponse(): void {
        // Prepare the response headers
        $headers = "HTTP/1.1 {$this->status} OK\r\n";
        
        $headers .= "Set-Cookie: PHPSESSID={$this->sessionId}; path=/; HttpOnly; SameSite=Strict\r\n";
                
        // Add the content-length and content-type headers
        $headers .= "Content-Length: " . strlen($this->content) . "\r\n";
        $headers .= "Content-Type: {$this->type}\r\n\r\n";
        

        // Send the headers and content to the client
        socket_write($this->client, $headers . $this->content);
        
        // Save session data after sending the response
        $this->saveSessionData();
    }    
}
