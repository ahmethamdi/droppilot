<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'kind',
        'plenty_base_url',
        'plenty_login_user',
        'plenty_login_password',
        'default_warehouse_id',
        'default_referrer_id',
        'default_order_status_id',
        'default_sales_price_id',
        'default_plenty_id',
        'config',
        'status',
        'owner_user_id',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    protected $hidden = [
        'plenty_login_user',
        'plenty_login_password',
    ];

    protected function plentyLoginUser(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    protected function plentyLoginPassword(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_supplier')
            ->using(TenantSupplier::class)
            ->withPivot([
                'plenty_contact_id',
                'default_billing_address_id',
                'markup_pct',
                'status',
                'meta',
            ])
            ->withTimestamps();
    }

    public function references(): HasMany
    {
        return $this->hasMany(SupplierReference::class);
    }

    public function referencesOfKind(string $kind): HasMany
    {
        return $this->references()->where('kind', $kind);
    }
}
