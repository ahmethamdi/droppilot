<?php

namespace App\Filament\Resources\ShopifyStoreResource\Pages;

use App\Filament\Resources\ShopifyStoreResource;
use App\Models\ShopifyStore;
use App\Services\Plenty\PushOrderToPlenty;
use App\Services\Shopify\ShopifyClient;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListShopifyStores extends ListRecords
{
    protected static string $resource = ShopifyStoreResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Wird vom „An Plenty senden"-Button im Modal ausgelöst.
     * Lädt die Bestellung live aus Shopify und übergibt sie an PushOrderToPlenty.
     */
    public function pushShopifyOrderToPlenty(int $storeId, int $shopifyOrderId): void
    {
        $store = ShopifyStore::find($storeId);
        if (! $store) {
            Notification::make()->title('Shop nicht gefunden')->danger()->send();

            return;
        }

        try {
            $order = (new ShopifyClient($store))->getOrder($shopifyOrderId);
            if (empty($order)) {
                throw new \RuntimeException("Shopify-Bestellung {$shopifyOrderId} nicht gefunden.");
            }

            $record = app(PushOrderToPlenty::class)($store, $order);

            Notification::make()
                ->title("An Plenty gesendet: {$record->shopify_order_name}")
                ->body("Plenty-Auftrag #{$record->plenty_order_id} angelegt ({$record->items_count} Positionen, {$record->skipped_count} übersprungen).")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Übertragung an Plenty fehlgeschlagen')
                ->body(mb_substr($e->getMessage(), 0, 500))
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
