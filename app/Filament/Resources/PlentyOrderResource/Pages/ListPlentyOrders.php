<?php

namespace App\Filament\Resources\PlentyOrderResource\Pages;

use App\Filament\Resources\PlentyOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListPlentyOrders extends ListRecords
{
    protected static string $resource = PlentyOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
