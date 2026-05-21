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
     * Modal'daki "Plenty'ye Gönder" butonundan tetiklenir.
     * Shopify'dan tek siparişi canlı çekip PushOrderToPlenty'ye verir.
     */
    public function pushShopifyOrderToPlenty(int $storeId, int $shopifyOrderId): void
    {
        $store = ShopifyStore::find($storeId);
        if (! $store) {
            Notification::make()->title('Mağaza bulunamadı')->danger()->send();

            return;
        }

        try {
            $order = (new ShopifyClient($store))->getOrder($shopifyOrderId);
            if (empty($order)) {
                throw new \RuntimeException("Shopify {$shopifyOrderId} siparişi bulunamadı.");
            }

            $record = app(PushOrderToPlenty::class)($store, $order);

            Notification::make()
                ->title("Plenty'ye gönderildi: {$record->shopify_order_name}")
                ->body("Plenty Auftrag #{$record->plenty_order_id} oluşturuldu ({$record->items_count} kalem, {$record->skipped_count} atlandı).")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title("Plenty'ye gönderim başarısız")
                ->body(mb_substr($e->getMessage(), 0, 500))
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
