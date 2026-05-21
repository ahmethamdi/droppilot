<?php

namespace App\Services\Plenty\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetContactsRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  int|null  $classId  Plenty contact class ID filter (single).
     *                             Multiple class'lar için endpoint'i ayrı çağırın.
     */
    public function __construct(
        protected int $page = 1,
        protected int $itemsPerPage = 50,
        protected ?int $classId = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/rest/accounts/contacts';
    }

    protected function defaultQuery(): array
    {
        $q = [
            'page' => $this->page,
            'itemsPerPage' => $this->itemsPerPage,
            'with' => 'accounts',
        ];

        if ($this->classId !== null) {
            $q['classId'] = $this->classId;
        }

        return $q;
    }
}
