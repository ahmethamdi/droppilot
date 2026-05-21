<?php

namespace App\Services\Shopify;

use App\Models\Product;
use App\Models\ShopifyPushedProduct;
use App\Models\ShopifyStore;
use App\Services\Plenty\PlentyClient;
use App\Services\Shopify\Requests\CreateProductRequest;

/**
 * Bir Plenty ürününü (Product) bir Shopify mağazasına (ShopifyStore) push eder.
 *
 * Akış:
 * 1. ShopifyPushedProduct kayıt var mı kontrol et (zaten gönderilmiş mi)
 * 2. Yoksa Plenty variation+image lazy çek (yoksa)
 * 3. Shopify productCreate REST çağrısı
 * 4. Dönen shopify_product_id'yi DB'ye yaz
 * 5. ShopifyPushedProduct state = success / failed
 */
class PushProductToShopify
{
    public function __invoke(Product $product, ShopifyStore $store): ShopifyPushedProduct
    {
        $pushed = ShopifyPushedProduct::firstOrNew([
            'product_id' => $product->id,
            'shopify_store_id' => $store->id,
        ]);

        // Zaten başarıyla gönderilmiş ise skip
        if ($pushed->exists && $pushed->state === 'success' && $pushed->shopify_product_id) {
            $pushed->state = 'skipped';

            return $pushed;
        }

        $pushed->attempts = ($pushed->attempts ?? 0) + 1;

        try {
            // 1) Variations cache yok ise Plenty'den çek
            $variation = $product->variations()->first();
            if (! $variation) {
                (new PlentyClient($product->supplier))->syncItemVariations($product);
                $product->refresh();
                $variation = $product->variations()->first();
            }

            if (! $variation) {
                throw new \RuntimeException("Bu üründe Plenty variation bulunamadı (item #{$product->plenty_item_id}).");
            }

            // 2) Shopify product payload
            $payload = [
                'title' => $product->name ?: ($variation->name ?? "Plenty #{$product->plenty_item_id}"),
                'body_html' => $product->description ?? '',
                'vendor' => $product->manufacturer_name ?? '',
                'product_type' => 'Dropshipping',
                'status' => 'active',
                'sku' => $variation->sku,
                'price' => $variation->retail_price,
                'image_url' => $variation->image_url,
                'weight_g' => $variation->weight_g,
            ];

            // 3) Shopify'a gönder
            $client = new ShopifyClient($store);
            $response = $client->connector()->send(new CreateProductRequest($payload));
            $response->throw();

            $shopifyProduct = $response->json('product');
            if (! $shopifyProduct || empty($shopifyProduct['id'])) {
                throw new \RuntimeException('Shopify product oluşturuldu ama ID dönmedi.');
            }

            $pushed->fill([
                'shopify_product_id' => $shopifyProduct['id'],
                'sku' => $variation->sku,
                'state' => 'success',
                'error' => null,
                'pushed_at' => now(),
                'shopify_payload' => $shopifyProduct,
            ])->save();

            return $pushed;
        } catch (\Throwable $e) {
            $pushed->fill([
                'state' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 500),
            ])->save();

            throw $e;
        }
    }
}
