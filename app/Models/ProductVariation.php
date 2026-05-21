<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariation extends Model
{
    protected $fillable = [
        'product_id',
        'plenty_variation_id',
        'sku',
        'model',
        'name',
        'is_main',
        'is_active',
        'retail_price',
        'retail_price_source_id',
        'currency',
        'stock_net',
        'weight_g',
        'width_mm',
        'length_mm',
        'height_mm',
        'image_url',
        'payload',
        'synced_at',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_active' => 'boolean',
        'retail_price' => 'decimal:2',
        'stock_net' => 'decimal:2',
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
