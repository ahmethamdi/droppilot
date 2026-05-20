<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Multi-Tenancy';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Bayi Bilgileri')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Bayi Adı')
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
                        ->unique(ignoreRecord: true)
                        ->helperText('URL-friendly tekil tanımlayıcı'),

                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => 'Aktif',
                            'pending' => 'Onay bekliyor',
                            'suspended' => 'Askıya alınmış',
                        ])
                        ->default('active')
                        ->required()
                        ->native(false),

                    Forms\Components\TextInput::make('plan')
                        ->maxLength(255)
                        ->placeholder('starter / pro / enterprise')
                        ->helperText('Opsiyonel — billing modülü için'),
                ]),

            Forms\Components\Section::make('Üyeler')
                ->description('Bayiye bağlı kullanıcılar (sadece kayıt sonrası eklenebilir).')
                ->schema([
                    Forms\Components\Placeholder::make('users_info')
                        ->label('')
                        ->content(function ($record) {
                            if (! $record) {
                                return 'Kaydı oluşturduktan sonra üye ekleyebilirsiniz.';
                            }

                            $list = $record->users
                                ->map(fn ($u) => "• {$u->name} <{$u->email}> ({$u->pivot->role})")
                                ->implode("\n");

                            return $list ?: 'Henüz üye yok.';
                        }),
                ])
                ->hiddenOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Üye'),
                Tables\Columns\TextColumn::make('suppliers_count')
                    ->counts('suppliers')
                    ->label('Tedarikçi'),
                Tables\Columns\TextColumn::make('plan')->toggleable(),
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            TenantResource\RelationManagers\SuppliersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
