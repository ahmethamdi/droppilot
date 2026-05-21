<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        // CreateAction kaldırıldı — ürünler Plenty senkronundan gelir.
        // Sync butonu ProductResource::table()->headerActions() içinde.
        return [];
    }
}
