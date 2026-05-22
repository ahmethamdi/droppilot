<div class="space-y-4">
    @if($error)
        <div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-950/50 dark:text-danger-400">
            Daten konnten nicht aus Shopify geladen werden: {{ $error }}
        </div>
    @elseif(empty($orders))
        <div class="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500 dark:bg-white/5">
            In diesem Shop sind noch keine Bestellungen vorhanden.
        </div>
    @else
        @if(!$canPush)
            <div class="rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-950/50 dark:text-warning-400">
                <strong>Hinweis:</strong> Dieser Shop ist für die Übertragung an Plenty nicht vollständig konfiguriert. Im Bearbeitungsformular die Felder
                <em>Händler-Zuordnung</em> und <em>Verkaufspreistyp</em> ausfüllen — sonst ist die Schaltfläche
                „An Plenty senden" deaktiviert.
            </div>
        @endif
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="px-3 py-2">Bestellung</th>
                        <th class="px-3 py-2">Kunde</th>
                        <th class="px-3 py-2">Betrag</th>
                        <th class="px-3 py-2">Zahlung</th>
                        <th class="px-3 py-2">Versand</th>
                        <th class="px-3 py-2">Artikel</th>
                        <th class="px-3 py-2">Datum</th>
                        <th class="px-3 py-2">Plenty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach($orders as $order)
                        @php
                            $orderId = (int) ($order['id'] ?? 0);
                            $existing = $pushed[$orderId] ?? null;
                            $isSuccess = $existing && $existing->state === 'success' && $existing->plenty_order_id;
                            $isFailed = $existing && $existing->state === 'failed';
                        @endphp
                        <tr wire:key="shopify-order-{{ $orderId }}">
                            <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">
                                {{ $order['name'] ?? ('#' . $orderId) }}
                            </td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                @if(!empty($order['customer']))
                                    {{ trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? '')) ?: '—' }}
                                    <div class="text-xs text-gray-500">{{ $order['customer']['email'] ?? '' }}</div>
                                @else
                                    {{ $order['email'] ?? '—' }}
                                @endif
                            </td>
                            <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-300">
                                {{ $order['total_price'] ?? '0' }} {{ $order['currency'] ?? '' }}
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $order['financial_status'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ $order['fulfillment_status'] ?? 'nicht versendet' }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ count($order['line_items'] ?? []) }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">
                                {{ \Carbon\Carbon::parse($order['created_at'] ?? null)->format('d.m.Y H:i') }}
                            </td>
                            <td class="px-3 py-2">
                                @if($isSuccess)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-950/40 dark:text-success-400">
                                        ✓ Auftrag #{{ $existing->plenty_order_id }}
                                    </span>
                                @elseif($isFailed)
                                    <div class="flex flex-col gap-1">
                                        <button
                                            type="button"
                                            wire:click="pushShopifyOrderToPlenty({{ $storeId }}, {{ $orderId }})"
                                            wire:loading.attr="disabled"
                                            wire:target="pushShopifyOrderToPlenty({{ $storeId }}, {{ $orderId }})"
                                            @disabled(!$canPush)
                                            class="inline-flex items-center gap-1 rounded-md bg-warning-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-warning-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <span wire:loading.remove wire:target="pushShopifyOrderToPlenty({{ $storeId }}, {{ $orderId }})">↻ Erneut versuchen</span>
                                            <span wire:loading wire:target="pushShopifyOrderToPlenty({{ $storeId }}, {{ $orderId }})">Wird gesendet …</span>
                                        </button>
                                        @if($existing->error)
                                            <div class="text-[10px] text-danger-600 dark:text-danger-400" title="{{ $existing->error }}">
                                                {{ \Illuminate\Support\Str::limit($existing->error, 60) }}
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <button
                                        type="button"
                                        wire:click="pushShopifyOrderToPlenty({{ $storeId }}, {{ $orderId }})"
                                        wire:loading.attr="disabled"
                                        wire:target="pushShopifyOrderToPlenty({{ $storeId }}, {{ $orderId }})"
                                        @disabled(!$canPush)
                                        class="inline-flex items-center gap-1 rounded-md bg-primary-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <span wire:loading.remove wire:target="pushShopifyOrderToPlenty({{ $storeId }}, {{ $orderId }})">An Plenty senden</span>
                                        <span wire:loading wire:target="pushShopifyOrderToPlenty({{ $storeId }}, {{ $orderId }})">Wird gesendet …</span>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="text-xs text-gray-500">{{ count($orders) }} Bestellungen geladen (Live-Daten aus Shopify).</div>
    @endif
</div>
