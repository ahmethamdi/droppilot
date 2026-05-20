<?php

namespace App\Services\Plenty\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetContactRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected int $contactId) {}

    public function resolveEndpoint(): string
    {
        return "/rest/accounts/contacts/{$this->contactId}";
    }
}
