<?php

namespace App\Services\Plenty\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetManufacturersRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected int $page = 1, protected int $itemsPerPage = 250) {}

    public function resolveEndpoint(): string
    {
        return '/rest/items/manufacturers';
    }

    protected function defaultQuery(): array
    {
        return ['page' => $this->page, 'itemsPerPage' => $this->itemsPerPage];
    }
}
