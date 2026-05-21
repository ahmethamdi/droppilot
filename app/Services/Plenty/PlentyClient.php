<?php

namespace App\Services\Plenty;

use App\Models\Supplier;
use App\Models\SupplierReference;
use App\Models\SkuLookup;
use App\Services\Plenty\Requests\GetContactAddressesRequest;
use App\Services\Plenty\Requests\GetContactClassesRequest;
use App\Services\Plenty\Requests\GetContactRequest;
use App\Services\Plenty\Requests\GetContactsRequest;
use App\Services\Plenty\Requests\GetOrderStatusesRequest;
use App\Services\Plenty\Requests\GetReferrersRequest;
use App\Services\Plenty\Requests\GetSalesPricesRequest;
use App\Services\Plenty\Requests\GetVariationBySkuRequest;
use App\Services\Plenty\Requests\GetWarehousesRequest;
use App\Services\Plenty\Requests\LoginRequest;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class PlentyClient
{
    public function __construct(protected Supplier $supplier) {}

    public function connector(): PlentyConnector
    {
        return new PlentyConnector(
            baseUrl: $this->supplier->plenty_base_url ?? throw new RuntimeException('Supplier has no Plenty base URL.'),
            accessToken: $this->accessToken(),
        );
    }

    public function accessToken(): string
    {
        return Cache::get($this->tokenCacheKey()) ?? $this->login();
    }

    public function login(): string
    {
        $username = $this->supplier->plenty_login_user;
        $password = $this->supplier->plenty_login_password;

        if (! $username || ! $password) {
            throw new RuntimeException('Supplier is missing Plenty credentials.');
        }

        $bareConnector = new PlentyConnector($this->supplier->plenty_base_url);
        $response = $bareConnector->send(new LoginRequest($username, $password));

        if ($response->failed()) {
            throw new RuntimeException(
                'Plenty login failed (HTTP ' . $response->status() . '): ' . $response->body(),
            );
        }

        $data = $response->json();
        $token = $data['access_token'] ?? $data['accessToken'] ?? null;
        $expiresIn = (int) ($data['expires_in'] ?? $data['expiresIn'] ?? 86400);

        if (! $token) {
            throw new RuntimeException('Plenty login response missing access_token.');
        }

        Cache::put($this->tokenCacheKey(), $token, now()->addSeconds(max(60, $expiresIn - 300)));

        return $token;
    }

    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    protected function tokenCacheKey(): string
    {
        return "plenty:token:supplier:{$this->supplier->id}";
    }

    public function testConnection(): array
    {
        try {
            $this->forgetToken();
            $token = $this->login();

            return [
                'ok' => true,
                'message' => 'Plenty login successful.',
                'meta' => ['token_preview' => substr($token, 0, 8) . '…'],
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'meta' => []];
        }
    }

    /** Tek bir contact'ı ID ile çek. Bulunamazsa null. */
    public function getContact(int $contactId): ?array
    {
        $response = $this->connector()->send(new GetContactRequest($contactId));

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        return $response->json();
    }

    /** Bir contact'ın adreslerini çek (typeId: 1=billing, 2=shipping, null=hepsi). */
    public function getContactAddresses(int $contactId, ?int $typeId = null): array
    {
        $response = $this->connector()->send(new GetContactAddressesRequest($contactId, $typeId));
        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * Filament UI için contact doğrulama özeti.
     * Dönüş: ['ok' => bool, 'message' => string, 'contact' => array|null, 'billing_addresses' => array]
     */
    public function verifyContact(int $contactId): array
    {
        try {
            $contact = $this->getContact($contactId);

            if (! $contact) {
                return [
                    'ok' => false,
                    'message' => "Contact #{$contactId} Plenty'de bulunamadı.",
                    'contact' => null,
                    'billing_addresses' => [],
                ];
            }

            $billing = $this->getContactAddresses($contactId, 1); // typeId 1 = billing

            $company = $contact['accounts'][0]['companyName'] ?? null;
            $name = trim(($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? '')) ?: ($contact['fullName'] ?? 'unknown');
            $email = $contact['email'] ?? '-';
            $plentyId = $contact['plentyId'] ?? null;

            return [
                'ok' => true,
                'message' => "✓ {$name}" . ($company ? " ({$company})" : '') . " | {$email} | Mandant #{$plentyId}",
                'contact' => $contact,
                'billing_addresses' => $billing,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'contact' => null,
                'billing_addresses' => [],
            ];
        }
    }

    /** Plenty'den referans verileri çekip DB'de cache'le. Dönüş: ['kind' => count, ...] */
    public function syncReferences(): array
    {
        return [
            'referrer' => $this->syncReferrers(),
            'warehouse' => $this->syncWarehouses(),
            'order_status' => $this->syncOrderStatuses(),
            'sales_price' => $this->syncSalesPrices(),
        ];
    }

    public function syncReferrers(): int
    {
        $response = $this->connector()->send(new GetReferrersRequest);
        $response->throw();

        $count = 0;
        foreach ($response->json() ?? [] as $item) {
            SupplierReference::updateOrCreate(
                [
                    'supplier_id' => $this->supplier->id,
                    'kind' => SupplierReference::KIND_REFERRER,
                    'external_id' => (string) $item['id'],
                ],
                [
                    'label' => $item['name'] ?? $item['backendName'] ?? (string) $item['id'],
                    'payload' => $item,
                    'synced_at' => now(),
                ],
            );
            $count++;
        }

        return $count;
    }

    public function syncWarehouses(): int
    {
        $response = $this->connector()->send(new GetWarehousesRequest);
        $response->throw();

        $count = 0;
        foreach ($response->json() ?? [] as $item) {
            SupplierReference::updateOrCreate(
                [
                    'supplier_id' => $this->supplier->id,
                    'kind' => SupplierReference::KIND_WAREHOUSE,
                    'external_id' => (string) $item['id'],
                ],
                [
                    'label' => $item['name'] ?? "Warehouse #{$item['id']}",
                    'payload' => $item,
                    'synced_at' => now(),
                ],
            );
            $count++;
        }

        return $count;
    }

    public function syncOrderStatuses(): int
    {
        $count = 0;
        $page = 1;

        while (true) {
            $response = $this->connector()->send(new GetOrderStatusesRequest($page, 250));
            $response->throw();

            $body = $response->json();
            $entries = $body['entries'] ?? [];

            foreach ($entries as $item) {
                $label = $item['names']['de'] ?? $item['names']['en'] ?? (string) $item['statusId'];
                SupplierReference::updateOrCreate(
                    [
                        'supplier_id' => $this->supplier->id,
                        'kind' => SupplierReference::KIND_ORDER_STATUS,
                        'external_id' => (string) $item['statusId'],
                    ],
                    [
                        'label' => $label,
                        'payload' => $item,
                        'synced_at' => now(),
                    ],
                );
                $count++;
            }

            if (! empty($body['isLastPage']) || empty($entries)) {
                break;
            }
            $page++;
            if ($page > 20) {
                break;
            }
        }

        return $count;
    }

    public function syncSalesPrices(): int
    {
        $count = 0;
        $page = 1;

        while (true) {
            $response = $this->connector()->send(new GetSalesPricesRequest($page, 100));
            $response->throw();

            $body = $response->json();
            $entries = $body['entries'] ?? [];

            foreach ($entries as $item) {
                $label = collect($item['names'] ?? [])
                    ->firstWhere('lang', 'de')['nameInternal']
                    ?? collect($item['names'] ?? [])->firstWhere('lang', 'en')['nameInternal']
                    ?? "SalesPrice #{$item['id']}";

                SupplierReference::updateOrCreate(
                    [
                        'supplier_id' => $this->supplier->id,
                        'kind' => SupplierReference::KIND_SALES_PRICE,
                        'external_id' => (string) $item['id'],
                    ],
                    [
                        'label' => "#{$item['id']} — {$label}",
                        'payload' => $item,
                        'synced_at' => now(),
                    ],
                );
                $count++;
            }

            if (! empty($body['isLastPage']) || empty($entries)) {
                break;
            }
            $page++;
            if ($page > 20) {
                break;
            }
        }

        return $count;
    }

    /**
     * Tek bir SKU'yu Plenty'de ara. Cache'i kullanır; force=true ile bypass.
     *
     * Dönüş şekli (her zaman):
     *   ['found' => bool, 'sku' => string, 'variation_id' => ?int, 'item_id' => ?int,
     *    'name' => ?string, 'price' => ?float, 'price_source_id' => ?int,
     *    'stock_net' => ?float, 'is_active' => bool, 'cache' => SkuLookup]
     */
    public function lookupSku(string $sku, bool $force = false): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            throw new \InvalidArgumentException('SKU is empty.');
        }

        $cache = SkuLookup::where('supplier_id', $this->supplier->id)
            ->where('sku', $sku)
            ->first();

        $isFresh = $cache && $cache->synced_at && $cache->synced_at->gt(now()->subMinutes(10));
        if ($cache && $isFresh && ! $force) {
            return $this->formatLookup($cache);
        }

        $response = $this->connector()->send(new GetVariationBySkuRequest($sku));
        $response->throw();

        $entries = $response->json()['entries'] ?? [];
        $variation = $entries[0] ?? null;

        if (! $variation) {
            // Negative cache — Plenty'de yok demek
            $cache = SkuLookup::updateOrCreate(
                ['supplier_id' => $this->supplier->id, 'sku' => $sku],
                [
                    'found' => false,
                    'plenty_variation_id' => null,
                    'plenty_item_id' => null,
                    'name' => null,
                    'supplier_price' => null,
                    'supplier_price_source_id' => null,
                    'stock_net' => null,
                    'is_active' => false,
                    'payload' => null,
                    'synced_at' => now(),
                ],
            );

            return $this->formatLookup($cache);
        }

        $priceSourceId = (int) ($this->supplier->default_sales_price_id ?? 0);
        $price = null;
        if ($priceSourceId) {
            foreach ($variation['variationSalesPrices'] ?? [] as $vsp) {
                if ((int) ($vsp['salesPriceId'] ?? 0) === $priceSourceId) {
                    $price = (float) $vsp['price'];
                    break;
                }
            }
        }

        // Stock toplamı (tüm depolardan, netStock)
        $stockNet = null;
        if (isset($variation['stock']) && is_array($variation['stock'])) {
            $stockNet = collect($variation['stock'])->sum('netStock');
        }

        $cache = SkuLookup::updateOrCreate(
            ['supplier_id' => $this->supplier->id, 'sku' => $sku],
            [
                'found' => true,
                'plenty_variation_id' => (int) $variation['id'],
                'plenty_item_id' => (int) ($variation['itemId'] ?? 0) ?: null,
                'name' => $variation['name'] ?? null,
                'supplier_price' => $price,
                'supplier_price_source_id' => $price !== null ? $priceSourceId : null,
                'stock_net' => $stockNet,
                'is_active' => (bool) ($variation['isActive'] ?? false),
                'payload' => $variation,
                'synced_at' => now(),
            ],
        );

        return $this->formatLookup($cache);
    }

    protected function formatLookup(SkuLookup $cache): array
    {
        return [
            'found' => (bool) $cache->found,
            'sku' => $cache->sku,
            'variation_id' => $cache->plenty_variation_id,
            'item_id' => $cache->plenty_item_id,
            'name' => $cache->name,
            'price' => $cache->supplier_price !== null ? (float) $cache->supplier_price : null,
            'price_source_id' => $cache->supplier_price_source_id,
            'stock_net' => $cache->stock_net !== null ? (float) $cache->stock_net : null,
            'is_active' => (bool) $cache->is_active,
            'cache' => $cache,
        ];
    }

    /**
     * Plenty'de tanımlı tüm contact class'larını ID -> isim olarak döndür.
     *
     * Endpoint: GET /rest/accounts/contacts/classes
     * Döndüğü ham veri: {"1":"Händler (B2B)", "2":"B2C Kunde", "5":"B2B Shop Standard", ...}
     *
     * @return array<int, string>  classId => name
     */
    public function listContactClasses(): array
    {
        $response = $this->connector()->send(new GetContactClassesRequest);
        $response->throw();

        $raw = $response->json();
        if (! \is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $id => $name) {
            $out[(int) $id] = trim((string) $name);
        }
        ksort($out);

        return $out;
    }

    /**
     * B2B müşterileri listele.
     *
     * Strateji:
     * - Supplier'da b2b_class_ids DOLUYSA: o class'ları sırayla çek (HIZLI, sadece B2B class'lar).
     *   companyName check yine yapılır (false-positive elemek için).
     * - Boşsa: ilk N sayfayı tarayıp companyName non-empty olanları döndür (YAVAŞ — fallback).
     *
     * @param  int  $limit  Maks. dönecek müşteri sayısı (200 default).
     * @return list<array{id:int,first_name:string,last_name:string,company:string,email:string,class_id:int,created_at:string}>
     */
    public function listB2BContacts(int $limit = 1000): array
    {
        $classIds = $this->supplier->b2b_class_ids;
        $results = [];

        if (\is_array($classIds) && \count($classIds) > 0) {
            // FAST PATH: her classId için ADIL quota — toplam limit / class sayısı.
            // Böylece bir class limit'i tüketip diğerlerini engellemez.
            $perClassQuota = max(100, intdiv($limit, \count($classIds)) + 50);

            foreach ($classIds as $classId) {
                $perClassCount = 0;
                $page = 1;
                while ($perClassCount < $perClassQuota && \count($results) < $limit) {
                    $response = $this->connector()->send(new GetContactsRequest($page, 250, (int) $classId));
                    $response->throw();
                    $body = $response->json();
                    $entries = $body['entries'] ?? [];

                    foreach ($entries as $e) {
                        $company = $e['accounts'][0]['companyName'] ?? '';
                        if ($company === '') {
                            continue;
                        }
                        $results[] = $this->formatContact($e);
                        $perClassCount++;
                        if ($perClassCount >= $perClassQuota || \count($results) >= $limit) {
                            break;
                        }
                    }

                    if (! empty($body['isLastPage']) || empty($entries)) {
                        break;
                    }
                    $page++;
                    if ($page > 50) {
                        break;
                    }
                }
                if (\count($results) >= $limit) {
                    break;
                }
            }

            return $results;
        }

        // FALLBACK: tüm contact'ları sırayla tara, companyName dolu olanları topla
        // (yavaş — admin b2b_class_ids set etmemiş demek)
        $page = 1;
        while (count($results) < $limit) {
            $response = $this->connector()->send(new GetContactsRequest($page, 250));
            $response->throw();
            $body = $response->json();
            $entries = $body['entries'] ?? [];

            foreach ($entries as $e) {
                $company = $e['accounts'][0]['companyName'] ?? '';
                if ($company === '') {
                    continue;
                }
                $results[] = $this->formatContact($e);
                if (count($results) >= $limit) {
                    break 2;
                }
            }

            if (! empty($body['isLastPage']) || empty($entries)) {
                break;
            }
            $page++;
            if ($page > 20) {
                break; // güvenlik — max 5000 contact tara, sonra dur
            }
        }

        return $results;
    }

    /**
     * E-posta ile direkt Plenty araması (ShopifyStore search-as-you-type için).
     * companyName non-empty filtresinden geçer (B2B garantisi).
     *
     * @return list<array{id:int,first_name:string,last_name:string,company:string,email:string,class_id:int,created_at:string}>
     */
    public function searchContactsByEmail(string $email, int $limit = 25): array
    {
        if (trim($email) === '') {
            return [];
        }

        $response = $this->connector()->send(new GetContactsRequest(1, $limit, null, $email));
        $response->throw();

        $body = $response->json();
        $entries = $body['entries'] ?? [];

        $results = [];
        foreach ($entries as $e) {
            $company = $e['accounts'][0]['companyName'] ?? '';
            if ($company === '') {
                continue;
            }
            $results[] = $this->formatContact($e);
        }

        return $results;
    }

    protected function formatContact(array $e): array
    {
        return [
            'id' => (int) $e['id'],
            'first_name' => $e['firstName'] ?? '',
            'last_name' => $e['lastName'] ?? '',
            'company' => $e['accounts'][0]['companyName'] ?? '',
            'email' => $e['email'] ?? '',
            'class_id' => (int) ($e['classId'] ?? 0),
            'last_order_at' => $e['lastOrderAt'] ?? null,
            'created_at' => $e['createdAt'] ?? null,
        ];
    }
}
