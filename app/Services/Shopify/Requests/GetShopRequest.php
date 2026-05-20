<?php

namespace App\Services\Shopify\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetShopRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/shop.json';
    }
}
