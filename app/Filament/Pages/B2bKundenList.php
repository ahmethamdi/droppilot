<?php

namespace App\Filament\Pages;

use App\Models\Supplier;
use App\Services\Plenty\PlentyClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class B2bKundenList extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Mandanten';

    protected static ?string $navigationLabel = 'B2B-Kunden';

    protected static ?string $title = 'B2B-Kunden';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'b2b-kunden';

    protected static string $view = 'filament.pages.b2b-kunden-list';

    public string $search = '';

    public ?int $supplierFilter = null;

    public bool $refreshing = false;

    public function mount(): void
    {
        if (! $this->supplierFilter) {
            $this->supplierFilter = Supplier::where('status', 'active')->orderBy('id')->value('id');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Liste aktualisieren')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function () {
                    foreach ($this->cacheKeys() as $key) {
                        Cache::forget($key);
                    }

                    Notification::make()
                        ->title('Cache geleert — Liste wird neu geladen')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<int, array{id:int, company:string, email:string, first_name:string, last_name:string, class_id:int, last_order_at:?string, supplier_id:int, supplier_name:string}>
     */
    public function getRowsProperty(): array
    {
        $suppliers = Supplier::where('status', 'active')->orderBy('id')->get();
        if ($this->supplierFilter) {
            $suppliers = $suppliers->where('id', $this->supplierFilter);
        }

        $all = [];
        foreach ($suppliers as $supplier) {
            $cacheKey = $this->cacheKeyForSupplier($supplier->id);
            $rows = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($supplier) {
                try {
                    return (new PlentyClient($supplier))->listB2BContacts(1500);
                } catch (\Throwable $e) {
                    return [];
                }
            });

            foreach ($rows as $r) {
                $r['supplier_id'] = $supplier->id;
                $r['supplier_name'] = $supplier->name;
                $all[] = $r;
            }
        }

        $needle = mb_strtolower(trim($this->search));
        if ($needle !== '') {
            $all = array_filter($all, function ($r) use ($needle) {
                $hay = mb_strtolower(
                    ($r['company'] ?? '').' '.
                    ($r['email'] ?? '').' '.
                    ($r['first_name'] ?? '').' '.
                    ($r['last_name'] ?? '').' '.
                    ($r['id'] ?? '')
                );

                return str_contains($hay, $needle);
            });
        }

        usort($all, fn ($a, $b) => strcasecmp($a['company'] ?? '', $b['company'] ?? ''));

        return array_values($all);
    }

    public function getSupplierOptionsProperty(): array
    {
        return Supplier::where('status', 'active')
            ->orderBy('id')
            ->pluck('name', 'id')
            ->all();
    }

    protected function cacheKeyForSupplier(int $supplierId): string
    {
        return "plenty:b2b_contacts:supplier:{$supplierId}";
    }

    protected function cacheKeys(): array
    {
        return Supplier::where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => $this->cacheKeyForSupplier($id))
            ->all();
    }
}
