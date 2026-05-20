<?php

namespace App\Services\Plenty\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class LoginRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected string $username,
        protected string $password,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/rest/login';
    }

    protected function defaultBody(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
        ];
    }
}
