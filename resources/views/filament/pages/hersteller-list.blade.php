<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Suche</label>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Herstellername …"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
            />
        </div>

        @php($rows = $this->rows)

        @if(empty($rows))
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                <p class="font-semibold text-gray-700 dark:text-gray-200">Keine Hersteller gefunden</p>
                <p class="mt-1 text-xs">
                    Zuerst den Katalog aus Plenty synchronisieren (Menü „Artikel → Katalog aus Plenty synchronisieren").
                </p>
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="border-b border-gray-200 px-4 py-2 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                    {{ count($rows) }} Hersteller (nur Hersteller mit Paket-Artikeln).
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <th class="px-4 py-3">Hersteller</th>
                                <th class="px-4 py-3">Plenty-ID</th>
                                <th class="px-4 py-3">Lieferant</th>
                                <th class="px-4 py-3 text-right">Artikel</th>
                                <th class="px-4 py-3 text-right">B2B-Freigaben</th>
                                <th class="px-4 py-3 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach($rows as $r)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-4 py-3 font-semibold text-gray-950 dark:text-white">
                                        {{ $r['manufacturer_name'] }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">
                                        #{{ $r['manufacturer_id'] }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500">
                                        {{ $r['supplier_name'] ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">
                                        {{ $r['product_count'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($r['permission_count'] > 0)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-950/40 dark:text-success-300">
                                                ✓ {{ $r['permission_count'] }} Shops
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                                — keine
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a
                                            href="{{ url('/admin/hersteller/'.$r['supplier_id'].'/'.$r['manufacturer_id']) }}"
                                            class="inline-flex items-center gap-1 rounded-md bg-primary-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700"
                                        >
                                            Öffnen
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
