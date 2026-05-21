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

    protected static ?string $label = 'Ürün';

    protected static ?string $pluralLabel = 'Ürünler';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Plenty Kaynak')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('plenty_item_id')
                        ->label('Plenty Item ID')
                        ->disabled(),
                    Forms\Components\TextInput::make('main_variation_id')
                        ->label('Ana Variation ID')
                        ->disabled(),
                    Forms\Components\Select::make('supplier_id')
                        ->relationship('supplier', 'name')
                        ->disabled(),
                ]),
            Forms\Components\Section::make('İçerik')
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Ad')->disabled(),
                    Forms\Components\TextInput::make('name2')->label('Alt Ad')->disabled(),
                    Forms\Components\Textarea::make('short_description')->label('Kısa Açıklama')->rows(3)->disabled(),
                    Forms\Components\Textarea::make('description')->label('Açıklama')->rows(8)->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('mainVariationImage')
                    ->label('Görsel')
                    ->state(fn (Product $record) => $record->variations()->whereNotNull('image_url')->first()?->image_url)
                    ->square()
                    ->size(48),
                Tables\Columns\TextColumn::make('plenty_item_id')
                    ->label('Plenty ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Ürün Adı')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('manufacturer_name')
                    ->label('Üretici')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pushedTo_count')
                    ->label('Aktarıldı')
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
                    ->label('Fiyat')
                    ->state(fn (Product $record) => $record->variations()->first()?->retail_price)
                    ->money('EUR')
                    ->placeholder('—')
                    ->weight('semibold')
                    ->color('success'),
                Tables\Columns\TextColumn::make('mainVariationStock')
                    ->label('Stok')
                    ->state(fn (Product $record) => $record->variations()->first()?->stock_net)
                    ->numeric()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Tedarikçi')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('synced_at')
                    ->label('Senkron')
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Tedarikçi')
                    ->relationship('supplier', 'name'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('sync_from_plenty')
                    ->label('Plenty\'den Katalogu Senkronize Et')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('supplier_id')
                            ->label('Tedarikçi')
                            ->options(fn () => Supplier::where('status', 'active')->pluck('name', 'id'))
                            ->required(),
                        Forms\Components\TextInput::make('max_items')
                            ->label('Taranacak ürün sayısı (üst sınır)')
                            ->numeric()
                            ->default(2000)
                            ->helperText('Plenty\'de ~9500 ürün taranır; sadece "Paket" üreticili olanlar DB\'ye yazılır.'),
                    ])
                    ->action(function (array $data) {
                        $supplier = Supplier::find($data['supplier_id']);
                        if (! $supplier) {
                            Notification::make()->title('Tedarikçi bulunamadı')->danger()->send();

                            return;
                        }
                        try {
                            $result = (new PlentyClient($supplier))->syncProducts(
                                (int) $data['max_items'],
                                true, // sadece paket variation'lar çekilir
                            );
                            Notification::make()
                                ->title('Senkron tamam')
                                ->body(\sprintf(
                                    'İşlenen: %d  •  Yeni: %d  •  Güncellenen: %d  •  Variation: %d',
                                    $result['processed'],
                                    $result['created'],
                                    $result['updated'],
                                    $result['variations_synced'] ?? 0,
                                ))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Senkron hata verdi')
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
                        ->label('Variationları Çek')
                        ->icon('heroicon-o-cube-transparent')
                        ->color('info')
                        ->action(function (Product $record) {
                            try {
                                $n = (new PlentyClient($record->supplier))->syncItemVariations($record);
                                Notification::make()
                                    ->title('Variations alındı')
                                    ->body("{$n} variation senkronize edildi")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Variation senkronu başarısız')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\ViewAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('push_to_shopify')
                        ->label('Seçilenleri Shopify\'a Aktar')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Shopify\'a Aktar')
                        ->modalDescription('Seçili ürünleri seçilen mağazaya gönder. Aynı SKU zaten varsa Shopify hata verebilir — atlanır.')
                        ->form([
                            Forms\Components\Select::make('shopify_store_id')
                                ->label('Hedef Shopify Mağazası')
                                ->options(fn () => ShopifyStore::query()
                                    ->whereNotNull('password')
                                    ->get()
                                    ->mapWithKeys(fn ($s) => [$s->id => $s->name.($s->tenant ? " — {$s->tenant->name}" : '')])
                                    ->all())
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $store = ShopifyStore::find($data['shopify_store_id']);
                            if (! $store) {
                                Notification::make()->title('Mağaza bulunamadı')->danger()->send();

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

                            $body = "Başarılı: {$success}  •  Atlandı: {$skipped}  •  Hatalı: {$failed}";
                            if (! empty($errors)) {
                                $body .= "\n\n".implode("\n", array_slice($errors, 0, 3));
                            }

                            Notification::make()
                                ->title($failed === 0 ? 'Shopify\'a aktarım tamam' : 'Aktarım kısmi başarılı')
                                ->body($body)
                                ->color($failed === 0 ? 'success' : 'warning')
                                ->persistent()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Henüz katalog senkronize edilmedi')
            ->emptyStateDescription('Yukarıdaki "Plenty\'den Katalogu Senkronize Et" butonuna bas.')
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
