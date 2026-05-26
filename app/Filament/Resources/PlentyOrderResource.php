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

    protected static ?string $navigationGroup = 'Betrieb';

    protected static ?int $navigationSort = 10;

    protected static ?string $label = 'Bestellung';

    protected static ?string $pluralLabel = 'Bestellungen';

    protected static ?string $navigationLabel = 'Bestellungen (Plenty)';

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
                    ->label('Shop')
                    ->badge()
                    ->color('gray')
                    ->limit(28)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('plenty_order_id')
                    ->label('Plenty-Auftrag #')
                    ->placeholder('—')
                    ->copyable()
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : null)
                    ->weight('semibold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'success' => '✓ Übertragen',
                        'failed' => '✗ Fehlgeschlagen',
                        'pending' => '⏳ Ausstehend',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Positionen')
                    ->numeric()
                    ->alignCenter()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('skipped_count')
                    ->label('Übersprungen')
                    ->numeric()
                    ->alignCenter()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Betrag')
                    ->money(fn (PlentyOrder $r) => $r->currency ?: 'EUR')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('attempts')
                    ->label('Versuche')
                    ->numeric()
                    ->alignCenter()
                    ->color(fn ($state) => $state > 1 ? 'warning' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('pushed_at')
                    ->label('Übertragen am')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Erster Versuch')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('error')
                    ->label('Fehler')
                    ->limit(60)
                    ->tooltip(fn (PlentyOrder $r) => $r->error)
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('state')
                    ->label('Status')
                    ->options([
                        'success' => '✓ Übertragen',
                        'failed' => '✗ Fehlgeschlagen',
                        'pending' => '⏳ Ausstehend',
                    ]),
                Tables\Filters\SelectFilter::make('shopify_store_id')
                    ->label('Shop')
                    ->relationship('shopifyStore', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('plenty_contact_id')
                    ->label('B2B-Kunde')
                    ->searchable()
                    ->options(function () {
                        return PlentyOrder::query()
                            ->select('plenty_contact_id', 'supplier_id')
                            ->whereNotNull('plenty_contact_id')
                            ->distinct()
                            ->get()
                            ->map(function ($row) {
                                $store = \App\Models\ShopifyStore::where('supplier_id', $row->supplier_id)
                                    ->where('plenty_contact_id', $row->plenty_contact_id)
                                    ->first();
                                $tenantName = $store?->tenant?->name;

                                return [
                                    'id' => $row->plenty_contact_id,
                                    'label' => $tenantName
                                        ? "{$tenantName} (#{$row->plenty_contact_id})"
                                        : "Plenty-Kontakt #{$row->plenty_contact_id}",
                                ];
                            })
                            ->pluck('label', 'id')
                            ->all();
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('retry')
                        ->label('Erneut an Plenty senden')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (PlentyOrder $r) => $r->state !== 'success')
                        ->requiresConfirmation()
                        ->modalDescription('Diese Shopify-Bestellung wird erneut an Plenty gesendet. Es werden die gleiche SKU-Suche und Preisermittlung angewendet.')
                        ->action(function (PlentyOrder $r) {
                            $store = $r->shopifyStore;
                            if (! $store) {
                                Notification::make()->title('Shop nicht gefunden')->danger()->send();
                                return;
                            }
                            try {
                                $order = (new ShopifyClient($store))->getOrder((int) $r->shopify_order_id);
                                $updated = app(PushOrderToPlenty::class)($store, $order);
                                Notification::make()
                                    ->title("Plenty-Auftrag #{$updated->plenty_order_id} angelegt")
                                    ->body("{$updated->items_count} Positionen, {$updated->skipped_count} übersprungen.")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Übertragung an Plenty fehlgeschlagen')
                                    ->body(mb_substr($e->getMessage(), 0, 500))
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('view_payload')
                        ->label('Payload anzeigen')
                        ->icon('heroicon-o-code-bracket')
                        ->color('gray')
                        ->modalHeading(fn (PlentyOrder $r) => "{$r->shopify_order_name} — Plenty-Payload")
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Schließen')
                        ->modalWidth('5xl')
                        ->modalContent(fn (PlentyOrder $r) => view('filament.modals.plenty-order-payload', [
                            'order' => $r,
                        ])),
                ]),
            ])
            ->emptyStateHeading('Noch keine Bestellungen an Plenty übertragen')
            ->emptyStateDescription('In den Shopify-Shops „Bestellungen anzeigen" > „An Plenty senden" verwenden.')
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
