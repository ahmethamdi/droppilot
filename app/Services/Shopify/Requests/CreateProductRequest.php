<?php

namespace App\Services\Shopify\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Shopify REST: POST /admin/api/{version}/products.json
 *
 * Tek bir ürün yaratır, variations + image dahil. SKU = Plenty variation number.
 * Default published_status = 'active' (mağaza vitrininde yayında).
 */
class CreateProductRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  array  $product  Shopify product payload (title, body_html, vendor, sku, price, image_url, etc.)
     */
    public function __construct(protected array $product) {}

    public function resolveEndpoint(): string
    {
        return '/products.json';
    }

    protected function defaultBody(): array
    {
        $p = $this->product;

        $variant = [
            'sku' => $p['sku'] ?? null,
            'price' => isset($p['price']) ? number_format((float) $p['price'], 2, '.', '') : null,
            'inventory_management' => null, // bayi kendi yönetir
            'requires_shipping' => true,
            'taxable' => true,
        ];

        if (! empty($p['weight_g'])) {
            $variant['weight'] = (float) $p['weight_g'];
            $variant['weight_unit'] = 'g';
        }

        $payload = [
            'title' => $p['title'] ?? 'Untitled',
            'body_html' => $p['body_html'] ?? '',
            'vendor' => $p['vendor'] ?? '',
            'product_type' => $p['product_type'] ?? '',
            'status' => $p['status'] ?? 'active',
            'variants' => [array_filter($variant, fn ($v) => $v !== null)],
        ];

        if (! empty($p['image_url'])) {
            $payload['images'] = [['src' => $p['image_url']]];
        }

        return ['product' => $payload];
    }
}
