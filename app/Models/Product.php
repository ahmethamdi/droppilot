<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'supplier_id',
        'plenty_item_id',
        'main_variation_id',
        'manufacturer_id',
        'manufacturer_name',
        'is_package',
        'item_type_id',
        'name',
        'name2',
        'short_description',
        'description',
        'meta_description',
        'payload',
        'plenty_updated_at',
        'synced_at',
    ];

    protected $casts = [
        'is_package' => 'boolean',
        'payload' => 'array',
        'plenty_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function pushedTo(): HasMany
    {
        return $this->hasMany(ShopifyPushedProduct::class);
    }

    public function mainVariation(): ?ProductVariation
    {
        return $this->variations()->where('is_main', true)->first()
            ?? $this->variations()->first();
    }
}
