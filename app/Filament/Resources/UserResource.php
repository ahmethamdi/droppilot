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

    protected static ?string $navigationGroup = 'Mandanten';

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?int $navigationSort = 30;

    protected static ?string $label = 'Benutzer';

    protected static ?string $pluralLabel = 'Benutzer';

    protected static ?string $navigationLabel = 'Benutzer';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Benutzer')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Vor- und Nachname')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label('E-Mail')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('password')
                        ->label('Passwort')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $operation) => $operation === 'create')
                        ->helperText('Beim Bearbeiten leer lassen, um das alte Passwort beizubehalten.'),

                    Forms\Components\DateTimePicker::make('email_verified_at')
                        ->label('E-Mail bestätigt am')
                        ->default(now()),
                ]),

            Forms\Components\Section::make('Rollen & Händler')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->label('Rollen')
                        ->multiple()
                        ->relationship('roles', 'name')
                        ->options(Role::pluck('name', 'name'))
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('current_tenant_id')
                        ->label('Aktiver Händler')
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
                    ->label('Rollen')
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('currentTenant.name')
                    ->label('Aktiver Händler')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Bestätigt')
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
