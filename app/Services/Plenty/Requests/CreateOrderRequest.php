<?php

namespace App\Services\Plenty\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /rest/orders
 *
 * Plenty'de Auftrag (sipariş/fatura) yaratır. Payload yapısı:
 *   typeId: 1 (sales order)
 *   statusId: numerik (örn 7.00)
 *   plentyId: Mandant ID
 *   referrerId: kaynak ID
 *   relations: contact bind
 *   addressRelations: typeId=1 Rechnung, typeId=2 Lieferung
 *   orderItems: each {typeId:1, itemVariationId, quantity, orderItemName, amounts}
 */
class CreateOrderRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(protected array $order) {}

    public function resolveEndpoint(): string
    {
        return '/rest/orders';
    }

    protected function defaultBody(): array
    {
        return $this->order;
    }
}
