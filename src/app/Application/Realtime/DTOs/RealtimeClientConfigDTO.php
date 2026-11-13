<?php

declare(strict_types=1);

namespace App\Application\Realtime\DTOs;

/**
 * What the browser needs to open the WebSocket: the public application key (never the secret),
 * the host and port it connects to, and whether it uses TLS.
 */
final readonly class RealtimeClientConfigDTO
{
    public function __construct(
        public string $key,
        public string $host,
        public int $port,
        public bool $tls,
    ) {}

    /**
     * The WebSocket origin (`wss://host`, with the port only when it is not the scheme's own).
     */
    public function origin(): string
    {
        $default = $this->tls ? 443 : 80;

        return ($this->tls ? 'wss' : 'ws').'://'.$this->host.($this->port === $default ? '' : ':'.$this->port);
    }

    /**
     * @return array{key: string, host: string, port: int, tls: bool, authEndpoint: string}
     */
    public function toArray(string $authEndpoint): array
    {
        return [
            'key' => $this->key,
            'host' => $this->host,
            'port' => $this->port,
            'tls' => $this->tls,
            'authEndpoint' => $authEndpoint,
        ];
    }
}
