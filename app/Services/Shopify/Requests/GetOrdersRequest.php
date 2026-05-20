<?php

namespace App\Services\Shopify\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetOrdersRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  string  $status  'any' | 'open' | 'closed' | 'cancelled'
     */
    public function __construct(
        protected int $limit = 50,
        protected string $status = 'any',
    ) {}

    public function resolveEndpoint(): string
    {
        return '/orders.json';
    }

    protected function defaultQuery(): array
    {
        return [
            'status' => $this->status,
            'limit' => $this->limit,
            'fields' => 'id,name,email,financial_status,fulfillment_status,total_price,currency,line_items,shipping_address,customer,created_at,updated_at',
        ];
    }
}
