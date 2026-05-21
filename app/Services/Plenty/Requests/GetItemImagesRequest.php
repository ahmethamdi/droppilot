<?php

namespace App\Services\Plenty\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetItemImagesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected int $itemId) {}

    public function resolveEndpoint(): string
    {
        return "/rest/items/{$this->itemId}/images";
    }
}
