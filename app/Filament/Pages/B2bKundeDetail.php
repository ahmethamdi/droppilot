<?php

namespace App\Filament\Pages;

use App\Models\PlentyOrder;
use App\Models\ShopifyStore;
use App\Models\Supplier;
use App\Services\Plenty\PlentyClient;
use App\Services\Shopify\ShopifyClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class B2bKundeDetail extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'b2b-kunden/{supplier}/{contact}';

    protected static string $view = 'filament.pages.b2b-kunde-detail';

    public int $supplier;

    public int $contact;

    public ?array $contactData = null;

    public ?Supplier $supplierModel = null;

    public string $activeTab = 'shopify';

    public function mount(int $supplier, int $contact): void
    {
        $this->supplier = $supplier;
        $this->contact = $contact;

        $this->supplierModel = Supplier::find($supplier);
        if (! $this->supplierModel) {
            abort(404, 'Lieferant nicht gefunden.');
        }

        $this->contactData = Cache::remember(
            "plenty:contact:{$supplier}:{$contact}",
            now()->addMinutes(10),
            function () {
                try {
                    return (new PlentyClient($this->supplierModel))->getContact($this->contact);
                } catch (\Throwable $e) {
                    return null;
                }
            },
        );

        if (! $this->contactData) {
            abort(404, "Plenty-Kontakt #{$contact} nicht gefunden.");
        }
    }

    public function getTitle(): string
    {
        $company = $this->contactData['accounts'][0]['companyName'] ?? '';
        $name = trim(($this->contactData['firstName'] ?? '').' '.($this->contactData['lastName'] ?? ''));

        return $company ?: ($name ?: "Plenty-Kontakt #{$this->contact}");
    }

    public function getHeading(): string
    {
        return $this->getTitle();
    }

    public function getSubheading(): ?string
    {
        $parts = [];
        $parts[] = "Plenty-ID #{$this->contact}";
        $parts[] = $this->supplierModel->name;
        $email = $this->contactData['email'] ?? '';
        if ($email) {
            $parts[] = $email;
        }

        return implode(' · ', $parts);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Zurück zur Liste')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(url('/admin/b2b-kunden')),

            Action::make('refresh')
                ->label('Daten aktualisieren')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function () {
                    Cache::forget("plenty:contact:{$this->supplier}:{$this->contact}");
                    Cache::forget($this->shopifyCustomersCacheKey());
                    Cache::forget($this->plentyCustomersCacheKey());

                    Notification::make()
                        ->title('Cache geleert')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Bu Plenty contact'a bağlı ShopifyStore var mı?
     */
    public function getLinkedShopProperty(): ?ShopifyStore
    {
        return ShopifyStore::where('supplier_id', $this->supplier)
            ->where('plenty_contact_id', $this->contact)
            ->whereNotNull('password')
            ->first();
    }

    /**
     * Shopify shop'un kendi son tüketici müşterileri (10 dk cache).
     *
     * @return array{ok: bool, error: ?string, customers: array}
     */
    public function getShopifyCustomersProperty(): array
    {
        $shop = $this->linkedShop;
        if (! $shop) {
            return ['ok' => false, 'error' => null, 'customers' => []];
        }

        return Cache::remember(
            $this->shopifyCustomersCacheKey(),
            now()->addMinutes(10),
            function () use ($shop) {
                try {
                    $customers = (new ShopifyClient($shop))->getCustomers(100);

                    return ['ok' => true, 'error' => null, 'customers' => $customers];
                } catch (\Throwable $e) {
                    return ['ok' => false, 'error' => $e->getMessage(), 'customers' => []];
                }
            },
        );
    }

    /**
     * DropPilot üzerinden bu B2B'ye düşmüş Plenty-Auftrag'lar.
     */
    public function getPlentyOrdersProperty()
    {
        return PlentyOrder::query()
            ->where('supplier_id', $this->supplier)
            ->where('plenty_contact_id', $this->contact)
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    /**
     * B2B müşterimizin Plenty'deki son tüketici alıcıları (Plenty Auftrag'ların
     * Lieferadresse'sinden çıkarılmış).
     *
     * @return array{ok: bool, error: ?string, customers: array}
     */
    public function getPlentyCustomersProperty(): array
    {
        return Cache::remember(
            $this->plentyCustomersCacheKey(),
            now()->addMinutes(15),
            function () {
                try {
                    $customers = (new PlentyClient($this->supplierModel))
                        ->getEndCustomersByContact($this->contact, 500);

                    return ['ok' => true, 'error' => null, 'customers' => $customers];
                } catch (\Throwable $e) {
                    return ['ok' => false, 'error' => $e->getMessage(), 'customers' => []];
                }
            },
        );
    }

    protected function shopifyCustomersCacheKey(): string
    {
        return "shopify:customers:{$this->supplier}:{$this->contact}";
    }

    protected function plentyCustomersCacheKey(): string
    {
        return "plenty:end_customers:{$this->supplier}:{$this->contact}";
    }
}
