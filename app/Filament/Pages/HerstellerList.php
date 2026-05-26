<?php

namespace App\Filament\Pages;

use App\Models\ManufacturerShopPermission;
use App\Models\Product;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class HerstellerList extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $navigationLabel = 'Hersteller';

    protected static ?string $title = 'Hersteller';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'hersteller';

    protected static string $view = 'filament.pages.hersteller-list';

    public string $search = '';

    /**
     * @return array<int, array{
     *   manufacturer_id:int, manufacturer_name:string, supplier_id:int,
     *   supplier_name:?string, product_count:int, permission_count:int
     * }>
     */
    public function getRowsProperty(): array
    {
        $rows = DB::table('products')
            ->select(
                'products.manufacturer_id',
                'products.manufacturer_name',
                'products.supplier_id',
                DB::raw('COUNT(*) as product_count'),
            )
            ->whereNotNull('products.manufacturer_id')
            ->where('products.is_package', true)
            ->groupBy('products.manufacturer_id', 'products.manufacturer_name', 'products.supplier_id')
            ->orderBy('products.manufacturer_name')
            ->get();

        $needle = mb_strtolower(trim($this->search));

        $permissionCounts = ManufacturerShopPermission::query()
            ->select('manufacturer_id', 'supplier_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('manufacturer_id', 'supplier_id')
            ->get()
            ->mapWithKeys(fn ($p) => ["{$p->supplier_id}:{$p->manufacturer_id}" => (int) $p->cnt])
            ->all();

        $supplierNames = \App\Models\Supplier::pluck('name', 'id')->all();

        $result = [];
        foreach ($rows as $row) {
            $name = $row->manufacturer_name ?: "Hersteller #{$row->manufacturer_id}";
            if ($needle !== '' && ! str_contains(mb_strtolower($name), $needle)) {
                continue;
            }
            $key = "{$row->supplier_id}:{$row->manufacturer_id}";
            $result[] = [
                'manufacturer_id' => (int) $row->manufacturer_id,
                'manufacturer_name' => $name,
                'supplier_id' => (int) $row->supplier_id,
                'supplier_name' => $supplierNames[$row->supplier_id] ?? null,
                'product_count' => (int) $row->product_count,
                'permission_count' => $permissionCounts[$key] ?? 0,
            ];
        }

        return $result;
    }
}
