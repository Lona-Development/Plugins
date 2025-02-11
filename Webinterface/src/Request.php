<?php

namespace LonaDB\Plugin\Webinterface;

use pmmp\thread\ThreadSafeArray;
use pmmp\thread\ThreadSafe;

class Request extends ThreadSafe
{
    private string $method;
    private string $path;
    private ThreadSafeArray $headers;
    private ThreadSafeArray $queryParams;
    private ThreadSafeArray $body;
    private ThreadSafeArray $params;
    private ThreadSafeArray $session;  // Session-Daten hinzufügen

    public function __construct(
        string $method,
        string $path,
        ThreadSafeArray $headers,
        ThreadSafeArray $queryParams,
        ThreadSafeArray $body = null,
        ThreadSafeArray $params = null,
        ThreadSafeArray $session = null  // Session-Daten hinzufügen
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->headers = $headers ?? new ThreadSafeArray();
        $this->queryParams = $queryParams ?? new ThreadSafeArray();
        $this->body = $body ?? new ThreadSafeArray();
        $this->params = $params ?? new ThreadSafeArray();
        $this->session = $session ?? new ThreadSafeArray();  // Session speichern
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getHeaders(): ThreadSafeArray {
        return $this->headers;
    }

    public function getQueryParams(): ThreadSafeArray {
        return $this->queryParams;
    }

    public function getBody(): ThreadSafeArray {
        return $this->body;
    }

    public function parameter(string $key): ?string {
        return $this->params[$key] ?? null;
    }

    public function getSession(): ThreadSafeArray {
        return $this->session;  // Rückgabe der Session-Daten
    }
}
