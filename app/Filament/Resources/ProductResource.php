<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Plenty\PlentyClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                    ->placeholder('—'),
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
                            ->label('Maksimum ürün sayısı')
                            ->numeric()
                            ->default(500)
                            ->helperText('Bütün katalog ~9500 ürün. Test için 100-500 ile başla.'),
                    ])
                    ->action(function (array $data) {
                        $supplier = Supplier::find($data['supplier_id']);
                        if (! $supplier) {
                            Notification::make()->title('Tedarikçi bulunamadı')->danger()->send();

                            return;
                        }
                        try {
                            $result = (new PlentyClient($supplier))->syncProducts((int) $data['max_items']);
                            Notification::make()
                                ->title('Senkron tamam')
                                ->body(\sprintf(
                                    'İşlenen: %d  •  Yeni: %d  •  Güncellenen: %d',
                                    $result['processed'],
                                    $result['created'],
                                    $result['updated'],
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
            ->emptyStateHeading('Henüz katalog senkronize edilmedi')
            ->emptyStateDescription('Yukarıdaki "Plenty\'den Katalogu Senkronize Et" butonuna bas.')
            ->emptyStateIcon('heroicon-o-cube')
            ->defaultSort('plenty_item_id', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'view' => Pages\ViewProduct::route('/{record}'),
        ];
    }
}
