<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use App\Services\Plenty\PlentyClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Multi-Tenancy';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Genel')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Tedarikçi Adı')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('kind')
                        ->options([
                            'plenty' => 'PlentyMarkets',
                        ])
                        ->default('plenty')
                        ->required()
                        ->native(false),

                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => 'Aktif',
                            'pending' => 'Onay bekliyor',
                            'suspended' => 'Askıya alınmış',
                        ])
                        ->default('active')
                        ->required()
                        ->native(false),
                ]),

            Forms\Components\Section::make('PlentyMarkets Bağlantısı')
                ->description('Krediler şifrelenmiş olarak saklanır ve loglanmaz.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('plenty_base_url')
                        ->label('Plenty Base URL')
                        ->placeholder('https://p57085.my.plentysystems.com')
                        ->url()
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('plenty_login_user')
                        ->label('API Kullanıcı Adı')
                        ->required()
                        ->autocomplete('off'),

                    Forms\Components\TextInput::make('plenty_login_password')
                        ->label('API Şifresi')
                        ->password()
                        ->revealable()
                        ->required()
                        ->autocomplete('new-password')
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('Düzenlemede boş bırakılırsa eski şifre korunur.'),
                ]),

            Forms\Components\Section::make('Varsayılan Plenty Referansları')
                ->description('Önce "Plenty Referanslarını Senkronize Et" aksiyonunu çalıştırın, sonra burada seçim yapın.')
                ->columns(2)
                ->hiddenOn('create')
                ->schema([
                    Forms\Components\Select::make('default_referrer_id')
                        ->label('Herkunft / Referrer')
                        ->options(fn (?Supplier $record) => $record
                            ? $record->referencesOfKind(\App\Models\SupplierReference::KIND_REFERRER)
                                ->orderBy('external_id')
                                ->get()
                                ->mapWithKeys(fn ($r) => [$r->external_id => "{$r->external_id} — {$r->label}"])
                                ->all()
                            : [])
                        ->searchable()
                        ->placeholder('Referans verisi yoksa önce senkronize edin')
                        ->helperText('DropPilot siparişleri bu kaynak ID\'si ile işaretler.'),

                    Forms\Components\Select::make('default_order_status_id')
                        ->label('Yeni Sipariş Durumu')
                        ->options(fn (?Supplier $record) => $record
                            ? $record->referencesOfKind(\App\Models\SupplierReference::KIND_ORDER_STATUS)
                                ->orderBy('external_id')
                                ->get()
                                ->mapWithKeys(fn ($r) => [$r->external_id => $r->label])
                                ->all()
                            : [])
                        ->searchable()
                        ->placeholder('Önce senkronize edin'),

                    Forms\Components\Select::make('default_sales_price_id')
                        ->label('Satış Fiyatı Tipi (B2B)')
                        ->options(fn (?Supplier $record) => $record
                            ? $record->referencesOfKind(\App\Models\SupplierReference::KIND_SALES_PRICE)
                                ->orderBy('external_id')
                                ->get()
                                ->mapWithKeys(fn ($r) => [$r->external_id => $r->label])
                                ->all()
                            : [])
                        ->searchable()
                        ->helperText('Sipariş Plenty\'ye düşerken bu fiyat tipinden çekilen tutar Auftrag\'ın priceOriginalGross değeri olur.'),

                    Forms\Components\Select::make('default_warehouse_id')
                        ->label('Varsayılan Depo')
                        ->options(fn (?Supplier $record) => $record
                            ? $record->referencesOfKind(\App\Models\SupplierReference::KIND_WAREHOUSE)
                                ->orderBy('external_id')
                                ->get()
                                ->mapWithKeys(fn ($r) => [$r->external_id => "#{$r->external_id} — {$r->label}"])
                                ->all()
                            : [])
                        ->searchable()
                        ->placeholder('Önce senkronize edin'),

                    Forms\Components\TextInput::make('default_plenty_id')
                        ->label('Mandant (plentyId)')
                        ->numeric()
                        ->helperText('Bu hesapta birden fazla mağaza varsa Plenty\'den manuel girin.'),
                ]),

            Forms\Components\Section::make('B2B Müşteri Sınıfları')
                ->description('Bu tedarikçide B2B müşterileri ayıran Plenty contact class\'ları. Listede gözükmeyenler için "Plenty Sınıflarını Yenile" aksiyonunu çalıştırın.')
                ->hiddenOn('create')
                ->schema([
                    Forms\Components\Select::make('b2b_class_ids')
                        ->label('B2B Sınıfları')
                        ->multiple()
                        ->searchable()
                        ->placeholder('Sınıf ara (örn: "Händler", "B2B")')
                        ->helperText('Plenty\'deki tüm contact sınıflarından B2B olanları seçin. İlk yükte canlı çekilir; sonra cache\'lenir (10dk).')
                        ->options(function (?Supplier $record) {
                            if (! $record) {
                                return [];
                            }

                            return \Illuminate\Support\Facades\Cache::remember(
                                "plenty:classes:supplier:{$record->id}",
                                now()->addMinutes(10),
                                function () use ($record) {
                                    try {
                                        $classes = (new \App\Services\Plenty\PlentyClient($record))->listContactClasses();

                                        return collect($classes)
                                            ->mapWithKeys(fn ($name, $id) => [$id => "#{$id} — {$name}"])
                                            ->all();
                                    } catch (\Throwable $e) {
                                        return [];
                                    }
                                }
                            );
                        }),
                ]),

            Forms\Components\Section::make('Sahip')
                ->schema([
                    Forms\Components\Select::make('owner_user_id')
                        ->label('Tedarikçi Sahibi (opsiyonel)')
                        ->relationship('owner', 'email')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kind')->badge(),
                Tables\Columns\TextColumn::make('plenty_base_url')
                    ->label('Plenty URL')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tenants_count')
                    ->counts('tenants')
                    ->label('Bağlı Bayi'),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d.m.Y H:i')->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Aktif',
                    'pending' => 'Onay bekliyor',
                    'suspended' => 'Askıya alınmış',
                ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('test_connection')
                        ->label('Bağlantıyı Test Et')
                        ->icon('heroicon-o-bolt')
                        ->color('warning')
                        ->action(function (Supplier $record) {
                            $result = (new PlentyClient($record))->testConnection();

                            $notification = Notification::make()
                                ->title($result['ok'] ? 'Plenty bağlantısı başarılı' : 'Plenty bağlantısı başarısız')
                                ->body($result['message']);

                            $result['ok']
                                ? $notification->success()->send()
                                : $notification->danger()->persistent()->send();
                        }),

                    Tables\Actions\Action::make('sync_references')
                        ->label('Plenty Referanslarını Senkronize Et')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalDescription('Plenty\'den referrers, warehouses, order statuses ve sales prices çekilip kaydedilecek. ~5-10 sn sürebilir.')
                        ->action(function (Supplier $record) {
                            try {
                                $counts = (new PlentyClient($record))->syncReferences();
                                Notification::make()
                                    ->title('Referans senkronu tamam')
                                    ->body(\sprintf(
                                        'Referrers: %d, Warehouses: %d, Statuses: %d, Sales Prices: %d',
                                        $counts['referrer'] ?? 0,
                                        $counts['warehouse'] ?? 0,
                                        $counts['order_status'] ?? 0,
                                        $counts['sales_price'] ?? 0,
                                    ))
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Referans senkronu başarısız')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('lookup_sku')
                        ->label('SKU Sorgula')
                        ->icon('heroicon-o-magnifying-glass')
                        ->color('success')
                        ->form([
                            Forms\Components\TextInput::make('sku')
                                ->label('Plenty SKU (variation number)')
                                ->required()
                                ->placeholder('örn. EB600-AP')
                                ->autocomplete('off'),
                            Forms\Components\Toggle::make('force')
                                ->label('Cache\'i atla, Plenty\'den taze çek')
                                ->default(false),
                        ])
                        ->action(function (Supplier $record, array $data) {
                            try {
                                $client = new PlentyClient($record);
                                $r = $client->lookupSku($data['sku'], force: (bool) ($data['force'] ?? false));

                                if (! $r['found']) {
                                    Notification::make()
                                        ->title("SKU bulunamadı: {$r['sku']}")
                                        ->body('Plenty\'de bu SKU\'ya karşılık bir variation yok. Negative cache kaydedildi.')
                                        ->warning()
                                        ->persistent()
                                        ->send();

                                    return;
                                }

                                $lines = [
                                    "Variation ID: {$r['variation_id']}",
                                    "Item ID: {$r['item_id']}",
                                    "Name: {$r['name']}",
                                    'Price: ' . ($r['price'] !== null
                                        ? '€' . number_format($r['price'], 2)
                                            . " (priceId #{$r['price_source_id']})"
                                        : '— (default_sales_price_id seçilmemiş)'),
                                    'Stock: ' . ($r['stock_net'] ?? '—'),
                                    'Active: ' . ($r['is_active'] ? '✓' : '✗ (Plenty\'de pasif)'),
                                ];

                                Notification::make()
                                    ->title("SKU bulundu: {$r['sku']}")
                                    ->body(implode("\n", $lines))
                                    ->success()
                                    ->persistent()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('SKU sorgusu hata verdi')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('view_b2b_contacts')
                        ->label('B2B Müşterileri Görüntüle')
                        ->icon('heroicon-o-building-office-2')
                        ->color('primary')
                        ->modalHeading(fn (Supplier $record) => $record->name . ' — B2B Müşteriler')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Kapat')
                        ->modalWidth('7xl')
                        ->modalContent(function (Supplier $record) {
                            $contacts = [];
                            $error = null;
                            try {
                                $contacts = (new PlentyClient($record))->listB2BContacts(200);
                            } catch (\Throwable $e) {
                                $error = $e->getMessage();
                            }

                            return view('filament.modals.plenty-b2b-contacts', [
                                'supplier' => $record,
                                'contacts' => $contacts,
                                'error' => $error,
                            ]);
                        }),

                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
