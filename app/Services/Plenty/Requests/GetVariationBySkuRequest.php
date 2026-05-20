<?php

namespace App\Services\Plenty\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetVariationBySkuRequest extends Request
{
    protected Method $method = Method::GET;

    /** @param  list<string>  $with  e.g. ['variationSalesPrices', 'stock'] */
    public function __construct(
        protected string $sku,
        protected array $with = ['variationSalesPrices', 'stock'],
    ) {}

    public function resolveEndpoint(): string
    {
        return '/rest/items/variations';
    }

    protected function defaultQuery(): array
    {
        return [
            'numberExact' => $this->sku,
            'with' => implode(',', $this->with),
            'itemsPerPage' => 1,
        ];
    }
}
