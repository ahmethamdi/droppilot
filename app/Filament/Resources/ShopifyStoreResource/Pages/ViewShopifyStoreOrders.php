<?php

namespace App\Filament\Resources\ShopifyStoreResource\Pages;

use App\Filament\Resources\ShopifyStoreResource;
use App\Models\ShopifyStore;
use App\Services\Shopify\ShopifyClient;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ViewShopifyStoreOrders extends Page
{
    protected static string $resource = ShopifyStoreResource::class;

    protected static string $view = 'filament.pages.view-shopify-store-orders';

    public ShopifyStore $record;

    public array $shop = [];

    public array $orders = [];

    public array $customers = [];

    public ?string $error = null;

    public string $activeTab = 'orders';

    public function mount(int|string $record): void
    {
        $this->record = ShopifyStore::findOrFail($record);
        $this->loadFromShopify();
    }

    public function loadFromShopify(): void
    {
        try {
            $client = new ShopifyClient($this->record);
            $this->shop = $client->getShop();
            $this->orders = $client->getOrders(50);
            $this->customers = $client->getCustomers(50);
            $this->error = null;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            Notification::make()
                ->title('Shopify\'dan veri alınamadı')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refresh(): void
    {
        $this->loadFromShopify();
        Notification::make()->title('Veriler yenilendi')->success()->send();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getBreadcrumb(): string
    {
        return 'Siparişler & Müşteriler';
    }
}
