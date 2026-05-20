<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReference extends Model
{
    public const KIND_REFERRER = 'referrer';
    public const KIND_WAREHOUSE = 'warehouse';
    public const KIND_ORDER_STATUS = 'order_status';
    public const KIND_PLENTY_ID = 'plenty_id';
    public const KIND_SALES_PRICE = 'sales_price';

    protected $fillable = [
        'supplier_id',
        'kind',
        'external_id',
        'label',
        'payload',
        'synced_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
