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
        $combined = $data['mapping_bayi'] ?? null;

        if (! $combined || ! str_contains($combined, ':')) {
            return; // eşleştirme yapılmamış
        }

        [$supplierId, $contactId] = explode(':', $combined, 2);

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

        // ShopifyStore'u tenant + supplier + plenty_contact_id ile direkt ilişkilendir
        // (Faz 4 sipariş routing'i için hızlı erişim — Tenant/pivot dolaşmaya gerek kalmasın)
        $this->record->update([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'plenty_contact_id' => (int) $contactId,
        ]);

        Notification::make()
            ->title('Eşleştirme kaydedildi')
            ->body("{$displayName} — Plenty contact #{$contactId} → {$supplier->name}")
            ->success()
            ->send();
    }
}
