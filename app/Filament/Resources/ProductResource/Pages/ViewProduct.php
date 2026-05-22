<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\Plenty\PlentyClient;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_variations')
                ->label('Varianten aus Plenty laden')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function () {
                    try {
                        $n = (new PlentyClient($this->record->supplier))->syncItemVariations($this->record);
                        Notification::make()
                            ->title('Varianten aktualisiert')
                            ->body("{$n} Varianten geladen")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Variantensynchronisation fehlgeschlagen')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
