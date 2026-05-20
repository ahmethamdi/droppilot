<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Mağaza özet kartı --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-950 dark:text-white">
                            {{ $shop['name'] ?? $record->name }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $shop['domain'] ?? $record->name }}
                            @if(!empty($shop['email'])) · {{ $shop['email'] }} @endif
                            @if(!empty($shop['country_name'])) · {{ $shop['country_name'] }} @endif
                            @if(!empty($shop['currency'])) · {{ $shop['currency'] }} @endif
                        </p>
                        @if($record->tenant)
                            <p class="mt-2 text-sm">
                                <span class="text-gray-500">Bağlı Bayi:</span>
                                <span class="font-semibold text-primary-600">{{ $record->tenant->name }}</span>
                            </p>
                        @else
                            <p class="mt-2 text-sm text-warning-600">
                                ⚠ Bu mağaza henüz bir bayiye bağlı değil.
                            </p>
                        @endif
                    </div>
                    <x-filament::button wire:click="refresh" icon="heroicon-o-arrow-path" color="gray">
                        Yenile
                    </x-filament::button>
                </div>
            </div>
        </div>

        @if($error)
            <div class="rounded-xl bg-danger-50 p-4 ring-1 ring-danger-200 dark:bg-danger-950/50 dark:ring-danger-800">
                <p class="text-sm font-medium text-danger-700 dark:text-danger-400">
                    Hata: {{ $error }}
                </p>
            </div>
        @endif

        {{-- Tab seçici --}}
        <div class="flex gap-2 border-b border-gray-200 dark:border-white/10">
            <button
                type="button"
                wire:click="setTab('orders')"
                @class([
                    'px-4 py-2 text-sm font-medium border-b-2 transition',
                    'border-primary-600 text-primary-600' => $activeTab === 'orders',
                    'border-transparent text-gray-500 hover:text-gray-700' => $activeTab !== 'orders',
                ])
            >
                Siparişler ({{ count($orders) }})
            </button>
            <button
                type="button"
                wire:click="setTab('customers')"
                @class([
                    'px-4 py-2 text-sm font-medium border-b-2 transition',
                    'border-primary-600 text-primary-600' => $activeTab === 'customers',
                    'border-transparent text-gray-500 hover:text-gray-700' => $activeTab !== 'customers',
                ])
            >
                Müşteriler ({{ count($customers) }})
            </button>
        </div>

        {{-- Siparişler tablosu --}}
        @if($activeTab === 'orders')
            <div class="fi-section overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                @if(empty($orders))
                    <div class="p-12 text-center">
                        <p class="text-gray-500">Bu mağazada henüz sipariş yok.</p>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <th class="px-4 py-3">Sipariş</th>
                                <th class="px-4 py-3">Müşteri</th>
                                <th class="px-4 py-3">Tutar</th>
                                <th class="px-4 py-3">Ödeme</th>
                                <th class="px-4 py-3">Kargo</th>
                                <th class="px-4 py-3">Ürün</th>
                                <th class="px-4 py-3">Tarih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach($orders as $order)
                                <tr class="text-sm">
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">
                                        {{ $order['name'] ?? '#' . ($order['id'] ?? '?') }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        @if(!empty($order['customer']))
                                            {{ trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? '')) ?: '—' }}
                                            <div class="text-xs text-gray-500">{{ $order['customer']['email'] ?? '' }}</div>
                                        @elseif(!empty($order['email']))
                                            {{ $order['email'] }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300">
                                        {{ $order['total_price'] ?? '0' }} {{ $order['currency'] ?? '' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                                            'bg-success-50 text-success-700' => ($order['financial_status'] ?? '') === 'paid',
                                            'bg-warning-50 text-warning-700' => in_array($order['financial_status'] ?? '', ['pending', 'authorized']),
                                            'bg-gray-50 text-gray-700' => empty($order['financial_status']),
                                        ])>
                                            {{ $order['financial_status'] ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                                            'bg-success-50 text-success-700' => ($order['fulfillment_status'] ?? '') === 'fulfilled',
                                            'bg-warning-50 text-warning-700' => ($order['fulfillment_status'] ?? '') === 'partial',
                                            'bg-gray-50 text-gray-700' => empty($order['fulfillment_status']),
                                        ])>
                                            {{ $order['fulfillment_status'] ?? 'unfulfilled' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {{ count($order['line_items'] ?? []) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($order['created_at'])->format('d.m.Y H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

        {{-- Müşteriler tablosu --}}
        @if($activeTab === 'customers')
            <div class="fi-section overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                @if(empty($customers))
                    <div class="p-12 text-center">
                        <p class="text-gray-500">Bu mağazada henüz müşteri yok.</p>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <th class="px-4 py-3">Ad Soyad</th>
                                <th class="px-4 py-3">E-posta</th>
                                <th class="px-4 py-3">Telefon</th>
                                <th class="px-4 py-3">Adres</th>
                                <th class="px-4 py-3">Sipariş</th>
                                <th class="px-4 py-3">Toplam</th>
                                <th class="px-4 py-3">Tags</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach($customers as $customer)
                                <tr class="text-sm">
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">
                                        {{ trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: '—' }}
                                        @if(!empty($customer['tax_exempt']))
                                            <span class="ml-1 inline-flex rounded-md bg-info-50 px-1.5 py-0.5 text-xs font-medium text-info-700">B2B</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {{ $customer['email'] ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {{ $customer['phone'] ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500">
                                        @if(!empty($customer['default_address']))
                                            {{ $customer['default_address']['city'] ?? '' }}
                                            @if(!empty($customer['default_address']['country']))
                                                , {{ $customer['default_address']['country'] }}
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {{ $customer['orders_count'] ?? 0 }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300">
                                        {{ $customer['total_spent'] ?? '0' }} {{ $customer['currency'] ?? '' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500">
                                        {{ $customer['tags'] ?? '' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
