<?php

namespace App\Filament\Widgets;

use App\Models\ShopifyStore;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Filament\Widgets\Widget;

class DropPilotStatsOverview extends Widget
{
    protected static string $view = 'filament.widgets.drop-pilot-stats';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'stats' => [
                [
                    'label' => 'Shopify Mağazaları',
                    'value' => ShopifyStore::count(),
                    'description' => 'Bağlı dropshipping mağazaları',
                    'tone' => 'emerald',
                    'icon' => 'shopping-bag',
                ],
                [
                    'label' => 'Aktif Tedarikçi',
                    'value' => Supplier::where('status', 'active')->count(),
                    'description' => 'PlentyMarkets bağlantıları',
                    'tone' => 'sky',
                    'icon' => 'truck',
                ],
                [
                    'label' => 'Bayi (Tenant)',
                    'value' => Tenant::where('status', 'active')->count(),
                    'description' => 'Plenty B2B müşterileri',
                    'tone' => 'amber',
                    'icon' => 'building-office',
                ],
                [
                    'label' => 'Kullanıcı',
                    'value' => User::count(),
                    'description' => 'Sistem kullanıcıları',
                    'tone' => 'violet',
                    'icon' => 'users',
                ],
            ],
        ];
    }
}
