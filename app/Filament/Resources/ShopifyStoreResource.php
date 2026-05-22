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

    protected static ?string $navigationGroup = 'Mandanten';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 25;

    protected static ?string $label = 'Shopify-Shop';

    protected static ?string $pluralLabel = 'Shopify-Shops';

    protected static ?string $navigationLabel = 'Shopify-Shops';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Shop-Informationen')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Shopify-Domain')
                        ->disabled()
                        ->helperText('Wird beim OAuth-Login automatisch befüllt.'),

                    Forms\Components\TextInput::make('email')
                        ->label('Shop-E-Mail')
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('installed_at')
                        ->label('Installiert am')
                        ->disabled(),

                    Forms\Components\Placeholder::make('current_link')
                        ->label('Aktuelle Zuordnung')
                        ->content(function (?ShopifyStore $record) {
                            if (! $record || ! $record->plenty_contact_id) {
                                return '— Noch keinem Plenty-Kunden zugeordnet.';
                            }
                            $supplier = $record->supplier;
                            $tenant = $record->tenant;
                            $lines = [];
                            if ($tenant) {
                                $lines[] = "Händler: {$tenant->name}";
                            }
                            if ($supplier) {
                                $lines[] = "Lieferant: {$supplier->name}";
                            }
                            $lines[] = "Plenty-Kontakt: #{$record->plenty_contact_id}";

                            return implode("\n", $lines);
                        }),
                ]),

            Forms\Components\Section::make('Händler-Zuordnung')
                ->description('Welcher Händler (Plenty-B2B-Kunde) wird abgerechnet, wenn dieser Shop eine Bestellung erhält? Im Dropdown sind alle Kunden aus den B2B-Klassen aktiver Lieferanten verfügbar.')
                ->schema([
                    Forms\Components\Select::make('mapping_bayi')
                        ->label('Händler')
                        ->placeholder('Nach Firmenname oder E-Mail suchen ...')
                        ->searchable()
                        ->dehydrated(false)
                        ->helperText('Ergebnisformat: „Firma — E-Mail (Lieferant #PlentyID)". Bis zu 200 Treffer pro Lieferant.')
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

            Forms\Components\Section::make('Plenty-Auftragseinstellungen')
                ->description('Diese Werte werden verwendet, wenn Bestellungen dieses Shops in Plenty als Auftrag angelegt werden. Der Verkaufspreistyp kann pro Händler abweichen (Level 5, B2B Standard, etc.) — hier wird er manuell gesetzt.')
                ->columns(2)
                ->hiddenOn('create')
                ->schema([
                    Forms\Components\Select::make('plenty_sales_price_id')
                        ->label('Verkaufspreistyp')
                        ->options(fn (?ShopifyStore $record) => static::optionsForKind(
                            $record,
                            \App\Models\SupplierReference::KIND_SALES_PRICE,
                            fn ($r) => [(int) $r->external_id => $r->label],
                        ))
                        ->searchable()
                        ->preload()
                        ->placeholder('Preistyp des Händlers wählen (z. B. Level 5)')
                        ->helperText('Der Einzelpreis im Plenty-Auftrag wird aus diesem Preistyp pro SKU live aus Plenty geladen.'),

                    Forms\Components\Select::make('plenty_warehouse_id')
                        ->label('Lager')
                        ->options(fn (?ShopifyStore $record) => static::optionsForKind(
                            $record,
                            \App\Models\SupplierReference::KIND_WAREHOUSE,
                            fn ($r) => [(int) $r->external_id => "#{$r->external_id} — {$r->label}"],
                        ))
                        ->searchable()
                        ->preload()
                        ->placeholder('Versandlager (z. B. Hilden)'),

                    Forms\Components\Select::make('plenty_order_status_id')
                        ->label('Status für neue Aufträge')
                        ->options(fn (?ShopifyStore $record) => static::optionsForKind(
                            $record,
                            \App\Models\SupplierReference::KIND_ORDER_STATUS,
                            fn ($r) => [(string) $r->external_id => $r->label],
                        ))
                        ->searchable()
                        ->preload()
                        ->placeholder('Neue Aufträge werden in diesem Status angelegt')
                        ->helperText('Bleibt das Feld leer, wird der Standard-Auftragsstatus des Lieferanten verwendet.'),
                ]),

            Forms\Components\Section::make('Technische Informationen')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('shopify_offline_access_token_expires_at')
                        ->label('Token-Gültigkeit')
                        ->disabled(),
                    Forms\Components\Textarea::make('scopes')
                        ->label('Erteilte Berechtigungen')
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
                    ->label('Shopify-Domain')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Händler')
                    ->placeholder('— nicht verbunden')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('shopify_offline_access_token_expires_at')
                    ->label('Token-Gültigkeit')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Installiert')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Nach Händler')
                    ->relationship('tenant', 'name'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('add_manual')
                    ->label('Shop manuell hinzufügen')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->modalHeading('Shop mit Custom-App-Token verbinden')
                    ->modalDescription('Der Händler erstellt im eigenen Shopify-Admin unter „Apps → Apps und Vertriebskanäle entwickeln" eine private App und teilt den Admin-API-Access-Token mit. Token-Format: shpat_...')
                    ->modalSubmitActionLabel('Verbinden')
                    ->modalIcon('heroicon-o-link')
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\TextInput::make('shop_domain')
                            ->label('Shopify-Shop-Domain')
                            ->placeholder('mein-shop.myshopify.com')
                            ->required()
                            ->helperText('Vollständige myshopify.com-Domain (ohne https://). Wird normalisiert.')
                            ->dehydrateStateUsing(fn ($state) => static::normalizeShopDomain((string) $state)),
                        Forms\Components\TextInput::make('access_token')
                            ->label('Admin-API-Access-Token')
                            ->password()
                            ->revealable()
                            ->required()
                            ->placeholder('shpat_...')
                            ->helperText('Wird einmalig im Shopify-Admin angezeigt, wenn die Custom App installiert wird.'),
                        Forms\Components\TextInput::make('email')
                            ->label('Shop-E-Mail (optional)')
                            ->email()
                            ->placeholder('inhaber@beispiel.de'),
                    ])
                    ->action(function (array $data) {
                        $domain = static::normalizeShopDomain((string) $data['shop_domain']);
                        $token = trim((string) $data['access_token']);

                        if (! str_ends_with($domain, '.myshopify.com')) {
                            Notification::make()
                                ->title('Ungültige Shop-Domain')
                                ->body('Die Domain muss auf .myshopify.com enden.')
                                ->danger()->send();

                            return;
                        }

                        if ($domain === '' || $token === '') {
                            Notification::make()->title('Domain und Token sind erforderlich')->danger()->send();

                            return;
                        }

                        $existing = ShopifyStore::where('name', $domain)->first();
                        if ($existing) {
                            $existing->update([
                                'password' => $token,
                                'email' => $data['email'] ?: $existing->email,
                                'installed_at' => $existing->installed_at ?? now(),
                                'uninstalled_at' => null,
                            ]);
                            $store = $existing;
                        } else {
                            $store = ShopifyStore::create([
                                'name' => $domain,
                                'password' => $token,
                                'email' => $data['email'] ?: null,
                                'installed_at' => now(),
                            ]);
                        }

                        try {
                            $result = (new ShopifyClient($store))->testConnection();
                            if ($result['ok']) {
                                Notification::make()
                                    ->title('Shop verbunden')
                                    ->body($domain.' ist bereit. Bitte unter „Bearbeiten" Händler und Verkaufspreistyp zuweisen.')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Shop gespeichert, aber Verbindungstest fehlgeschlagen')
                                    ->body($result['message'])
                                    ->warning()
                                    ->persistent()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Shop gespeichert, Token ungeprüft')
                                ->body($e->getMessage())
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('test_connection')
                        ->label('Verbindung testen')
                        ->icon('heroicon-o-bolt')
                        ->color('warning')
                        ->action(function (ShopifyStore $record) {
                            $result = (new ShopifyClient($record))->testConnection();

                            $notification = Notification::make()
                                ->title($result['ok'] ? 'Shopify-Verbindung erfolgreich' : 'Shopify-Verbindung fehlgeschlagen')
                                ->body($result['message']);

                            $result['ok']
                                ? $notification->success()->send()
                                : $notification->danger()->persistent()->send();
                        }),

                    Tables\Actions\Action::make('view_orders')
                        ->label('Bestellungen anzeigen')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->color('info')
                        ->modalHeading(fn (ShopifyStore $record) => $record->name . ' — Letzte Bestellungen')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Schließen')
                        ->modalWidth('7xl')
                        ->modalContent(function (ShopifyStore $record) {
                            try {
                                $orders = (new \App\Services\Shopify\ShopifyClient($record))->getOrders(50);
                            } catch (\Throwable $e) {
                                $orders = [];
                                $error = $e->getMessage();
                            }

                            $pushed = \App\Models\PlentyOrder::query()
                                ->where('shopify_store_id', $record->id)
                                ->whereIn('shopify_order_id', collect($orders)->pluck('id')->all())
                                ->get()
                                ->keyBy('shopify_order_id');

                            $canPush = $record->supplier_id
                                && $record->plenty_contact_id
                                && $record->plenty_sales_price_id;

                            return view('filament.modals.shopify-orders', [
                                'orders' => $orders,
                                'error' => $error ?? null,
                                'storeId' => $record->id,
                                'pushed' => $pushed,
                                'canPush' => $canPush,
                            ]);
                        }),

                    Tables\Actions\Action::make('view_customers')
                        ->label('Kunden anzeigen')
                        ->icon('heroicon-o-users')
                        ->color('info')
                        ->modalHeading(fn (ShopifyStore $record) => $record->name . ' — Kunden')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Schließen')
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

                    Tables\Actions\EditAction::make()->label('Bearbeiten / Händler zuordnen'),

                    Tables\Actions\Action::make('force_delete')
                        ->label('Shop endgültig löschen')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Shop endgültig löschen?')
                        ->modalDescription(fn (ShopifyStore $record) => "Shop {$record->name} wird unwiderruflich aus der Datenbank entfernt — inklusive Token, Händler-Zuordnung und allen verknüpften Übertragungen. Bestätigung erforderlich.")
                        ->modalSubmitActionLabel('Endgültig löschen')
                        ->action(function (ShopifyStore $record) {
                            $name = $record->name;
                            $record->forceDelete();

                            Notification::make()
                                ->title("Shop {$name} entfernt")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_force_delete')
                        ->label('Ausgewählte Shops endgültig löschen')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Ausgewählte Shops endgültig löschen?')
                        ->modalDescription('Die markierten Shops werden unwiderruflich entfernt.')
                        ->modalSubmitActionLabel('Endgültig löschen')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $count = $records->count();
                            foreach ($records as $r) {
                                $r->forceDelete();
                            }

                            Notification::make()
                                ->title("{$count} Shops entfernt")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Noch keine verbundenen Shopify-Shops')
            ->emptyStateDescription('Sobald ein Händler seinen Shopify-Shop mit DropPilot verbindet, erscheint er hier.')
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->defaultSort('id', 'desc');
    }

    /**
     * Shopify shop domain'ini normalize et: https://, slash, boşluk temizle, lowercase.
     */
    protected static function normalizeShopDomain(string $input): string
    {
        $clean = trim(strtolower($input));
        $clean = preg_replace('#^https?://#i', '', $clean) ?? $clean;
        $clean = rtrim($clean, '/');

        return $clean;
    }

    /**
     * Plenty Sipariş Ayarları dropdown'ları için supplier referans satırlarını çek.
     *
     * Supplier resolution sırası:
     *   1. Mağazaya direkt bağlı supplier (shopify_stores.supplier_id)
     *   2. Tenant üzerinden ilk supplier
     *   3. Sistemdeki ilk aktif supplier (Bayi Eşleştirmesi henüz yapılmamış mağazalar için
     *      dropdown'lar yine de dolu gözüksün; Vapor Handels tek supplier olduğundan güvenli)
     */
    protected static function optionsForKind(?ShopifyStore $record, string $kind, \Closure $map): array
    {
        $supplier = $record?->supplier
            ?? $record?->tenant?->suppliers()->first()
            ?? Supplier::where('status', 'active')->orderBy('id')->first();

        if (! $supplier) {
            return [];
        }

        return $supplier
            ->referencesOfKind($kind)
            ->orderBy('external_id')
            ->get()
            ->mapWithKeys($map)
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopifyStores::route('/'),
            'edit' => Pages\EditShopifyStore::route('/{record}/edit'),
        ];
    }
}
