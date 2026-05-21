<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyPushedProduct extends Model
{
    protected $fillable = [
        'product_id',
        'shopify_store_id',
        'shopify_product_id',
        'sku',
        'state',
        'error',
        'attempts',
        'pushed_at',
        'shopify_payload',
    ];

    protected $casts = [
        'pushed_at' => 'datetime',
        'shopify_payload' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shopifyStore(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class);
    }
}
