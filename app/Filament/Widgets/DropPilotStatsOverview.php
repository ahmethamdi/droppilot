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
                    'label' => 'Shopify-Shops',
                    'value' => ShopifyStore::count(),
                    'description' => 'Verbundene Dropshipping-Shops',
                    'tone' => 'emerald',
                    'icon' => 'shopping-bag',
                ],
                [
                    'label' => 'Aktive Lieferanten',
                    'value' => Supplier::where('status', 'active')->count(),
                    'description' => 'PlentyMarkets-Verbindungen',
                    'tone' => 'sky',
                    'icon' => 'truck',
                ],
                [
                    'label' => 'Händler (Mandanten)',
                    'value' => Tenant::where('status', 'active')->count(),
                    'description' => 'Plenty-B2B-Kunden',
                    'tone' => 'amber',
                    'icon' => 'building-office',
                ],
                [
                    'label' => 'Benutzer',
                    'value' => User::count(),
                    'description' => 'Systembenutzer',
                    'tone' => 'violet',
                    'icon' => 'users',
                ],
            ],
        ];
    }
}
