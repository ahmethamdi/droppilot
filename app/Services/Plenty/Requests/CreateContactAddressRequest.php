<?php

namespace App\Services\Plenty\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /rest/accounts/contacts/{contactId}/addresses
 *
 * Plenty contact'a yeni bir adres ekler. typeId:
 *   1 = Rechnungsadresse (billing)
 *   2 = Lieferadresse (shipping)
 *   4 = Hesap adresi
 */
class CreateContactAddressRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected int $contactId,
        protected array $address,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/rest/accounts/contacts/{$this->contactId}/addresses";
    }

    protected function defaultBody(): array
    {
        return $this->address;
    }
}
