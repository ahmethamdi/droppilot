<?php

namespace App\Services\Shopify\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCustomersRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected int $limit = 50) {}

    public function resolveEndpoint(): string
    {
        return '/customers.json';
    }

    protected function defaultQuery(): array
    {
        return [
            'limit' => $this->limit,
            'fields' => 'id,first_name,last_name,email,phone,tax_exempt,verified_email,orders_count,total_spent,currency,tags,default_address,created_at,updated_at',
        ];
    }
}
