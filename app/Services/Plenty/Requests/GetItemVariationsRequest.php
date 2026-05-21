<?php

namespace App\Services\Plenty\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetItemVariationsRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  list<string>  $with  e.g. ['variationSalesPrices', 'stock', 'images']
     */
    public function __construct(
        protected int $itemId,
        protected array $with = ['variationSalesPrices', 'stock', 'variationImages'],
        protected int $itemsPerPage = 100,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/rest/items/variations';
    }

    protected function defaultQuery(): array
    {
        return [
            'itemId' => $this->itemId,
            'with' => implode(',', $this->with),
            'itemsPerPage' => $this->itemsPerPage,
        ];
    }
}
