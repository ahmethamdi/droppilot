<?php

namespace App\Services\Shopify;

use App\Models\ShopifyStore;
use App\Services\Shopify\Requests\GetCustomersRequest;
use App\Services\Shopify\Requests\GetOrdersRequest;
use App\Services\Shopify\Requests\GetShopRequest;

class ShopifyClient
{
    public function __construct(protected ShopifyStore $store) {}

    public function connector(): ShopifyConnector
    {
        return new ShopifyConnector($this->store);
    }

    public function getShop(): array
    {
        $response = $this->connector()->send(new GetShopRequest);
        $response->throw();

        return $response->json('shop') ?? [];
    }

    public function getOrders(int $limit = 50, string $status = 'any'): array
    {
        $response = $this->connector()->send(new GetOrdersRequest($limit, $status));
        $response->throw();

        return $response->json('orders') ?? [];
    }

    public function getCustomers(int $limit = 50): array
    {
        $response = $this->connector()->send(new GetCustomersRequest($limit));
        $response->throw();

        return $response->json('customers') ?? [];
    }

    public function testConnection(): array
    {
        try {
            $shop = $this->getShop();

            return [
                'ok' => true,
                'message' => "✓ {$shop['name']} | {$shop['domain']} | {$shop['country_name']} | currency: {$shop['currency']}",
                'shop' => $shop,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'shop' => null,
            ];
        }
    }
}
