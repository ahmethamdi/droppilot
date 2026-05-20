<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkuLookup extends Model
{
    protected $fillable = [
        'supplier_id',
        'sku',
        'plenty_variation_id',
        'plenty_item_id',
        'name',
        'supplier_price',
        'supplier_price_source_id',
        'stock_net',
        'is_active',
        'found',
        'payload',
        'synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'found' => 'boolean',
        'supplier_price' => 'decimal:2',
        'stock_net' => 'decimal:2',
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
