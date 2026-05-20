<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Multi-Tenancy';

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Kullanıcı')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Ad Soyad')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label('E-posta')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('password')
                        ->label('Şifre')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $operation) => $operation === 'create')
                        ->helperText('Düzenlemede boş bırakılırsa eski şifre korunur.'),

                    Forms\Components\DateTimePicker::make('email_verified_at')
                        ->label('E-posta Doğrulandı')
                        ->default(now()),
                ]),

            Forms\Components\Section::make('Roller & Bayi')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->label('Roller')
                        ->multiple()
                        ->relationship('roles', 'name')
                        ->options(Role::pluck('name', 'name'))
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('current_tenant_id')
                        ->label('Aktif Bayi')
                        ->relationship('currentTenant', 'name')
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
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roller')
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('currentTenant.name')
                    ->label('Aktif Bayi')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Doğrulandı')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d.m.Y H:i')->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
