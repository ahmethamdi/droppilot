<x-filament-panels::page>
    @php
        $c = $this->contactData;
        $account = $c['accounts'][0] ?? [];
        $shop = $this->linkedShop;
        $shopifyData = $this->shopifyCustomers;
        $plentyOrders = $this->plentyOrders;
    @endphp

    <div class="space-y-6">
        {{-- Kontakt-Übersicht --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-semibold uppercase text-gray-500">Firma</div>
                <div class="mt-1 text-base font-semibold text-gray-950 dark:text-white">
                    {{ $account['companyName'] ?? '—' }}
                </div>
                <div class="mt-2 text-xs text-gray-500">
                    Klasse: <span class="font-mono">{{ $c['classId'] ?? '—' }}</span>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-semibold uppercase text-gray-500">Ansprechpartner</div>
                <div class="mt-1 text-base text-gray-950 dark:text-white">
                    {{ trim(($c['firstName'] ?? '').' '.($c['lastName'] ?? '')) ?: '—' }}
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    {{ $c['email'] ?? '—' }}
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-semibold uppercase text-gray-500">DropPilot-Verbindung</div>
                @if($shop)
                    <div class="mt-1 text-base font-semibold text-success-600 dark:text-success-400">
                        ✓ Shopify verbunden
                    </div>
                    <div class="mt-1 truncate text-xs text-gray-500" title="{{ $shop->name }}">
                        {{ $shop->name }}
                    </div>
                @else
                    <div class="mt-1 text-base font-semibold text-warning-600 dark:text-warning-400">
                        ⚠ Kein Shop zugeordnet
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Unter „Shopify-Shops" einen Shop diesem Kontakt zuordnen.
                    </div>
                @endif
            </div>
        </div>

        {{-- Tabs --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="flex gap-1 border-b border-gray-200 px-2 pt-2 dark:border-white/10">
                <button
                    type="button"
                    wire:click="$set('activeTab', 'shopify')"
                    class="rounded-t-md px-4 py-2 text-sm font-medium transition
                        {{ $activeTab === 'shopify'
                            ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300'
                            : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}"
                >
                    Shopify-Käufer
                    @if($shop && $shopifyData['ok'])
                        <span class="ml-1 inline-flex items-center rounded-full bg-gray-100 px-1.5 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-300">
                            {{ count($shopifyData['customers']) }}
                        </span>
                    @endif
                </button>
                <button
                    type="button"
                    wire:click="$set('activeTab', 'plenty')"
                    class="rounded-t-md px-4 py-2 text-sm font-medium transition
                        {{ $activeTab === 'plenty'
                            ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300'
                            : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}"
                >
                    Plenty-Aufträge
                    <span class="ml-1 inline-flex items-center rounded-full bg-gray-100 px-1.5 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-300">
                        {{ $plentyOrders->count() }}
                    </span>
                </button>
            </div>

            <div class="p-4">
                {{-- Shopify Käufer Tab --}}
                @if($activeTab === 'shopify')
                    @if(! $shop)
                        <div class="rounded-lg bg-warning-50 p-6 text-sm text-warning-700 dark:bg-warning-950/50 dark:text-warning-400">
                            <p class="font-semibold">Kein Shopify-Shop mit diesem B2B-Kunden verknüpft.</p>
                            <p class="mt-2 text-xs">
                                Damit Endkunden-Daten geladen werden können, muss der Händler seinen Shopify-Shop
                                über die Seite „Shopify-Shops" mit dem Plenty-Kontakt #{{ $contact }} verbinden.
                            </p>
                        </div>
                    @elseif(! $shopifyData['ok'])
                        <div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-950/50 dark:text-danger-400">
                            Daten konnten nicht aus Shopify geladen werden: {{ $shopifyData['error'] }}
                        </div>
                    @elseif(empty($shopifyData['customers']))
                        <div class="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500 dark:bg-white/5">
                            In diesem Shop sind noch keine Endkunden vorhanden.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                                <thead class="bg-gray-50 dark:bg-white/5">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                        <th class="px-3 py-2">Name</th>
                                        <th class="px-3 py-2">E-Mail</th>
                                        <th class="px-3 py-2">Telefon</th>
                                        <th class="px-3 py-2">Stadt</th>
                                        <th class="px-3 py-2">Bestellungen</th>
                                        <th class="px-3 py-2">Umsatz</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                    @foreach($shopifyData['customers'] as $cust)
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">
                                                {{ trim(($cust['first_name'] ?? '').' '.($cust['last_name'] ?? '')) ?: '—' }}
                                                @if(!empty($cust['tax_exempt']))
                                                    <span class="ml-1 inline-flex rounded bg-info-50 px-1.5 py-0.5 text-xs font-medium text-info-700">B2B</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $cust['email'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $cust['phone'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-xs text-gray-500">
                                                @if(!empty($cust['default_address']))
                                                    {{ $cust['default_address']['city'] ?? '—' }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $cust['orders_count'] ?? 0 }}</td>
                                            <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-300">
                                                {{ $cust['total_spent'] ?? '0' }} {{ $cust['currency'] ?? '' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">
                            {{ count($shopifyData['customers']) }} Endkunden (Live-Daten aus Shopify, 10 Min. gecacht).
                        </div>
                    @endif
                @endif

                {{-- Plenty-Aufträge Tab --}}
                @if($activeTab === 'plenty')
                    @if($plentyOrders->isEmpty())
                        <div class="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500 dark:bg-white/5">
                            Noch keine über DropPilot übertragenen Aufträge für diesen Kontakt.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                                <thead class="bg-gray-50 dark:bg-white/5">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                        <th class="px-3 py-2">Shopify</th>
                                        <th class="px-3 py-2">Plenty-Auftrag</th>
                                        <th class="px-3 py-2">Status</th>
                                        <th class="px-3 py-2">Positionen</th>
                                        <th class="px-3 py-2">Betrag</th>
                                        <th class="px-3 py-2">Übertragen</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                    @foreach($plentyOrders as $o)
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">
                                                {{ $o->shopify_order_name }}
                                            </td>
                                            <td class="px-3 py-2 font-mono text-success-700 dark:text-success-400">
                                                @if($o->plenty_order_id)
                                                    #{{ $o->plenty_order_id }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">
                                                @php
                                                    $tone = match($o->state) {
                                                        'success' => 'bg-success-50 text-success-700 dark:bg-success-950/40 dark:text-success-300',
                                                        'failed' => 'bg-danger-50 text-danger-700 dark:bg-danger-950/40 dark:text-danger-300',
                                                        default => 'bg-warning-50 text-warning-700 dark:bg-warning-950/40 dark:text-warning-300',
                                                    };
                                                    $label = match($o->state) {
                                                        'success' => '✓ Übertragen',
                                                        'failed' => '✗ Fehlgeschlagen',
                                                        default => '⏳ Ausstehend',
                                                    };
                                                @endphp
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $tone }}">
                                                    {{ $label }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $o->items_count ?? '—' }}</td>
                                            <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-300">
                                                @if($o->total)
                                                    {{ number_format((float) $o->total, 2, ',', '.') }} {{ $o->currency ?: 'EUR' }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-xs text-gray-500">
                                                {{ $o->pushed_at?->format('d.m.Y H:i') ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
