<x-filament-panels::page>
    @php
        $products = $this->products;
        $shops = $this->availableShops;
    @endphp

    <div class="space-y-6">
        {{-- Übersicht --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-semibold uppercase text-gray-500">Hersteller</div>
                <div class="mt-1 text-base font-semibold text-gray-950 dark:text-white">
                    {{ $manufacturerName }}
                </div>
                <div class="mt-2 text-xs text-gray-500">
                    Plenty-ID: <span class="font-mono">#{{ $manufacturer }}</span>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-semibold uppercase text-gray-500">Pakete im Katalog</div>
                <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ $products->count() }}
                </div>
                <div class="mt-1 text-xs text-gray-500">Nur Paket-Artikel werden ausgespielt.</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-semibold uppercase text-gray-500">Freigegeben für</div>
                <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                    {{ count($selectedShops) }} <span class="text-sm font-normal text-gray-500">/ {{ $shops->count() }} Shops</span>
                </div>
            </div>
        </div>

        {{-- Freigabe-Form --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="mb-3">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">B2B-Freigaben</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Wählen Sie die Shopify-Shops aus, in die die Artikel dieses Herstellers übertragen werden dürfen.
                    Speichern Sie zuerst die Freigaben — anschließend können Sie alle Artikel mit einem Klick übertragen.
                </p>
            </div>

            @if($shops->isEmpty())
                <div class="rounded-lg bg-warning-50 p-4 text-sm text-warning-700 dark:bg-warning-950/40 dark:text-warning-300">
                    Keine zugeordneten Shopify-Shops verfügbar. Zuerst unter „Shopify-Shops" einen Shop mit einem B2B-Kontakt verknüpfen.
                </div>
            @else
                <div class="space-y-2">
                    @foreach($shops as $shop)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5">
                            <input
                                type="checkbox"
                                value="{{ $shop->id }}"
                                wire:model="selectedShops"
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                            <div class="flex-1 text-sm">
                                <div class="font-medium text-gray-950 dark:text-white">
                                    {{ $shop->tenant?->name ?? '— ohne Händler' }}
                                </div>
                                <div class="text-xs text-gray-500 font-mono">{{ $shop->name }}</div>
                            </div>
                            <span class="text-xs text-gray-400">Plenty #{{ $shop->plenty_contact_id }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        wire:click="saveShopPermissions"
                        wire:loading.attr="disabled"
                        wire:target="saveShopPermissions"
                        class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span wire:loading.remove wire:target="saveShopPermissions">Freigaben speichern</span>
                        <span wire:loading wire:target="saveShopPermissions">Wird gespeichert …</span>
                    </button>

                    <button
                        type="button"
                        wire:click="pushToSelectedShops"
                        wire:loading.attr="disabled"
                        wire:target="pushToSelectedShops"
                        @disabled(empty($selectedShops) || $products->isEmpty())
                        class="inline-flex items-center gap-2 rounded-md bg-success-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-success-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        <span wire:loading.remove wire:target="pushToSelectedShops">Alle {{ $products->count() }} Artikel an freigegebene Shops senden</span>
                        <span wire:loading wire:target="pushToSelectedShops">Wird übertragen …</span>
                    </button>
                </div>
            @endif
        </div>

        {{-- Artikel-Tabelle --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="border-b border-gray-200 px-4 py-2 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                Artikel dieses Herstellers ({{ $products->count() }})
            </div>
            @if($products->isEmpty())
                <div class="p-8 text-center text-sm text-gray-500">
                    Keine Paket-Artikel von diesem Hersteller im Katalog.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <th class="px-3 py-2">Bild</th>
                                <th class="px-3 py-2">Artikel</th>
                                <th class="px-3 py-2">Plenty-ID</th>
                                <th class="px-3 py-2">SKU</th>
                                <th class="px-3 py-2 text-right">Preis</th>
                                <th class="px-3 py-2 text-right">Bestand</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach($products as $p)
                                @php($v = $p->variations->first())
                                <tr>
                                    <td class="px-3 py-2">
                                        @if($v && $v->image_url)
                                            <img src="{{ $v->image_url }}" class="h-10 w-10 rounded object-cover" alt="">
                                        @else
                                            <div class="h-10 w-10 rounded bg-gray-100 dark:bg-white/5"></div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">
                                        {{ \Illuminate\Support\Str::limit($p->name, 80) }}
                                    </td>
                                    <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">
                                        #{{ $p->plenty_item_id }}
                                    </td>
                                    <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">
                                        {{ $v?->sku ?: '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono text-success-700 dark:text-success-400">
                                        @if($v?->retail_price)
                                            {{ number_format((float) $v->retail_price, 2, ',', '.') }} €
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">
                                        {{ $v?->stock_net ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
