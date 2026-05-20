<?php

namespace App\Services\Plenty\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetWarehousesRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/rest/stockmanagement/warehouses';
    }
}
