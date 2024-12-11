<?php

namespace LonaDB\Plugin\Webinterface;

class Request {
    private string $method;
    private string $path;
    private array $headers;
    private array $queryParams;
    private array $body;
    private array $params;
    private array $session;  // Session-Daten hinzufügen

    public function __construct(
        string $method,
        string $path,
        array $headers,
        array $queryParams,
        array $body = [],
        array $params = [],
        array $session = []  // Session als Parameter hinzufügen
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->headers = $headers;
        $this->queryParams = $queryParams;
        $this->body = $body;
        $this->params = $params;
        $this->session = $session;  // Session speichern
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getHeaders(): array {
        return $this->headers;
    }

    public function getQueryParams(): array {
        return $this->queryParams;
    }

    public function getBody(): array {
        return $this->body;
    }

    public function parameter(string $key): ?string {
        return $this->params[$key] ?? null;
    }

    public function getSession(): array {
        return $this->session;  // Rückgabe der Session-Daten
    }
}
