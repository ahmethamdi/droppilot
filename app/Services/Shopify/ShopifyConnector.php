<?php

namespace App\Services\Shopify;

use App\Models\ShopifyStore;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class ShopifyConnector extends Connector
{
    use AcceptsJson;

    public function __construct(protected ShopifyStore $store) {}

    public function resolveBaseUrl(): string
    {
        $apiVersion = config('shopify-app.api_version', '2026-04');

        return "https://{$this->store->name}/admin/api/{$apiVersion}";
    }

    protected function defaultHeaders(): array
    {
        return [
            'X-Shopify-Access-Token' => $this->store->password,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    protected function defaultConfig(): array
    {
        return [
            'timeout' => 30,
            'connect_timeout' => 10,
        ];
    }
}
