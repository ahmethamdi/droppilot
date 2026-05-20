<?php

namespace App\Filament\Resources\ShopifyStoreResource\Pages;

use App\Filament\Resources\ShopifyStoreResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopifyStore extends EditRecord
{
    protected static string $resource = ShopifyStoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
