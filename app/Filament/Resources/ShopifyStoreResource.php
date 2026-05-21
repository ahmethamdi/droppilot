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

                    Forms\Components\DateTimePicker::make('installed_at')
                        ->label('Yüklenme Zamanı')
                        ->disabled(),

                    Forms\Components\Placeholder::make('current_link')
                        ->label('Mevcut Eşleşme')
                        ->content(function (?ShopifyStore $record) {
                            if (! $record || ! $record->plenty_contact_id) {
                                return '— Henüz Plenty müşterisine eşlenmemiş.';
                            }
                            $supplier = $record->supplier;
                            $tenant = $record->tenant;
                            $lines = [];
                            if ($tenant) {
                                $lines[] = "Bayi: {$tenant->name}";
                            }
                            if ($supplier) {
                                $lines[] = "Tedarikçi: {$supplier->name}";
                            }
                            $lines[] = "Plenty contact: #{$record->plenty_contact_id}";

                            return implode("\n", $lines);
                        }),
                ]),

            Forms\Components\Section::make('Bayi Eşleştirmesi')
                ->description('Bu mağazadan sipariş geldiğinde hangi bayinin (Plenty B2B müşterisinin) hesabına fatura kesilecek. Dropdown\'da aktif tedarikçilerin B2B sınıflarındaki tüm müşteriler yüklü gelir.')
                ->schema([
                    Forms\Components\Select::make('mapping_bayi')
                        ->label('Bayi')
                        ->placeholder('Şirket adı veya e-posta ile ara...')
                        ->searchable()
                        ->dehydrated(false)
                        ->helperText('Sonuç formatı: "Şirket — e-posta (Tedarikçi #PlentyID)". Tedarikçi başına 200 sonuca kadar.')
                        ->getSearchResultsUsing(function (string $search) {
                            $needle = mb_strtolower(trim($search));
                            if (mb_strlen($needle) < 2) {
                                return [];
                            }

                            $isNumeric = ctype_digit($needle);
                            $results = [];

                            foreach (Supplier::where('status', 'active')->get() as $supplier) {
                                $client = new \App\Services\Plenty\PlentyClient($supplier);
                                $contacts = [];

                                // 1) Sayı ise direkt Plenty contact ID lookup
                                if ($isNumeric) {
                                    try {
                                        $one = $client->getContact((int) $needle);
                                        if ($one) {
                                            $contacts[] = [
                                                'id' => (int) $one['id'],
                                                'first_name' => $one['firstName'] ?? '',
                                                'last_name' => $one['lastName'] ?? '',
                                                'company' => $one['accounts'][0]['companyName'] ?? '',
                                                'email' => $one['email'] ?? '',
                                                'class_id' => (int) ($one['classId'] ?? 0),
                                            ];
                                        }
                                    } catch (\Throwable $e) {
                                        // ignore
                                    }
                                }

                                // 2) E-posta ile direkt Plenty arama (her class'a bakar)
                                if (! $isNumeric) {
                                    try {
                                        $contacts = array_merge($contacts, $client->searchContactsByEmail($needle, 25));
                                    } catch (\Throwable $e) {
                                        // ignore
                                    }
                                }

                                // 3) B2B class cache'inden de tara (şirket adı, email, id parça eşleşmeleri)
                                try {
                                    $cached = $client->listB2BContacts(1500);
                                    foreach ($cached as $c) {
                                        $haystack = mb_strtolower(
                                            ($c['company'] ?? '').' '.
                                            ($c['email'] ?? '').' '.
                                            ($c['first_name'] ?? '').' '.
                                            ($c['last_name'] ?? '').' '.
                                            $c['id']
                                        );
                                        if (str_contains($haystack, $needle)) {
                                            $contacts[] = $c;
                                        }
                                    }
                                } catch (\Throwable $e) {
                                    // ignore
                                }

                                // De-dupe by Plenty ID
                                $seen = [];
                                foreach ($contacts as $c) {
                                    if (isset($seen[$c['id']])) {
                                        continue;
                                    }
                                    $seen[$c['id']] = true;

                                    $key = "{$supplier->id}:{$c['id']}";
                                    $company = $c['company'] ?? '';
                                    $email = $c['email'] ?? '';
                                    $label = $company !== '' ? "{$company}" : "Plenty #{$c['id']}";
                                    if ($email !== '') {
                                        $label .= " — {$email}";
                                    }
                                    $results[$key] = "{$label} ({$supplier->name} #{$c['id']})";

                                    if (count($results) >= 50) {
                                        break 2;
                                    }
                                }
                            }

                            return $results;
                        })
                        ->getOptionLabelUsing(function ($value) {
                            if (! $value || ! str_contains($value, ':')) {
                                return null;
                            }
                            [$supplierId, $contactId] = explode(':', $value, 2);
                            $supplier = Supplier::find($supplierId);
                            if (! $supplier) {
                                return "Plenty #{$contactId}";
                            }
                            try {
                                $contact = (new \App\Services\Plenty\PlentyClient($supplier))->getContact((int) $contactId);
                                if (! $contact) {
                                    return "{$supplier->name} #{$contactId}";
                                }
                                $company = $contact['accounts'][0]['companyName'] ?? '';
                                $email = $contact['email'] ?? '';

                                return "{$company} — {$email} ({$supplier->name} #{$contactId})";
                            } catch (\Throwable $e) {
                                return "{$supplier->name} #{$contactId}";
                            }
                        })
                        ->afterStateHydrated(function (Forms\Components\Select $component, ?ShopifyStore $record) {
                            if (! $record || ! $record->tenant_id) {
                                return;
                            }
                            $supplier = $record->tenant?->suppliers()->first();
                            if ($supplier && $supplier->pivot->plenty_contact_id) {
                                $component->state("{$supplier->id}:{$supplier->pivot->plenty_contact_id}");
                            }
                        }),
                ])
                ->hiddenOn('create'),

            Forms\Components\Section::make('Plenty Sipariş Ayarları')
                ->description('Bu mağazadan gelen siparişler Plenty Auftrag olarak düşerken kullanılacak ayarlar. Bayinin satış fiyatı tipi farklı olabilir (Level 5, B2B Standard, vb.) — burada elle seçiyorsun.')
                ->columns(2)
                ->hiddenOn('create')
                ->schema([
                    Forms\Components\Select::make('plenty_sales_price_id')
                        ->label('Satış Fiyatı Tipi')
                        ->options(function (?ShopifyStore $record) {
                            $supplier = $record?->supplier ?? $record?->tenant?->suppliers()->first();
                            if (! $supplier) {
                                return [];
                            }

                            return $supplier
                                ->referencesOfKind(\App\Models\SupplierReference::KIND_SALES_PRICE)
                                ->orderBy('external_id')
                                ->get()
                                ->mapWithKeys(fn ($r) => [(int) $r->external_id => $r->label])
                                ->all();
                        })
                        ->searchable()
                        ->placeholder('Bayinin fiyat tipini seçin (örn. Level 5)')
                        ->helperText('Bu mağazadan gelen siparişlerin Plenty\'deki birim fiyatı bu tipten alınır. SKU başına Plenty\'den canlı çekilir.'),

                    Forms\Components\Select::make('plenty_warehouse_id')
                        ->label('Depo')
                        ->options(function (?ShopifyStore $record) {
                            $supplier = $record?->supplier ?? $record?->tenant?->suppliers()->first();
                            if (! $supplier) {
                                return [];
                            }

                            return $supplier
                                ->referencesOfKind(\App\Models\SupplierReference::KIND_WAREHOUSE)
                                ->orderBy('external_id')
                                ->get()
                                ->mapWithKeys(fn ($r) => [(int) $r->external_id => "#{$r->external_id} — {$r->label}"])
                                ->all();
                        })
                        ->searchable()
                        ->placeholder('Sevkıyat deposu (örn. Hilden)'),

                    Forms\Components\Select::make('plenty_order_status_id')
                        ->label('Yeni Sipariş Statüsü')
                        ->options(function (?ShopifyStore $record) {
                            $supplier = $record?->supplier ?? $record?->tenant?->suppliers()->first();
                            if (! $supplier) {
                                return [];
                            }

                            return $supplier
                                ->referencesOfKind(\App\Models\SupplierReference::KIND_ORDER_STATUS)
                                ->orderBy('external_id')
                                ->get()
                                ->mapWithKeys(fn ($r) => [(string) $r->external_id => $r->label])
                                ->all();
                        })
                        ->searchable()
                        ->placeholder('Yeni Auftrag\'lar bu statüde açılır')
                        ->helperText('Boş bırakılırsa tedarikçinin default sipariş statüsü kullanılır.'),
                ]),

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
