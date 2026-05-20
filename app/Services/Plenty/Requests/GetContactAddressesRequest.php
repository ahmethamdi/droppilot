<?php

namespace App\Services\Plenty\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetContactAddressesRequest extends Request
{
    protected Method $method = Method::GET;

    /** @param  int  $typeId  1 = Rechnungsadresse, 2 = Lieferadresse */
    public function __construct(
        protected int $contactId,
        protected ?int $typeId = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/rest/accounts/contacts/{$this->contactId}/addresses";
    }

    protected function defaultQuery(): array
    {
        return $this->typeId ? ['typeId' => $this->typeId] : [];
    }
}
