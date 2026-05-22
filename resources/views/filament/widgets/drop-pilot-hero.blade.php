<x-filament-widgets::widget>
    <div class="dp-hero">
        <div class="dp-hero-blob dp-hero-blob-1"></div>
        <div class="dp-hero-blob dp-hero-blob-2"></div>
        <div class="dp-hero-grid"></div>

        <div class="dp-hero-content">
            <div class="dp-hero-text">
                <p class="dp-hero-eyebrow">{{ $greeting }}, {{ $name }}</p>
                <h2 class="dp-hero-title">Willkommen im DropPilot-Kontrollzentrum</h2>
                <p class="dp-hero-subtitle">
                    Shopify-Shops mit PlentyMarkets-B2B-Kunden verknüpfen, Bestellabläufe automatisieren und den Katalog mit einem Klick übertragen.
                </p>
                <div class="dp-hero-actions">
                    <a href="{{ url('/admin/shopify-stores') }}" class="dp-hero-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        Shopify-Shops
                    </a>
                    <a href="{{ url('/admin/suppliers') }}" class="dp-hero-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7l9-4 9 4M3 7v10l9 4 9-4V7M3 7l9 4 9-4M12 11v10"/></svg>
                        Lieferanten
                    </a>
                    <a href="{{ url('/admin/tenants') }}" class="dp-hero-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2m-16 0H3m4-3h2m0-3h2m-2-3h2"/></svg>
                        Händler
                    </a>
                </div>
            </div>
            <div class="dp-hero-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2L4 7v10l8 5 8-5V7l-8-5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7l8 5 8-5"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 22V12"/>
                </svg>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
