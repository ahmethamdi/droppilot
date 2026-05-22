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

    protected static ?string $navigationGroup = 'Mandanten';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 20;

    protected static ?string $label = 'Lieferant';

    protected static ?string $pluralLabel = 'Lieferanten';

    protected static ?string $navigationLabel = 'Lieferanten';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Allgemein')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Lieferantenname')
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
                        ->label('Typ')
                        ->options([
                            'plenty' => 'PlentyMarkets',
                        ])
                        ->default('plenty')
                        ->required()
                        ->native(false),

                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => 'Aktiv',
                            'pending' => 'Wartet auf Freigabe',
                            'suspended' => 'Gesperrt',
                        ])
                        ->default('active')
                        ->required()
                        ->native(false),
                ]),

            Forms\Components\Section::make('PlentyMarkets-Verbindung')
                ->description('Zugangsdaten werden verschlüsselt gespeichert und nicht geloggt.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('plenty_base_url')
                        ->label('Plenty Base URL')
                        ->placeholder('https://p57085.my.plentysystems.com')
                        ->url()
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('plenty_login_user')
                        ->label('API-Benutzername')
                        ->required()
                        ->autocomplete('off'),

                    Forms\Components\TextInput::make('plenty_login_password')
                        ->label('API-Passwort')
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation) => $operation === 'create')
                        ->autocomplete('new-password')
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('Beim Bearbeiten wird das Feld automatisch befüllt. Leer lassen, wenn nicht geändert werden soll.'),
                ]),

            Forms\Components\Section::make('Plenty-Standardreferenzen')
                ->description('Zuerst „Plenty-Referenzen synchronisieren" ausführen, danach hier auswählen.')
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
                        ->placeholder('Keine Referenzen vorhanden — bitte zuerst synchronisieren')
                        ->helperText('DropPilot markiert Aufträge mit dieser Herkunft.'),

                    Forms\Components\Select::make('default_order_status_id')
                        ->label('Status für neue Aufträge')
                        ->options(fn (?Supplier $record) => $record
                            ? $record->referencesOfKind(\App\Models\SupplierReference::KIND_ORDER_STATUS)
                                ->orderBy('external_id')
                                ->get()
                                ->mapWithKeys(fn ($r) => [$r->external_id => $r->label])
                                ->all()
                            : [])
                        ->searchable()
                        ->placeholder('Bitte zuerst synchronisieren'),

                    Forms\Components\Select::make('default_sales_price_id')
                        ->label('Verkaufspreistyp (B2B)')
                        ->options(fn (?Supplier $record) => $record
                            ? $record->referencesOfKind(\App\Models\SupplierReference::KIND_SALES_PRICE)
                                ->orderBy('external_id')
                                ->get()
                                ->mapWithKeys(fn ($r) => [$r->external_id => $r->label])
                                ->all()
                            : [])
                        ->searchable()
                        ->helperText('Beim Anlegen des Plenty-Auftrags wird der Preis dieses Typs als priceOriginalGross verwendet.'),

                    Forms\Components\Select::make('default_warehouse_id')
                        ->label('Standardlager')
                        ->options(fn (?Supplier $record) => $record
                            ? $record->referencesOfKind(\App\Models\SupplierReference::KIND_WAREHOUSE)
                                ->orderBy('external_id')
                                ->get()
                                ->mapWithKeys(fn ($r) => [$r->external_id => "#{$r->external_id} — {$r->label}"])
                                ->all()
                            : [])
                        ->searchable()
                        ->placeholder('Bitte zuerst synchronisieren'),

                    Forms\Components\TextInput::make('default_plenty_id')
                        ->label('Mandant (plentyId)')
                        ->numeric()
                        ->helperText('Falls in diesem Konto mehrere Mandanten existieren, ID manuell aus Plenty eintragen.'),
                ]),

            Forms\Components\Section::make('B2B-Kundenklassen')
                ->description('Plenty-Kontaktklassen, die B2B-Kunden dieses Lieferanten umfassen. Fehlende Klassen können über „Plenty-Klassen aktualisieren" nachgeladen werden.')
                ->hiddenOn('create')
                ->schema([
                    Forms\Components\Select::make('b2b_class_ids')
                        ->label('B2B-Klassen')
                        ->multiple()
                        ->searchable()
                        ->placeholder('Klasse suchen (z. B. „Händler", „B2B")')
                        ->helperText('Alle B2B-relevanten Kontaktklassen aus Plenty auswählen. Erste Abfrage live; danach 10 Min. gecacht.')
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

            Forms\Components\Section::make('Inhaber')
                ->schema([
                    Forms\Components\Select::make('owner_user_id')
                        ->label('Lieferanteninhaber (optional)')
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
                    ->label('Verbundene Händler'),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d.m.Y H:i')->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Aktiv',
                    'pending' => 'Wartet auf Freigabe',
                    'suspended' => 'Gesperrt',
                ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('test_connection')
                        ->label('Verbindung testen')
                        ->icon('heroicon-o-bolt')
                        ->color('warning')
                        ->action(function (Supplier $record) {
                            $result = (new PlentyClient($record))->testConnection();

                            $notification = Notification::make()
                                ->title($result['ok'] ? 'Plenty-Verbindung erfolgreich' : 'Plenty-Verbindung fehlgeschlagen')
                                ->body($result['message']);

                            $result['ok']
                                ? $notification->success()->send()
                                : $notification->danger()->persistent()->send();
                        }),

                    Tables\Actions\Action::make('sync_references')
                        ->label('Plenty-Referenzen synchronisieren')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalDescription('Referrers, Lager, Auftragsstatus und Verkaufspreise werden aus Plenty geladen und gespeichert. Dauert ca. 5–10 Sekunden.')
                        ->action(function (Supplier $record) {
                            try {
                                $counts = (new PlentyClient($record))->syncReferences();
                                Notification::make()
                                    ->title('Referenzsynchronisation abgeschlossen')
                                    ->body(\sprintf(
                                        'Referrers: %d, Lager: %d, Status: %d, Verkaufspreise: %d',
                                        $counts['referrer'] ?? 0,
                                        $counts['warehouse'] ?? 0,
                                        $counts['order_status'] ?? 0,
                                        $counts['sales_price'] ?? 0,
                                    ))
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Referenzsynchronisation fehlgeschlagen')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('lookup_sku')
                        ->label('SKU abfragen')
                        ->icon('heroicon-o-magnifying-glass')
                        ->color('success')
                        ->form([
                            Forms\Components\TextInput::make('sku')
                                ->label('Plenty-SKU (Variantennummer)')
                                ->required()
                                ->placeholder('z. B. EB600-AP')
                                ->autocomplete('off'),
                            Forms\Components\Toggle::make('force')
                                ->label('Cache umgehen, direkt aus Plenty laden')
                                ->default(false),
                        ])
                        ->action(function (Supplier $record, array $data) {
                            try {
                                $client = new PlentyClient($record);
                                $r = $client->lookupSku($data['sku'], force: (bool) ($data['force'] ?? false));

                                if (! $r['found']) {
                                    Notification::make()
                                        ->title("SKU nicht gefunden: {$r['sku']}")
                                        ->body('In Plenty existiert keine Variante zu dieser SKU. Negativer Cache-Eintrag wurde gespeichert.')
                                        ->warning()
                                        ->persistent()
                                        ->send();

                                    return;
                                }

                                $lines = [
                                    "Variations-ID: {$r['variation_id']}",
                                    "Artikel-ID: {$r['item_id']}",
                                    "Name: {$r['name']}",
                                    'Preis: ' . ($r['price'] !== null
                                        ? '€' . number_format($r['price'], 2)
                                            . " (priceId #{$r['price_source_id']})"
                                        : '— (default_sales_price_id nicht gesetzt)'),
                                    'Bestand: ' . ($r['stock_net'] ?? '—'),
                                    'Aktiv: ' . ($r['is_active'] ? '✓' : '✗ (in Plenty inaktiv)'),
                                ];

                                Notification::make()
                                    ->title("SKU gefunden: {$r['sku']}")
                                    ->body(implode("\n", $lines))
                                    ->success()
                                    ->persistent()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('SKU-Abfrage fehlgeschlagen')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('view_b2b_contacts')
                        ->label('B2B-Kunden anzeigen')
                        ->icon('heroicon-o-building-office-2')
                        ->color('primary')
                        ->modalHeading(fn (Supplier $record) => $record->name . ' — B2B-Kunden')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Schließen')
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
