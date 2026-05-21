<?php

namespace App\Services\Plenty;

use App\Models\PlentyOrder;
use App\Models\ShopifyStore;
use RuntimeException;

/**
 * Shopify siparişini Plenty'de Auftrag olarak yaratır.
 *
 * Akış:
 *   1. Shopify order ham datasını al (line items, shipping address, customer)
 *   2. ShopifyStore config'inden Plenty parametrelerini oku
 *      (supplier, contact_id, sales_price_id, warehouse_id, status_id)
 *   3. Her Shopify line item'ın SKU'sunu Plenty'de bul → variation_id
 *   4. Shopify shipping_address'i Plenty contact'a Lieferadresse olarak yaz
 *   5. Plenty'ye order payload POST et
 *   6. plenty_orders tablosuna kayıt
 */
class PushOrderToPlenty
{
    /**
     * @param  array  $shopifyOrder  Shopify order ham JSON'u
     */
    public function __invoke(ShopifyStore $store, array $shopifyOrder): PlentyOrder
    {
        $shopifyOrderId = (int) $shopifyOrder['id'];
        $shopifyOrderName = $shopifyOrder['name'] ?? "#{$shopifyOrderId}";

        // Idempotency: aynı sipariş daha önce gönderilmiş mi?
        $record = PlentyOrder::firstOrNew([
            'shopify_store_id' => $store->id,
            'shopify_order_id' => $shopifyOrderId,
        ]);

        if ($record->exists && $record->state === 'success' && $record->plenty_order_id) {
            // Zaten başarıyla gönderilmiş → tekrar gönderme, mevcut kaydı dön
            return $record;
        }

        $record->fill([
            'supplier_id' => $store->supplier_id,
            'shopify_order_name' => $shopifyOrderName,
            'plenty_contact_id' => $store->plenty_contact_id,
            'total' => $shopifyOrder['total_price'] ?? null,
            'currency' => $shopifyOrder['currency'] ?? 'EUR',
            'attempts' => ($record->attempts ?? 0) + 1,
            'state' => 'pending',
        ]);
        $record->save();

        try {
            // 1) Gerekli config'leri doğrula
            if (! $store->supplier_id || ! $store->plenty_contact_id) {
                throw new RuntimeException('Mağaza Plenty müşterisine eşlenmemiş. Edit > Bayi eşleştirmesi yapın.');
            }
            if (! $store->plenty_sales_price_id) {
                throw new RuntimeException('Mağaza için "Satış Fiyatı Tipi" seçilmemiş. Edit > Plenty Sipariş Ayarları.');
            }

            $supplier = $store->supplier;
            $client = new PlentyClient($supplier);

            // 2) Line items SKU lookup
            $orderItems = [];
            $skipped = 0;
            $itemsCount = 0;

            foreach ($shopifyOrder['line_items'] ?? [] as $line) {
                $sku = trim((string) ($line['sku'] ?? ''));
                $qty = (int) ($line['quantity'] ?? 1);

                if ($sku === '') {
                    $skipped++;

                    continue;
                }

                $lookup = $client->lookupSku($sku);
                if (! $lookup['found'] || ! $lookup['variation_id']) {
                    $skipped++;

                    continue;
                }

                // Bu mağazanın sales_price_id'sinden fiyat çek
                $priceForStore = $this->resolveStorePrice($lookup, $store->plenty_sales_price_id, $client, $sku);

                $orderItems[] = [
                    'typeId' => 1, // variation
                    'itemVariationId' => $lookup['variation_id'],
                    'quantity' => $qty,
                    'orderItemName' => $lookup['name'] ?: $sku,
                    'referrerId' => (float) ($supplier->default_referrer_id ?? 1),
                    'amounts' => [[
                        'isSystemCurrency' => true,
                        'currency' => $shopifyOrder['currency'] ?? 'EUR',
                        'exchangeRate' => 1,
                        'priceOriginalGross' => $priceForStore,
                    ]],
                    'properties' => [
                        ['typeId' => 6, 'value' => $sku], // SKU as external reference
                    ],
                ];
                $itemsCount++;
            }

            if (empty($orderItems)) {
                throw new RuntimeException("Sipariş line items'larından hiçbir SKU Plenty'de bulunamadı (atlanan: {$skipped}).");
            }

            // 3) Lieferadresse yarat (Shopify shipping address → Plenty)
            $shippingAddress = $shopifyOrder['shipping_address'] ?? null;
            $addressId = null;
            if ($shippingAddress) {
                $plentyAddress = $this->mapShopifyAddressToPlenty($shippingAddress);
                $addressId = $client->createContactAddress(
                    (int) $store->plenty_contact_id,
                    $plentyAddress + ['typeId' => 2], // typeId=2 → Lieferadresse
                );
                $record->plenty_address_id = $addressId;
            }

            // 4) Order payload
            $addressRelations = [
                ['typeId' => 1, 'addressId' => (int) ($store->plenty_contact_id ? $this->resolveBillingAddressId($client, (int) $store->plenty_contact_id) : 0)],
            ];
            if ($addressId) {
                $addressRelations[] = ['typeId' => 2, 'addressId' => $addressId];
            }

            $payload = [
                'typeId' => 1, // sales order
                'plentyId' => (int) ($supplier->default_plenty_id ?? 0) ?: null,
                'referrerId' => (float) ($supplier->default_referrer_id ?? 1),
                'statusId' => (float) ($store->plenty_order_status_id ?? $supplier->default_order_status_id ?? 5),
                'relations' => [
                    [
                        'referenceType' => 'contact',
                        'referenceId' => (int) $store->plenty_contact_id,
                        'relation' => 'receiver',
                    ],
                ],
                'addressRelations' => array_filter($addressRelations, fn ($r) => ! empty($r['addressId'])),
                'orderItems' => $orderItems,
                'properties' => [
                    [
                        'typeId' => 7, // external order ID
                        'value' => (string) $shopifyOrderId,
                    ],
                ],
            ];

            // null/0 plentyId payload'a girmesin
            if (! $payload['plentyId']) {
                unset($payload['plentyId']);
            }

            $record->payload = $payload;

            // 5) Plenty'ye gönder
            $response = $client->createOrder($payload);

            $plentyOrderId = $response['id'] ?? null;
            if (! $plentyOrderId) {
                throw new RuntimeException('Plenty createOrder beklenen "id" döndürmedi: '.json_encode($response));
            }

            $record->fill([
                'plenty_order_id' => $plentyOrderId,
                'items_count' => $itemsCount,
                'skipped_count' => $skipped,
                'state' => 'success',
                'error' => null,
                'pushed_at' => now(),
                'response' => $response,
            ])->save();

            return $record;
        } catch (\Throwable $e) {
            $record->fill([
                'state' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();
            throw $e;
        }
    }

    /**
     * Lookup'ta dönen variation için, store'un sales_price_id'sinden fiyatı bul.
     * Lookup zaten cache'lenmiş price'a sahip ama o cache supplier->default_sales_price_id baz alır.
     * Bu yüzden payload'dan tekrar parse ediyoruz.
     */
    protected function resolveStorePrice(array $lookup, int $salesPriceId, PlentyClient $client, string $sku): float
    {
        $payload = $lookup['cache']->payload ?? [];

        foreach ($payload['variationSalesPrices'] ?? [] as $vsp) {
            if ((int) ($vsp['salesPriceId'] ?? 0) === $salesPriceId) {
                return (float) $vsp['price'];
            }
        }

        // Fallback: lookup'taki default fiyat (supplier-level)
        if (! empty($lookup['price'])) {
            return (float) $lookup['price'];
        }

        throw new RuntimeException("SKU {$sku} için Plenty'de salesPriceId={$salesPriceId} fiyatı tanımlı değil.");
    }

    /**
     * Contact'ın varsayılan Rechnungsadresse'sini bul.
     * İlk billing address (typeId=1) kullanılır.
     */
    protected function resolveBillingAddressId(PlentyClient $client, int $contactId): int
    {
        $addresses = $client->getContactAddresses($contactId, 1); // typeId=1 = billing
        if (empty($addresses)) {
            // typeId filter çalışmıyorsa tüm adresleri al, ilk billing'i bul
            $all = $client->getContactAddresses($contactId, null);
            foreach ($all as $a) {
                $rel = $a['contactRelations'][0]['typeId'] ?? null;
                if ((int) $rel === 1) {
                    return (int) $a['id'];
                }
            }
            if (! empty($all)) {
                return (int) $all[0]['id'];
            }
            throw new RuntimeException("Plenty contact {$contactId} için billing address bulunamadı.");
        }

        return (int) $addresses[0]['id'];
    }

    /**
     * Shopify shipping_address → Plenty address payload.
     */
    protected function mapShopifyAddressToPlenty(array $ship): array
    {
        return [
            'name1' => trim(($ship['company'] ?? '').' '.($ship['address2'] ?? '')) ?: ($ship['name'] ?? 'Customer'),
            'name2' => $ship['first_name'] ?? '',
            'name3' => $ship['last_name'] ?? '',
            'address1' => $ship['address1'] ?? '',
            'address2' => $ship['address2'] ?? '',
            'postalCode' => $ship['zip'] ?? '',
            'town' => $ship['city'] ?? '',
            'countryId' => $this->countryIdFromIso($ship['country_code'] ?? 'DE'),
            'options' => [
                ['typeId' => 5, 'value' => $ship['phone'] ?? ''],
                ['typeId' => 4, 'value' => $ship['phone'] ?? ''],
            ],
        ];
    }

    /**
     * Yaygın ISO ülke kodu → Plenty country ID.
     * (Plenty'de Almanya=1, Avusturya=2, ABD=21, ...)
     */
    protected function countryIdFromIso(string $iso): int
    {
        $map = [
            'DE' => 1,
            'AT' => 2,
            'CH' => 3,
            'FR' => 10,
            'NL' => 14,
            'BE' => 4,
            'IT' => 13,
            'ES' => 7,
            'GB' => 12,
            'US' => 21,
            'TR' => 18,
        ];

        return $map[strtoupper($iso)] ?? 1; // default DE
    }
}
