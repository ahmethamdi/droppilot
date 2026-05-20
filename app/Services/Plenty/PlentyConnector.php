<?php

namespace App\Services\Plenty;

use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;

class PlentyConnector extends Connector
{
    use AcceptsJson;

    public function __construct(
        protected string $baseUrl,
        protected ?string $accessToken = null,
    ) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    protected function defaultHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->accessToken) {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        return $headers;
    }

    protected function defaultConfig(): array
    {
        return [
            'timeout' => 30,
            'connect_timeout' => 10,
        ];
    }

    public function withAccessToken(string $token): static
    {
        $this->accessToken = $token;

        return $this;
    }
}
