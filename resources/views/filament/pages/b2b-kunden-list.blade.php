<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Suche</label>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Firma, E-Mail, Name oder Plenty-ID …"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Lieferant</label>
                    <select
                        wire:model.live="supplierFilter"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    >
                        <option value="">Alle aktiven Lieferanten</option>
                        @foreach($this->supplierOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @php($rows = $this->rows)

        @if(empty($rows))
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-sm dark:bg-white/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2m-16 0H3"/>
                    </svg>
                </div>
                <p class="font-semibold text-gray-700 dark:text-gray-200">Keine B2B-Kunden gefunden</p>
                <p class="mt-1 text-xs">
                    @if(!empty(trim($search)))
                        Suchanfrage „{{ $search }}" lieferte keine Treffer.
                    @else
                        Prüfen Sie die Lieferanten-Konfiguration und die hinterlegten B2B-Klassen-IDs.
                    @endif
                </p>
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="border-b border-gray-200 px-4 py-2 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                    {{ count($rows) }} B2B-Kunden geladen (Live-Daten aus Plenty, 15 Min. gecacht).
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <th class="px-4 py-3">Firma</th>
                                <th class="px-4 py-3">Ansprechpartner</th>
                                <th class="px-4 py-3">E-Mail</th>
                                <th class="px-4 py-3">Plenty-ID</th>
                                <th class="px-4 py-3">Klasse</th>
                                <th class="px-4 py-3">Lieferant</th>
                                <th class="px-4 py-3">Letzte Bestellung</th>
                                <th class="px-4 py-3 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach($rows as $r)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-4 py-3 font-semibold text-gray-950 dark:text-white">
                                        {{ $r['company'] ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {{ trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')) ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {{ $r['email'] ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">
                                        #{{ $r['id'] }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                            {{ $r['class_id'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500">
                                        {{ $r['supplier_name'] }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500">
                                        @if(!empty($r['last_order_at']))
                                            {{ \Carbon\Carbon::parse($r['last_order_at'])->format('d.m.Y') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a
                                            href="{{ url('/admin/b2b-kunden/'.$r['supplier_id'].'/'.$r['id']) }}"
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
