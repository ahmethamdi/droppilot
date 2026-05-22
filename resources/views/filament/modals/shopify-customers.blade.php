<div class="space-y-4">
    @if($error)
        <div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-950/50 dark:text-danger-400">
            Daten konnten nicht aus Shopify geladen werden: {{ $error }}
        </div>
    @elseif(empty($customers))
        <div class="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500 dark:bg-white/5">
            In diesem Shop sind noch keine Kunden vorhanden.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">E-Mail</th>
                        <th class="px-3 py-2">Telefon</th>
                        <th class="px-3 py-2">Adresse</th>
                        <th class="px-3 py-2">Bestellungen</th>
                        <th class="px-3 py-2">Umsatz</th>
                        <th class="px-3 py-2">Tags</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach($customers as $customer)
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">
                                {{ trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: '—' }}
                                @if(!empty($customer['tax_exempt']))
                                    <span class="ml-1 inline-flex rounded bg-info-50 px-1.5 py-0.5 text-xs font-medium text-info-700">B2B</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $customer['email'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $customer['phone'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">
                                @if(!empty($customer['default_address']))
                                    {{ $customer['default_address']['city'] ?? '' }}
                                    @if(!empty($customer['default_address']['country'])), {{ $customer['default_address']['country'] }}@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $customer['orders_count'] ?? 0 }}</td>
                            <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-300">
                                {{ $customer['total_spent'] ?? '0' }} {{ $customer['currency'] ?? '' }}
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $customer['tags'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="text-xs text-gray-500">{{ count($customers) }} Kunden geladen (Live-Daten aus Shopify).</div>
    @endif
</div>
