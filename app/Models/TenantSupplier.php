<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantSupplier extends Pivot
{
    protected $table = 'tenant_supplier';

    public $incrementing = true;

    protected $casts = [
        'meta' => 'array',
        'markup_pct' => 'decimal:2',
    ];
}
