<?php

namespace App\Filament\Resources\ShopifyStoreResource\Pages;

use App\Filament\Resources\ShopifyStoreResource;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\Plenty\PlentyClient;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditShopifyStore extends EditRecord
{
    protected static string $resource = ShopifyStoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Form'daki seçili tedarikçi + Plenty contact ID'yi alıp:
     * - Plenty'den şirket adını/email'i çek
     * - Tenant yarat (varsa al)
     * - tenant_supplier pivot kaydı (plenty_contact_id ile)
     * - ShopifyStore.tenant_id güncelle
     */
    protected function afterSave(): void
    {
        $data = $this->form->getRawState();
        $supplierId = $data['mapping_supplier_id'] ?? null;
        $contactId = $data['mapping_plenty_contact_id'] ?? null;

        if (! $supplierId || ! $contactId) {
            return; // eşleştirme yapılmamış
        }

        $supplier = Supplier::find($supplierId);
        if (! $supplier) {
            Notification::make()
                ->title('Tedarikçi bulunamadı')
                ->danger()
                ->send();

            return;
        }

        // Plenty'den contact bilgisini çek
        try {
            $contact = (new PlentyClient($supplier))->getContact((int) $contactId);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Plenty contact çekilemedi')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if (! $contact) {
            Notification::make()
                ->title("Plenty contact #{$contactId} bulunamadı")
                ->danger()
                ->send();

            return;
        }

        $companyName = trim((string) ($contact['accounts'][0]['companyName'] ?? ''));
        $email = trim((string) ($contact['email'] ?? ''));
        $fullName = trim(($contact['firstName'] ?? '').' '.($contact['lastName'] ?? ''));
        $displayName = $companyName !== '' ? $companyName : ($fullName !== '' ? $fullName : "Plenty #{$contactId}");

        // Tenant'ı bul veya yarat — slug stabilite için plenty contact id'sinden
        $slug = Str::slug($displayName).'-'.$contactId;
        $tenant = Tenant::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $displayName,
                'status' => 'active',
            ],
        );

        // tenant_supplier pivot — varsa güncelle yoksa yarat
        $tenant->suppliers()->syncWithoutDetaching([
            $supplier->id => [
                'plenty_contact_id' => (int) $contactId,
                'status' => 'active',
            ],
        ]);

        // Eğer pivot zaten varsa plenty_contact_id'yi güncellemeyi garanti et
        $tenant->suppliers()->updateExistingPivot($supplier->id, [
            'plenty_contact_id' => (int) $contactId,
            'status' => 'active',
        ]);

        // ShopifyStore'u tenant'a bağla
        $this->record->update(['tenant_id' => $tenant->id]);

        Notification::make()
            ->title('Eşleştirme kaydedildi')
            ->body("{$displayName} — Plenty contact #{$contactId} → {$supplier->name}")
            ->success()
            ->send();
    }
}
