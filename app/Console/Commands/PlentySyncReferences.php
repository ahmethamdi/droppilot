<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Plenty\PlentyClient;
use Illuminate\Console\Command;

class PlentySyncReferences extends Command
{
    protected $signature = 'plenty:sync-references {supplier? : Supplier ID (default: ilk aktif supplier)}';

    protected $description = 'Plenty\'den referrers, warehouses, order statuses ve sales prices çekip supplier_references tablosuna yazar.';

    public function handle(): int
    {
        $supplierId = $this->argument('supplier');

        $supplier = $supplierId
            ? Supplier::find($supplierId)
            : Supplier::where('status', 'active')->orderBy('id')->first();

        if (! $supplier) {
            $this->error('Aktif supplier bulunamadı.');

            return self::FAILURE;
        }

        $this->info("Supplier: #{$supplier->id} {$supplier->name}");
        $this->info('Plenty referansları senkronize ediliyor...');

        try {
            $counts = (new PlentyClient($supplier))->syncReferences();
        } catch (\Throwable $e) {
            $this->error('Senkron başarısız: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Kind', 'Adet'],
            collect($counts)->map(fn ($v, $k) => [$k, $v])->values()->all(),
        );

        $this->info('Tamam.');

        return self::SUCCESS;
    }
}
