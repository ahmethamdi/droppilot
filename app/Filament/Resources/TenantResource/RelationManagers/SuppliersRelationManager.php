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

    protected static ?string $title = 'Bağlı Tedarikçiler';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('plenty_contact_id')
                ->label('Plenty Contact ID (Rechnungsadresse)')
                ->required()
                ->numeric()
                ->helperText('Bayinin bu tedarikçideki B2B müşteri ID\'si — fatura bu kayda kesilir.'),

            Forms\Components\TextInput::make('default_billing_address_id')
                ->label('Varsayılan Fatura Adresi ID')
                ->numeric()
                ->helperText('Boş bırakılırsa kontaktın birincil adresi kullanılır. "Contact Doğrula" sonrası seçilebilir.'),

            Forms\Components\TextInput::make('markup_pct')
                ->label('Markup % (raporlama)')
                ->numeric()
                ->default(0)
                ->step(0.01)
                ->suffix('%')
                ->helperText('Sadece raporlama; gerçek fiyatlandırma SKU başına Plenty\'den çekilir.'),

            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Onay bekliyor',
                    'active' => 'Aktif',
                    'suspended' => 'Askıya alınmış',
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
                Tables\Columns\TextColumn::make('id')->label('Supplier ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Tedarikçi')->searchable(),
                Tables\Columns\TextColumn::make('pivot.plenty_contact_id')
                    ->label('Plenty Contact ID')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('pivot.default_billing_address_id')
                    ->label('Fatura Adresi ID')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('pivot.markup_pct')
                    ->label('Markup')
                    ->suffix(' %'),
                Tables\Columns\TextColumn::make('pivot.status')
                    ->label('Bağlantı Durumu')
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
                    ->label('Tedarikçi Bağla')
                    ->preloadRecordSelect()
                    ->recordSelect(fn ($select) => $select->placeholder('Tedarikçi seçin'))
                    ->form(fn (Tables\Actions\AttachAction $action) => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('plenty_contact_id')
                            ->label('Plenty Contact ID (Rechnungsadresse)')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('default_billing_address_id')
                            ->label('Fatura Adresi ID (opsiyonel)')
                            ->numeric(),
                        Forms\Components\TextInput::make('markup_pct')
                            ->label('Markup %')
                            ->numeric()
                            ->default(0)
                            ->step(0.01),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Onay bekliyor',
                                'active' => 'Aktif',
                                'suspended' => 'Askıya alınmış',
                            ])
                            ->default('active')
                            ->required(),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('verify_contact')
                    ->label('Contact Doğrula')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->action(function (Model $record) {
                        /** @var Supplier $record */
                        $contactId = (int) $record->pivot->plenty_contact_id;

                        if (! $contactId) {
                            Notification::make()
                                ->title('Plenty Contact ID girilmemiş')
                                ->danger()->send();

                            return;
                        }

                        $result = (new PlentyClient($record))->verifyContact($contactId);

                        $notification = Notification::make()
                            ->title($result['ok'] ? 'Contact doğrulandı' : 'Contact doğrulanamadı')
                            ->body($result['message']);

                        $result['ok']
                            ? $notification->success()->send()
                            : $notification->danger()->persistent()->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make()->label('Bağlantıyı Kaldır'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
