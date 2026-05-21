<?php

namespace App\Services\Plenty\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetItemsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected int $page = 1,
        protected int $itemsPerPage = 50,
        protected ?string $updatedAtSince = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/rest/items';
    }

    protected function defaultQuery(): array
    {
        $q = [
            'page' => $this->page,
            'itemsPerPage' => $this->itemsPerPage,
        ];

        if ($this->updatedAtSince !== null) {
            $q['updatedBetween'] = $this->updatedAtSince.','.now()->toIso8601String();
        }

        return $q;
    }
}
