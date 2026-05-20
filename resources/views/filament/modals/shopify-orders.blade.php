<div class="space-y-4">
    @if($error)
        <div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-950/50 dark:text-danger-400">
            Shopify'dan veri alınamadı: {{ $error }}
        </div>
    @elseif(empty($orders))
        <div class="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500 dark:bg-white/5">
            Bu mağazada henüz sipariş yok.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="px-3 py-2">Sipariş</th>
                        <th class="px-3 py-2">Müşteri</th>
                        <th class="px-3 py-2">Tutar</th>
                        <th class="px-3 py-2">Ödeme</th>
                        <th class="px-3 py-2">Kargo</th>
                        <th class="px-3 py-2">Ürün</th>
                        <th class="px-3 py-2">Tarih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach($orders as $order)
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">
                                {{ $order['name'] ?? ('#' . ($order['id'] ?? '?')) }}
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
                            <td class="px-3 py-2 text-xs">{{ $order['fulfillment_status'] ?? 'unfulfilled' }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ count($order['line_items'] ?? []) }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">
                                {{ \Carbon\Carbon::parse($order['created_at'] ?? null)->format('d.m.Y H:i') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="text-xs text-gray-500">{{ count($orders) }} sipariş yüklendi (canlı Shopify API).</div>
    @endif
</div>
