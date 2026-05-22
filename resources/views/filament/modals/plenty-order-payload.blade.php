<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <div class="text-xs font-semibold uppercase text-gray-500">Shopify-Bestellung</div>
            <div class="font-mono">{{ $order->shopify_order_name }}</div>
            <div class="text-xs text-gray-500">ID: {{ $order->shopify_order_id }}</div>
        </div>
        <div>
            <div class="text-xs font-semibold uppercase text-gray-500">Plenty-Auftrag</div>
            <div class="font-mono">{{ $order->plenty_order_id ? '#' . $order->plenty_order_id : '—' }}</div>
            <div class="text-xs text-gray-500">Kontakt: #{{ $order->plenty_contact_id }} / Adresse: {{ $order->plenty_address_id ?? '—' }}</div>
        </div>
        <div>
            <div class="text-xs font-semibold uppercase text-gray-500">Status</div>
            <div class="font-mono">{{ $order->state }} ({{ $order->attempts }} Versuche)</div>
        </div>
        <div>
            <div class="text-xs font-semibold uppercase text-gray-500">Übertragen am</div>
            <div class="font-mono">{{ $order->pushed_at?->format('d.m.Y H:i') ?? '—' }}</div>
        </div>
    </div>

    @if($order->error)
        <div>
            <div class="text-xs font-semibold uppercase text-danger-600">Fehlermeldung</div>
            <pre class="mt-1 max-h-40 overflow-auto rounded-lg bg-danger-50 p-3 text-xs text-danger-700 dark:bg-danger-950/40 dark:text-danger-300">{{ $order->error }}</pre>
        </div>
    @endif

    <div>
        <div class="text-xs font-semibold uppercase text-gray-500">Gesendetes Payload</div>
        <pre class="mt-1 max-h-96 overflow-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100">{{ json_encode($order->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>

    @if(!empty($order->response))
        <div>
            <div class="text-xs font-semibold uppercase text-gray-500">Plenty-Antwort</div>
            <pre class="mt-1 max-h-96 overflow-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100">{{ json_encode($order->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @endif
</div>
