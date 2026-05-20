<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopifyStoreResource\Pages;
use App\Models\ShopifyStore;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\Shopify\ShopifyClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShopifyStoreResource extends Resource
{
    protected static ?string $model = ShopifyStore::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Multi-Tenancy';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 25;

    protected static ?string $label = 'Shopify Mağaza';

    protected static ?string $pluralLabel = 'Shopify Mağazalar';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Mağaza Bilgileri')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Shopify Domain')
                        ->disabled()
                        ->helperText('OAuth ile bağlandığında otomatik dolar.'),

                    Forms\Components\TextInput::make('email')
                        ->label('Mağaza E-postası')
                        ->disabled(),

                    Forms\Components\Select::make('tenant_id')
                        ->label('Bayi (Tenant)')
                        ->relationship('tenant', 'name')
                        ->searchable()
                        ->preload()
                        ->placeholder('Bu mağaza henüz bir bayiye bağlı değil')
                        ->helperText('Bu mağazadan gelen siparişler bu bayinin Plenty hesabına yönlendirilecek.'),

                    Forms\Components\DateTimePicker::make('installed_at')
                        ->label('Yüklenme Zamanı')
                        ->disabled(),
                ]),

            Forms\Components\Section::make('Plenty Eşleştirmesi')
                ->description('Bu mağazadan sipariş geldiğinde hangi tedarikçinin Plenty hesabında hangi müşteriye fatura kesilecek.')
                ->columns(1)
                ->schema([
                    Forms\Components\Placeholder::make('mapping_info')
                        ->label('')
                        ->content(function (?ShopifyStore $record) {
                            if (! $record || ! $record->tenant_id) {
                                return 'Önce bir bayi seçin (yukarıda), sonra bayinin tedarikçilerinden hangisinin kullanılacağı görünür.';
                            }

                            $links = $record->tenant->suppliers()->get();
                            if ($links->isEmpty()) {
                                return 'Bu bayi henüz hiçbir tedarikçi ile bağlı değil. Önce Tenants → düzenle → "Bağlı Tedarikçiler" sekmesinden tedarikçi bağlayın.';
                            }

                            $lines = $links->map(function ($supplier) {
                                $contactId = $supplier->pivot->plenty_contact_id ?? '—';
                                $status = $supplier->pivot->status ?? '—';

                                return "• {$supplier->name} → Plenty contact #{$contactId} ({$status})";
                            })->implode("\n");

                            return $lines;
                        }),
                ])
                ->collapsed(false),

            Forms\Components\Section::make('Teknik Bilgi')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('shopify_offline_access_token_expires_at')
                        ->label('Token Süresi')
                        ->disabled(),
                    Forms\Components\Textarea::make('scopes')
                        ->label('Verilen İzinler')
                        ->disabled()
                        ->rows(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Shopify Domain')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-posta')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Bayi')
                    ->placeholder('— bağlı değil')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('shopify_offline_access_token_expires_at')
                    ->label('Token Süresi')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Yüklendi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Bayiye Göre')
                    ->relationship('tenant', 'name'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('test_connection')
                        ->label('Bağlantıyı Test Et')
                        ->icon('heroicon-o-bolt')
                        ->color('warning')
                        ->action(function (ShopifyStore $record) {
                            $result = (new ShopifyClient($record))->testConnection();

                            $notification = Notification::make()
                                ->title($result['ok'] ? 'Shopify bağlantısı başarılı' : 'Shopify bağlantısı başarısız')
                                ->body($result['message']);

                            $result['ok']
                                ? $notification->success()->send()
                                : $notification->danger()->persistent()->send();
                        }),

                    Tables\Actions\Action::make('view_orders')
                        ->label('Siparişleri Görüntüle')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->color('info')
                        ->modalHeading(fn (ShopifyStore $record) => $record->name . ' — Son Siparişler')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Kapat')
                        ->modalWidth('7xl')
                        ->modalContent(function (ShopifyStore $record) {
                            try {
                                $orders = (new \App\Services\Shopify\ShopifyClient($record))->getOrders(50);
                            } catch (\Throwable $e) {
                                $orders = [];
                                $error = $e->getMessage();
                            }

                            return view('filament.modals.shopify-orders', [
                                'orders' => $orders,
                                'error' => $error ?? null,
                            ]);
                        }),

                    Tables\Actions\Action::make('view_customers')
                        ->label('Müşterileri Görüntüle')
                        ->icon('heroicon-o-users')
                        ->color('info')
                        ->modalHeading(fn (ShopifyStore $record) => $record->name . ' — Müşteriler')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Kapat')
                        ->modalWidth('7xl')
                        ->modalContent(function (ShopifyStore $record) {
                            try {
                                $customers = (new \App\Services\Shopify\ShopifyClient($record))->getCustomers(50);
                            } catch (\Throwable $e) {
                                $customers = [];
                                $error = $e->getMessage();
                            }

                            return view('filament.modals.shopify-customers', [
                                'customers' => $customers,
                                'error' => $error ?? null,
                            ]);
                        }),

                    Tables\Actions\EditAction::make()->label('Düzenle / Bayi Eşle'),
                ]),
            ])
            ->emptyStateHeading('Henüz bağlı Shopify mağazası yok')
            ->emptyStateDescription('Bir bayi DropPilot\'a Shopify mağazasını bağladığında burada görünür.')
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopifyStores::route('/'),
            'edit' => Pages\EditShopifyStore::route('/{record}/edit'),
        ];
    }
}
