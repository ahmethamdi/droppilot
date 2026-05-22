<div class="space-y-4">
    @if($error)
        <div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-950/50 dark:text-danger-400">
            Daten konnten nicht aus Plenty geladen werden: {{ $error }}
        </div>
    @elseif(empty($contacts))
        <div class="rounded-lg bg-warning-50 p-6 text-sm text-warning-700 dark:bg-warning-950/50 dark:text-warning-400">
            <p class="font-semibold">Keine B2B-Kunden gefunden.</p>
            <p class="mt-2 text-xs">
                @if(empty($supplier->b2b_class_ids))
                    Für diesen Lieferanten sind keine <strong>B2B-Plenty-Klassen-IDs</strong> hinterlegt. Im Bearbeitungsformular unter „B2B-Kundenklassen" hinzufügen (z. B. <code>12, 50</code>).
                @else
                    In den definierten Klassen ({{ implode(', ', $supplier->b2b_class_ids) }}) wurden keine Kunden mit ausgefülltem Firmennamen gefunden.
                @endif
            </p>
        </div>
    @else
        <div class="rounded-lg bg-info-50 p-3 text-xs text-info-700 dark:bg-info-950/50 dark:text-info-400">
            {{ count($contacts) }} B2B-Kunden geladen (Live-Daten aus Plenty).
            @if(! empty($supplier->b2b_class_ids))
                Filter: classId IN ({{ implode(', ', $supplier->b2b_class_ids) }}) + Firmenname nicht leer.
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="px-3 py-2">Plenty-ID</th>
                        <th class="px-3 py-2">Firma</th>
                        <th class="px-3 py-2">Ansprechpartner</th>
                        <th class="px-3 py-2">E-Mail</th>
                        <th class="px-3 py-2">Klasse</th>
                        <th class="px-3 py-2">Letzte Bestellung</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach($contacts as $c)
                        <tr>
                            <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-300">
                                #{{ $c['id'] }}
                            </td>
                            <td class="px-3 py-2 font-semibold text-gray-950 dark:text-white">
                                {{ $c['company'] }}
                            </td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                {{ trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: '—' }}
                            </td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                {{ $c['email'] ?: '—' }}
                            </td>
                            <td class="px-3 py-2 text-xs">
                                <span class="inline-flex rounded bg-gray-100 px-1.5 py-0.5 text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                    {{ $c['class_id'] }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500">
                                @if(! empty($c['last_order_at']))
                                    {{ \Carbon\Carbon::parse($c['last_order_at'])->format('d.m.Y') }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
