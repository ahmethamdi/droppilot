<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\ShopifyStore;
use App\Models\Supplier;
use App\Services\Plenty\PlentyClient;
use App\Services\Shopify\PushProductToShopify;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 5;

    protected static ?string $label = 'Artikel';

    protected static ?string $pluralLabel = 'Artikel';

    protected static ?string $navigationLabel = 'Artikel';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Plenty-Quelle')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('plenty_item_id')
                        ->label('Plenty Artikel-ID')
                        ->disabled(),
                    Forms\Components\TextInput::make('main_variation_id')
                        ->label('Hauptvariations-ID')
                        ->disabled(),
                    Forms\Components\Select::make('supplier_id')
                        ->label('Lieferant')
                        ->relationship('supplier', 'name')
                        ->disabled(),
                ]),
            Forms\Components\Section::make('Inhalt')
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Name')->disabled(),
                    Forms\Components\TextInput::make('name2')->label('Untertitel')->disabled(),
                    Forms\Components\Textarea::make('short_description')->label('Kurzbeschreibung')->rows(3)->disabled(),
                    Forms\Components\Textarea::make('description')->label('Beschreibung')->rows(8)->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('mainVariationImage')
                    ->label('Bild')
                    ->state(fn (Product $record) => $record->variations()->whereNotNull('image_url')->first()?->image_url)
                    ->square()
                    ->size(48),
                Tables\Columns\TextColumn::make('plenty_item_id')
                    ->label('Plenty-ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Artikelname')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('manufacturer_name')
                    ->label('Hersteller')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pushedTo_count')
                    ->label('Übertragen')
                    ->counts('pushedTo')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('mainVariationSku')
                    ->label('SKU')
                    ->state(fn (Product $record) => $record->variations()->first()?->sku)
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('mainVariationPrice')
                    ->label('Preis')
                    ->state(fn (Product $record) => $record->variations()->first()?->retail_price)
                    ->money('EUR')
                    ->placeholder('—')
                    ->weight('semibold')
                    ->color('success'),
                Tables\Columns\TextColumn::make('mainVariationStock')
                    ->label('Bestand')
                    ->state(fn (Product $record) => $record->variations()->first()?->stock_net)
                    ->numeric()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Lieferant')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('synced_at')
                    ->label('Synchronisiert')
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Lieferant')
                    ->relationship('supplier', 'name'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('sync_from_plenty')
                    ->label('Katalog aus Plenty synchronisieren')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('supplier_id')
                            ->label('Lieferant')
                            ->options(fn () => Supplier::where('status', 'active')->pluck('name', 'id'))
                            ->required(),
                        Forms\Components\TextInput::make('max_items')
                            ->label('Max. zu prüfende Artikelanzahl')
                            ->numeric()
                            ->default(2000)
                            ->helperText('Plenty enthält ca. 9.500 Artikel; nur Artikel mit „Paket"-Hersteller werden in die DB übernommen.'),
                    ])
                    ->action(function (array $data) {
                        $supplier = Supplier::find($data['supplier_id']);
                        if (! $supplier) {
                            Notification::make()->title('Lieferant nicht gefunden')->danger()->send();

                            return;
                        }
                        try {
                            $result = (new PlentyClient($supplier))->syncProducts(
                                (int) $data['max_items'],
                                true,
                            );
                            Notification::make()
                                ->title('Synchronisation abgeschlossen')
                                ->body(\sprintf(
                                    'Verarbeitet: %d  •  Neu: %d  •  Aktualisiert: %d  •  Varianten: %d',
                                    $result['processed'],
                                    $result['created'],
                                    $result['updated'],
                                    $result['variations_synced'] ?? 0,
                                ))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Synchronisation fehlgeschlagen')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('sync_variations')
                        ->label('Varianten laden')
                        ->icon('heroicon-o-cube-transparent')
                        ->color('info')
                        ->action(function (Product $record) {
                            try {
                                $n = (new PlentyClient($record->supplier))->syncItemVariations($record);
                                Notification::make()
                                    ->title('Varianten geladen')
                                    ->body("{$n} Varianten synchronisiert")
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

                    Tables\Actions\ViewAction::make()->label('Anzeigen'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('push_to_shopify')
                    ->label('In Shopify-Shop übertragen')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Artikel in Shopify-Shop übertragen')
                    ->modalDescription(fn ($livewire) => 'Die ausgewählten '.count($livewire->getSelectedTableRecords()).' Artikel werden in den unten gewählten Shopify-Shop übertragen. Bereits dort vorhandene Artikel werden übersprungen.')
                    ->modalSubmitActionLabel('Übertragen')
                    ->modalIcon('heroicon-o-shopping-bag')
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\Select::make('shopify_store_id')
                            ->label('Ziel-Shopify-Shop')
                            ->placeholder('Shop auswählen ...')
                            ->options(fn () => ShopifyStore::query()
                                ->whereNotNull('password')
                                ->with('tenant')
                                ->get()
                                ->mapWithKeys(fn ($s) => [
                                    $s->id => $s->tenant
                                        ? "{$s->tenant->name}  —  {$s->name}"
                                        : $s->name,
                                ])
                                ->all())
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->helperText('Es werden alle mit DropPilot verbundenen Shopify-Shops aufgelistet. Jeder Shop gehört einem Händler (Plenty-B2B-Kunden).'),
                    ])
                        ->action(function (Collection $records, array $data) {
                            $store = ShopifyStore::find($data['shopify_store_id']);
                            if (! $store) {
                                Notification::make()->title('Shop nicht gefunden')->danger()->send();

                                return;
                            }

                            $pusher = new PushProductToShopify;
                            $success = 0;
                            $failed = 0;
                            $skipped = 0;
                            $errors = [];

                            foreach ($records as $product) {
                                /** @var Product $product */
                                try {
                                    $result = $pusher($product, $store);
                                    if ($result->state === 'success') {
                                        $success++;
                                    } elseif ($result->state === 'skipped') {
                                        $skipped++;
                                    } else {
                                        $failed++;
                                    }
                                } catch (\Throwable $e) {
                                    $failed++;
                                    $errors[] = "{$product->name}: ".mb_substr($e->getMessage(), 0, 100);
                                }
                            }

                            $body = "Erfolgreich: {$success}  •  Übersprungen: {$skipped}  •  Fehlgeschlagen: {$failed}";
                            if (! empty($errors)) {
                                $body .= "\n\n".implode("\n", array_slice($errors, 0, 3));
                            }

                            Notification::make()
                                ->title($failed === 0 ? 'Übertragung an Shopify abgeschlossen' : 'Übertragung teilweise erfolgreich')
                                ->body($body)
                                ->color($failed === 0 ? 'success' : 'warning')
                                ->persistent()
                                ->send();
                        }),
            ])
            ->emptyStateHeading('Noch kein Katalog synchronisiert')
            ->emptyStateDescription('Oben auf „Katalog aus Plenty synchronisieren" klicken.')
            ->emptyStateIcon('heroicon-o-cube')
            ->modifyQueryUsing(fn ($query) => $query->where('is_package', true)->orderBy('plenty_item_id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'view' => Pages\ViewProduct::route('/{record}'),
        ];
    }
}
