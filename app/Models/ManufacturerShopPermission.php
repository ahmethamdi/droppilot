<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturerShopPermission extends Model
{
    protected $fillable = [
        'supplier_id',
        'manufacturer_id',
        'manufacturer_name',
        'shopify_store_id',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function shopifyStore(): BelongsTo
    {
        return $this->belongsTo(ShopifyStore::class);
    }
}
