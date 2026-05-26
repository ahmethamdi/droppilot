<?php

namespace App\Filament\Pages;

use App\Models\ManufacturerShopPermission;
use App\Models\Product;
use App\Models\ShopifyStore;
use App\Models\Supplier;
use App\Services\Shopify\PushProductToShopify;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class HerstellerDetail extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'hersteller/{supplier}/{manufacturer}';

    protected static string $view = 'filament.pages.hersteller-detail';

    public int $supplier;

    public int $manufacturer;

    public ?Supplier $supplierModel = null;

    public string $manufacturerName = '';

    /** @var array<int, int> Seçili shopify_store_id'leri */
    public array $selectedShops = [];

    public function mount(int $supplier, int $manufacturer): void
    {
        $this->supplier = $supplier;
        $this->manufacturer = $manufacturer;

        $this->supplierModel = Supplier::find($supplier);
        if (! $this->supplierModel) {
            abort(404, 'Lieferant nicht gefunden.');
        }

        $first = Product::where('supplier_id', $supplier)
            ->where('manufacturer_id', $manufacturer)
            ->first();
        if (! $first) {
            abort(404, "Hersteller #{$manufacturer} hat keine Artikel.");
        }
        $this->manufacturerName = $first->manufacturer_name ?: "Hersteller #{$manufacturer}";

        $this->selectedShops = ManufacturerShopPermission::query()
            ->where('supplier_id', $supplier)
            ->where('manufacturer_id', $manufacturer)
            ->pluck('shopify_store_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function getTitle(): string
    {
        return $this->manufacturerName;
    }

    public function getHeading(): string
    {
        return $this->manufacturerName;
    }

    public function getSubheading(): ?string
    {
        return "Hersteller-ID #{$this->manufacturer} · {$this->supplierModel->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Zurück zur Liste')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(url('/admin/hersteller')),
        ];
    }

    public function getProductsProperty()
    {
        return Product::where('supplier_id', $this->supplier)
            ->where('manufacturer_id', $this->manufacturer)
            ->where('is_package', true)
            ->with(['variations' => fn ($q) => $q->limit(1)])
            ->orderBy('plenty_item_id')
            ->get();
    }

    /**
     * Bu supplier'ın tüm aktif/eşlenmiş Shopify shop'ları.
     */
    public function getAvailableShopsProperty()
    {
        return ShopifyStore::query()
            ->where('supplier_id', $this->supplier)
            ->whereNotNull('plenty_contact_id')
            ->whereNotNull('password')
            ->with('tenant')
            ->orderBy('id')
            ->get();
    }

    public function saveShopPermissions(): void
    {
        $selected = array_map('intval', $this->selectedShops);

        ManufacturerShopPermission::where('supplier_id', $this->supplier)
            ->where('manufacturer_id', $this->manufacturer)
            ->whereNotIn('shopify_store_id', $selected ?: [0])
            ->delete();

        foreach ($selected as $shopId) {
            ManufacturerShopPermission::updateOrCreate(
                [
                    'supplier_id' => $this->supplier,
                    'manufacturer_id' => $this->manufacturer,
                    'shopify_store_id' => $shopId,
                ],
                ['manufacturer_name' => $this->manufacturerName],
            );
        }

        Notification::make()
            ->title('Freigaben gespeichert')
            ->body(count($selected).' Shop(s) für '.$this->manufacturerName.' freigegeben.')
            ->success()
            ->send();
    }

    public function pushToSelectedShops(): void
    {
        $shopIds = $this->selectedShops;
        if (empty($shopIds)) {
            Notification::make()
                ->title('Keine Shops ausgewählt')
                ->body('Bitte zuerst mindestens einen Shop auswählen und Freigaben speichern.')
                ->warning()->send();

            return;
        }

        $products = $this->products;
        $shops = ShopifyStore::whereIn('id', $shopIds)->whereNotNull('password')->get();

        $pusher = new PushProductToShopify;
        $totalSuccess = 0;
        $totalSkipped = 0;
        $totalFailed = 0;
        $errors = [];

        foreach ($shops as $shop) {
            foreach ($products as $product) {
                try {
                    $result = $pusher($product, $shop);
                    if ($result->state === 'success') {
                        $totalSuccess++;
                    } elseif ($result->state === 'skipped') {
                        $totalSkipped++;
                    } else {
                        $totalFailed++;
                    }
                } catch (\Throwable $e) {
                    $totalFailed++;
                    $errors[] = "{$shop->name} / {$product->name}: ".mb_substr($e->getMessage(), 0, 100);
                }
            }
        }

        $body = "Erfolgreich: {$totalSuccess}  •  Übersprungen: {$totalSkipped}  •  Fehlgeschlagen: {$totalFailed}";
        if (! empty($errors)) {
            $body .= "\n\n".implode("\n", array_slice($errors, 0, 3));
        }

        Notification::make()
            ->title($totalFailed === 0 ? 'Push abgeschlossen' : 'Push teilweise erfolgreich')
            ->body($body)
            ->color($totalFailed === 0 ? 'success' : 'warning')
            ->persistent()
            ->send();
    }
}
