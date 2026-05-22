<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use App\Models\Supplier;
use App\Services\Plenty\PlentyClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SuppliersRelationManager extends RelationManager
{
    protected static string $relationship = 'suppliers';

    protected static ?string $title = 'Verbundene Lieferanten';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('plenty_contact_id')
                ->label('Plenty-Kontakt-ID (Rechnungsadresse)')
                ->required()
                ->numeric()
                ->helperText('Die B2B-Kunden-ID des Händlers bei diesem Lieferanten — die Rechnung wird auf diesen Datensatz ausgestellt.'),

            Forms\Components\TextInput::make('default_billing_address_id')
                ->label('Standard-Rechnungsadressen-ID')
                ->numeric()
                ->helperText('Bleibt das Feld leer, wird die primäre Adresse des Kontakts verwendet. Auswahl nach „Kontakt prüfen" möglich.'),

            Forms\Components\TextInput::make('markup_pct')
                ->label('Aufschlag % (Reporting)')
                ->numeric()
                ->default(0)
                ->step(0.01)
                ->suffix('%')
                ->helperText('Nur für Auswertungen; die tatsächliche Preisermittlung erfolgt pro SKU live aus Plenty.'),

            Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    'pending' => 'Wartet auf Freigabe',
                    'active' => 'Aktiv',
                    'suspended' => 'Gesperrt',
                ])
                ->default('pending')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Lieferant-ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Lieferant')->searchable(),
                Tables\Columns\TextColumn::make('pivot.plenty_contact_id')
                    ->label('Plenty-Kontakt-ID')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('pivot.default_billing_address_id')
                    ->label('Rechnungsadressen-ID')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('pivot.markup_pct')
                    ->label('Aufschlag')
                    ->suffix(' %'),
                Tables\Columns\TextColumn::make('pivot.status')
                    ->label('Verbindungsstatus')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Lieferanten verbinden')
                    ->preloadRecordSelect()
                    ->recordSelect(fn ($select) => $select->placeholder('Lieferant auswählen'))
                    ->form(fn (Tables\Actions\AttachAction $action) => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('plenty_contact_id')
                            ->label('Plenty-Kontakt-ID (Rechnungsadresse)')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('default_billing_address_id')
                            ->label('Rechnungsadressen-ID (optional)')
                            ->numeric(),
                        Forms\Components\TextInput::make('markup_pct')
                            ->label('Aufschlag %')
                            ->numeric()
                            ->default(0)
                            ->step(0.01),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Wartet auf Freigabe',
                                'active' => 'Aktiv',
                                'suspended' => 'Gesperrt',
                            ])
                            ->default('active')
                            ->required(),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('verify_contact')
                    ->label('Kontakt prüfen')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->action(function (Model $record) {
                        /** @var Supplier $record */
                        $contactId = (int) $record->pivot->plenty_contact_id;

                        if (! $contactId) {
                            Notification::make()
                                ->title('Plenty-Kontakt-ID wurde nicht eingegeben')
                                ->danger()->send();

                            return;
                        }

                        $result = (new PlentyClient($record))->verifyContact($contactId);

                        $notification = Notification::make()
                            ->title($result['ok'] ? 'Kontakt bestätigt' : 'Kontakt konnte nicht bestätigt werden')
                            ->body($result['message']);

                        $result['ok']
                            ? $notification->success()->send()
                            : $notification->danger()->persistent()->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make()->label('Verbindung entfernen'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
