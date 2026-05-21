<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlentyOrder extends Model
{
    protected $fillable = [
        'shopify_store_id',
        'supplier_id',
        'shopify_order_id',
        'shopify_order_name',
        'plenty_contact_id',
        'plenty_address_id',
        'plenty_order_id',
        'total',
        'currency',
        'items_count',
        'skipped_count',
        'state',
        'error',
        'attempts',
        'pushed_at',
        'payload',
        'response',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'pushed_at' => 'datetime',
        'payload' => 'array',
        'response' => 'array',
    ];

    public function shopifyStore(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
