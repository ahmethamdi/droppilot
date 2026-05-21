<x-filament-widgets::widget>
    <div class="dp-stats-grid">
        @foreach($stats as $stat)
            <div class="dp-stat dp-stat-{{ $stat['tone'] }}">
                <div class="dp-stat-header">
                    <div class="dp-stat-icon">
                        @switch($stat['icon'])
                            @case('shopping-bag')
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                                    <line x1="3" y1="6" x2="21" y2="6"/>
                                    <path d="M16 10a4 4 0 01-8 0"/>
                                </svg>
                                @break
                            @case('truck')
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="1" y="3" width="15" height="13"/>
                                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                                    <circle cx="5.5" cy="18.5" r="2.5"/>
                                    <circle cx="18.5" cy="18.5" r="2.5"/>
                                </svg>
                                @break
                            @case('building-office')
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4" y="2" width="16" height="20" rx="2"/>
                                    <line x1="9" y1="6" x2="9" y2="6.01"/>
                                    <line x1="15" y1="6" x2="15" y2="6.01"/>
                                    <line x1="9" y1="10" x2="9" y2="10.01"/>
                                    <line x1="15" y1="10" x2="15" y2="10.01"/>
                                    <line x1="9" y1="14" x2="9" y2="14.01"/>
                                    <line x1="15" y1="14" x2="15" y2="14.01"/>
                                    <path d="M10 22v-4h4v4"/>
                                </svg>
                                @break
                            @case('users')
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 00-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 010 7.75"/>
                                </svg>
                                @break
                        @endswitch
                    </div>
                    <div class="dp-stat-trend">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                            <polyline points="17 6 23 6 23 12"/>
                        </svg>
                    </div>
                </div>
                <div class="dp-stat-body">
                    <div class="dp-stat-label">{{ $stat['label'] }}</div>
                    <div class="dp-stat-value">{{ number_format($stat['value']) }}</div>
                    <div class="dp-stat-desc">{{ $stat['description'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
