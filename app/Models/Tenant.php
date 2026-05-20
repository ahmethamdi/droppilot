<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'plan',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'tenant_supplier')
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
}
