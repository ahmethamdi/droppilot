<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Form yüklenmeden önce credentials'ı decrypted hâliyle injekte et,
     * ki Edit sayfasında "API Kullanıcı Adı" ve "API Şifresi" boş gözükmesin.
     * (Supplier model'de Crypt accessor var, $record->plenty_login_user
     * decryptli döner — burada raw data'ya basıyoruz.)
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var \App\Models\Supplier $record */
        $record = $this->record;
        $data['plenty_login_user'] = $record->plenty_login_user;
        $data['plenty_login_password'] = $record->plenty_login_password;

        return $data;
    }
}
