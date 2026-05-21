<?php

namespace App\Services\Shopify\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /admin/api/{version}/orders/{id}.json
 *
 * Tek bir siparişi line_items + shipping_address ile getirir.
 */
class GetOrderRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected int $orderId) {}

    public function resolveEndpoint(): string
    {
        return "/orders/{$this->orderId}.json";
    }

    protected function defaultQuery(): array
    {
        return [
            'fields' => 'id,name,email,financial_status,fulfillment_status,total_price,currency,line_items,shipping_address,customer,created_at,updated_at',
        ];
    }
}
