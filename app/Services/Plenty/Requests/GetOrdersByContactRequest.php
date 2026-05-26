<?php

namespace App\Services\Plenty\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /rest/orders?contactId={id}&with[]=addresses
 *
 * Bir Plenty contact'ın receiver olduğu Auftrag'ları çek.
 * with[]=addresses → addressRelations ile birlikte adresleri da getirir
 * (Lieferadresse = typeId 2, alıcı bilgisi).
 */
class GetOrdersByContactRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected int $contactId,
        protected int $page = 1,
        protected int $itemsPerPage = 100,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/rest/orders';
    }

    protected function defaultQuery(): array
    {
        // Plenty wants ?with[]=addresses&with[]=addressRelations — Saloon serializes nested arrays this way.
        return [
            'contactReceiver' => $this->contactId,
            'page' => $this->page,
            'itemsPerPage' => $this->itemsPerPage,
            'with' => ['addresses', 'addressRelations'],
        ];
    }
}
