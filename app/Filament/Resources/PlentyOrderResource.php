<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlentyOrderResource\Pages;
use App\Models\PlentyOrder;
use App\Models\ShopifyStore;
use App\Services\Plenty\PushOrderToPlenty;
use App\Services\Shopify\ShopifyClient;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlentyOrderResource extends Resource
{
    protected static ?string $model = PlentyOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 10;

    protected static ?string $label = 'Sipariş';

    protected static ?string $pluralLabel = 'Siparişler';

    protected static ?string $navigationLabel = 'Siparişler (Plenty)';

    public static function getNavigationBadge(): ?string
    {
        $failed = static::getModel()::where('state', 'failed')->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shopify_order_name')
                    ->label('Shopify')
                    ->searchable(['shopify_order_name'])
                    ->sortable()
                    ->copyable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('shopifyStore.name')
                    ->label('Mağaza')
                    ->badge()
                    ->color('gray')
                    ->limit(28)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('plenty_order_id')
                    ->label('Plenty Auftrag #')
                    ->placeholder('—')
                    ->copyable()
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : null)
                    ->weight('semibold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('state')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'success' => '✓ Gönderildi',
                        'failed' => '✗ Başarısız',
                        'pending' => '⏳ Beklemede',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Kalem')
                    ->numeric()
                    ->alignCenter()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('skipped_count')
                    ->label('Atlanan')
                    ->numeric()
                    ->alignCenter()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Tutar')
                    ->money(fn (PlentyOrder $r) => $r->currency ?: 'EUR')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('attempts')
                    ->label('Deneme')
                    ->numeric()
                    ->alignCenter()
                    ->color(fn ($state) => $state > 1 ? 'warning' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('pushed_at')
                    ->label('Gönderim')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('İlk Deneme')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('error')
                    ->label('Hata')
                    ->limit(60)
                    ->tooltip(fn (PlentyOrder $r) => $r->error)
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('state')
                    ->label('Durum')
                    ->options([
                        'success' => '✓ Gönderildi',
                        'failed' => '✗ Başarısız',
                        'pending' => '⏳ Beklemede',
                    ]),
                Tables\Filters\SelectFilter::make('shopify_store_id')
                    ->label('Mağaza')
                    ->relationship('shopifyStore', 'name')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('retry')
                        ->label('Plenty\'ye Tekrar Gönder')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (PlentyOrder $r) => $r->state !== 'success')
                        ->requiresConfirmation()
                        ->modalDescription('Bu Shopify siparişi Plenty\'ye yeniden gönderilecek. Aynı SKU lookup ve fiyatlandırma uygulanır.')
                        ->action(function (PlentyOrder $r) {
                            $store = $r->shopifyStore;
                            if (! $store) {
                                Notification::make()->title('Mağaza bulunamadı')->danger()->send();
                                return;
                            }
                            try {
                                $order = (new ShopifyClient($store))->getOrder((int) $r->shopify_order_id);
                                $updated = app(PushOrderToPlenty::class)($store, $order);
                                Notification::make()
                                    ->title("Plenty Auftrag #{$updated->plenty_order_id} oluşturuldu")
                                    ->body("{$updated->items_count} kalem, {$updated->skipped_count} atlandı.")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Plenty\'ye gönderim başarısız')
                                    ->body(mb_substr($e->getMessage(), 0, 500))
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('view_payload')
                        ->label('Payload\'ı Gör')
                        ->icon('heroicon-o-code-bracket')
                        ->color('gray')
                        ->modalHeading(fn (PlentyOrder $r) => "{$r->shopify_order_name} — Plenty Payload")
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Kapat')
                        ->modalWidth('5xl')
                        ->modalContent(fn (PlentyOrder $r) => view('filament.modals.plenty-order-payload', [
                            'order' => $r,
                        ])),
                ]),
            ])
            ->emptyStateHeading('Henüz Plenty\'ye sipariş gönderilmedi')
            ->emptyStateDescription('Shopify mağazalarında "Siparişleri Görüntüle" > "Plenty\'ye Gönder" butonunu kullan.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlentyOrders::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
