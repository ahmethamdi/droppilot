<?php

namespace App\Services\Plenty\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetSalesPricesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected int $page = 1, protected int $itemsPerPage = 100) {}

    public function resolveEndpoint(): string
    {
        return '/rest/items/sales_prices';
    }

    protected function defaultQuery(): array
    {
        return ['page' => $this->page, 'itemsPerPage' => $this->itemsPerPage];
    }
}
