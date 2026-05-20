<?php

namespace App\Services\Shopify;

use App\Models\ShopifyStore;
use App\Services\Shopify\Requests\GetCustomersRequest;
use App\Services\Shopify\Requests\GetOrdersRequest;
use App\Services\Shopify\Requests\GetShopRequest;
use Osiset\ShopifyApp\Services\OfflineAccessTokenRefresher;

class ShopifyClient
{
    public function __construct(protected ShopifyStore $store) {}

    public function connector(): ShopifyConnector
    {
        $this->ensureFreshToken();

        return new ShopifyConnector($this->store);
    }

    /**
     * Süresi dolmuş offline token'ı paketin refresh servisi ile yeniler.
     * Crypt::decryptString refresh token'ı çözer, Shopify'a refresh_token
     * grant'ı POST eder, yeni access token + yeni refresh token alıp DB'ye yazar.
     */
    protected function ensureFreshToken(): void
    {
        try {
            app(OfflineAccessTokenRefresher::class)->refreshIfNeeded($this->store);
            $this->store->refresh();
        } catch (\Throwable $e) {
            // Refresh başarısızsa eski token'ı kullanmaya devam et — Shopify
            // 401 dönerse zaten üst katmanda anlaşılır.
            \Illuminate\Support\Facades\Log::warning('Shopify token refresh skipped', [
                'shop' => $this->store->name,
                'reason' => $e->getMessage(),
            ]);
        }
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
