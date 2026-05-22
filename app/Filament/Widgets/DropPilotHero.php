<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DropPilotHero extends Widget
{
    protected static string $view = 'filament.widgets.drop-pilot-hero';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $hour = (int) now()->format('H');
        $greeting = match (true) {
            $hour < 5 => 'Gute Nacht',
            $hour < 11 => 'Guten Morgen',
            $hour < 18 => 'Guten Tag',
            default => 'Guten Abend',
        };

        return [
            'greeting' => $greeting,
            'name' => Auth::user()?->name ?? 'Admin',
        ];
    }
}
