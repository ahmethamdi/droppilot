<?php

namespace App\Services\Plenty\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetReferrersRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/rest/orders/referrers';
    }
}
