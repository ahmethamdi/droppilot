<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Osiset\ShopifyApp\Contracts\ShopModel as IShopModel;
use Osiset\ShopifyApp\Traits\ShopModel;

class ShopifyStore extends Authenticatable implements IShopModel
{
    use Notifiable;
    use ShopModel;
    use SoftDeletes;

    protected $table = 'shopify_stores';

    // Paketin Shop trait'i: `name` = shop domain, `password` = access token.
    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'plenty_contact_id',
        'plenty_sales_price_id',
        'plenty_warehouse_id',
        'plenty_order_status_id',
        'name',
        'email',
        'password',
        'shopify_offline_refresh_token',
        'shopify_offline_access_token_expires_at',
        'shopify_offline_refresh_token_expires_at',
        'shopify_namespace',
        'shopify_grandfathered',
        'shopify_freemium',
        'plan_id',
        'scopes',
        'installed_at',
        'uninstalled_at',
    ];

    protected $hidden = [
        'password',
        'shopify_offline_refresh_token',
    ];

    protected $casts = [
        'shopify_grandfathered' => 'boolean',
        'shopify_freemium' => 'boolean',
        'scopes' => 'array',
        'installed_at' => 'datetime',
        'uninstalled_at' => 'datetime',
        'shopify_offline_access_token_expires_at' => 'datetime',
        'shopify_offline_refresh_token_expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
