<?php

namespace App\Services\Plenty;

use App\Models\Supplier;
use App\Models\SupplierReference;
use App\Models\SkuLookup;
use App\Services\Plenty\Requests\CreateContactAddressRequest;
use App\Services\Plenty\Requests\CreateOrderRequest;
use App\Services\Plenty\Requests\GetContactAddressesRequest;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Plenty\Requests\GetContactClassesRequest;
use App\Services\Plenty\Requests\GetContactRequest;
use App\Services\Plenty\Requests\GetContactsRequest;
use App\Services\Plenty\Requests\GetItemImagesRequest;
use App\Services\Plenty\Requests\GetItemsRequest;
use App\Services\Plenty\Requests\GetItemVariationsRequest;
use App\Services\Plenty\Requests\GetManufacturersRequest;
use App\Services\Plenty\Requests\GetOrdersByContactRequest;
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

    /**
     * Tüm Plenty manufacturer'larını ID->name map olarak döndür (cache'li).
     * Plenty UI'da "ELFBAR V1 0% Paket" gibi paket manufacturer'ları var;
     * isminde "Paket" kelimesi geçenleri Shopify B2B paket olarak işaretliyoruz.
     *
     * @return array<int, string>  manufacturerId => name
     */
    public function listManufacturers(): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "plenty:manufacturers:supplier:{$this->supplier->id}",
            now()->addMinutes(30),
            function () {
                $out = [];
                $page = 1;
                while (true) {
                    $response = $this->connector()->send(new GetManufacturersRequest($page, 250));
                    $response->throw();
                    $body = $response->json();
                    $entries = $body['entries'] ?? [];

                    foreach ($entries as $m) {
                        $out[(int) $m['id']] = trim((string) ($m['name'] ?? ''));
                    }

                    if (! empty($body['isLastPage']) || empty($entries)) {
                        break;
                    }
                    $page++;
                    if ($page > 20) {
                        break;
                    }
                }

                return $out;
            },
        );
    }

    /**
     * Plenty katalogundan ürünleri (items) + variations + fiyat + image
     * DropPilot DB'ye senkronize et.
     *
     * Performans: 500 ürün ≈ 5-10dk (her ürün için 2 ek API: variations + images).
     * $packagesOnly=true ise sadece is_package=true olan ürünler için
     * variation'ları çeker (hızlanır, demo için yeterli).
     *
     * @return array{processed:int,created:int,updated:int,variations_synced:int}
     */
    public function syncProducts(
        int $maxItems = 10000,
        bool $packagesOnly = false,
        int $startPage = 1,
        ?int $maxPages = null,
    ): array {
        $manufacturers = $this->listManufacturers(); // id => name
        $created = 0;
        $updated = 0;
        $processed = 0;
        $variationsSynced = 0;
        $page = max(1, $startPage);
        $pagesScanned = 0;
        $lastPageSeen = $page;

        while ($processed < $maxItems) {
            $lastPageSeen = $page;
            if ($maxPages !== null && $pagesScanned >= $maxPages) {
                break;
            }
            $response = $this->connector()->send(new GetItemsRequest($page, 250));
            $response->throw();
            $body = $response->json();
            $entries = $body['entries'] ?? [];

            if (empty($entries)) {
                break;
            }

            foreach ($entries as $item) {
                $texts = $item['texts'][0] ?? [];
                $manufacturerId = $item['manufacturerId'] ?? null;
                $manufacturerName = $manufacturerId ? ($manufacturers[$manufacturerId] ?? null) : null;
                $isPackage = $manufacturerName !== null
                    && mb_stripos($manufacturerName, 'paket') !== false;

                // SADECE PAKET ÜRÜNLERİ DB'YE KAYDET — tekliler atlanır
                if (! $isPackage) {
                    continue;
                }

                $product = Product::updateOrCreate(
                    [
                        'supplier_id' => $this->supplier->id,
                        'plenty_item_id' => (int) $item['id'],
                    ],
                    [
                        'main_variation_id' => $item['mainVariationId'] ?? null,
                        'manufacturer_id' => $manufacturerId,
                        'manufacturer_name' => $manufacturerName,
                        'is_package' => $isPackage,
                        'item_type_id' => null,
                        'name' => $texts['name1'] ?? null,
                        'name2' => $texts['name2'] ?? null,
                        'short_description' => $texts['shortDescription'] ?? null,
                        'description' => $texts['description'] ?? null,
                        'meta_description' => $texts['metaDescription'] ?? null,
                        'payload' => $item,
                        'plenty_updated_at' => $item['updatedAt'] ?? null,
                        'synced_at' => now(),
                    ],
                );

                $product->wasRecentlyCreated ? $created++ : $updated++;
                $processed++;

                // Her ürün için variation + fiyat + image çek
                // $packagesOnly true ise sadece paket olanlar için yap (hız için)
                if (! $packagesOnly || $isPackage) {
                    try {
                        $variationsSynced += $this->syncItemVariations($product);
                    } catch (\Throwable $e) {
                        // Tek bir variation hatası tüm sync'i durdurmasın
                    }
                }

                if ($processed >= $maxItems) {
                    break;
                }
            }

            $pagesScanned++;
            if (! empty($body['isLastPage'])) {
                $page++;
                break;
            }
            $page++;
            if ($page > 100) {
                break;
            }
        }

        return [
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'variations_synced' => $variationsSynced,
            'last_page' => $lastPageSeen,
            'next_page' => $page,
            'pages_scanned' => $pagesScanned,
        ];
    }

    /**
     * Tek bir Plenty item'in variations'larını DB'ye yaz (lazy load).
     * Kullanıcı bir ürünün detayına bakınca çağrılır.
     *
     * @return int variation count
     */
    public function syncItemVariations(Product $product): int
    {
        $response = $this->connector()->send(new GetItemVariationsRequest((int) $product->plenty_item_id));
        $response->throw();

        $entries = $response->json('entries') ?? [];
        $count = 0;

        // Item-level images: tek API çağrısı, tüm varyasyonlarda paylaşılır
        $imageUrl = null;
        try {
            $imgResponse = $this->connector()->send(new GetItemImagesRequest((int) $product->plenty_item_id));
            $imgResponse->throw();
            $images = $imgResponse->json();
            if (\is_array($images) && ! empty($images)) {
                // İlk image'in full URL'i (Shopify productCreate için yeterli boyut)
                $imageUrl = $images[0]['url'] ?? $images[0]['urlMiddle'] ?? null;
            }
        } catch (\Throwable $e) {
            // image yoksa devam et
        }

        foreach ($entries as $v) {
            // Retail price: salesPriceId=1 (Aktionspreis Webshop)
            $retailPrice = null;
            foreach ($v['variationSalesPrices'] ?? [] as $vsp) {
                if ((int) ($vsp['salesPriceId'] ?? 0) === 1) {
                    $retailPrice = (float) $vsp['price'];
                    break;
                }
            }

            // Stock toplamı
            $stockNet = null;
            if (isset($v['stock']) && \is_array($v['stock'])) {
                $stockNet = collect($v['stock'])->sum('netStock');
            }

            ProductVariation::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'plenty_variation_id' => (int) $v['id'],
                ],
                [
                    'sku' => $v['number'] ?? null,
                    'model' => $v['model'] ?? null,
                    'name' => $v['name'] ?? null,
                    'is_main' => (bool) ($v['isMain'] ?? false),
                    'is_active' => (bool) ($v['isActive'] ?? false),
                    'retail_price' => $retailPrice,
                    'retail_price_source_id' => $retailPrice !== null ? 1 : null,
                    'currency' => 'EUR',
                    'stock_net' => $stockNet,
                    'weight_g' => $v['weightG'] ?? null,
                    'width_mm' => $v['widthMM'] ?? null,
                    'length_mm' => $v['lengthMM'] ?? null,
                    'height_mm' => $v['heightMM'] ?? null,
                    'image_url' => $imageUrl,
                    'payload' => $v,
                    'synced_at' => now(),
                ],
            );
            $count++;
        }

        return $count;
    }

    /**
     * Plenty contact'a yeni adres ekle (Lieferadresse için typeId=2).
     * @return int Yeni address ID
     */
    public function createContactAddress(int $contactId, array $address): int
    {
        $response = $this->connector()->send(new CreateContactAddressRequest($contactId, $address));
        $response->throw();

        $data = $response->json();
        if (! isset($data['id'])) {
            throw new \RuntimeException('Plenty createContactAddress beklenen "id" alanını döndürmedi.');
        }

        return (int) $data['id'];
    }

    /**
     * Plenty'de Auftrag yarat.
     * @return array Plenty order response (id, dates, status, ...)
     */
    public function createOrder(array $payload): array
    {
        $response = $this->connector()->send(new CreateOrderRequest($payload));
        $response->throw();

        return $response->json();
    }

    /**
     * Bir Plenty contact'ın receiver olduğu Aufträge'i çek + her Auftrag'ın
     * Lieferadresse'sinden alıcı bilgisini özetle.
     *
     * Bu, B2B müşterimizin Plenty'de gördüğü "son tüketici" listesidir.
     *
     * @return array<int, array{
     *   order_id:int, order_date:?string, total:?float, currency:?string, status_id:?float,
     *   buyer_name:string, buyer_company:string, buyer_email:string, buyer_phone:string,
     *   buyer_postal_code:string, buyer_town:string, buyer_country_id:?int,
     * }>
     */
    public function getEndCustomersByContact(int $contactId, int $maxOrders = 500): array
    {
        $orders = [];
        $page = 1;
        while (count($orders) < $maxOrders) {
            $response = $this->connector()->send(new GetOrdersByContactRequest($contactId, $page, 100));
            $response->throw();
            $body = $response->json();
            $entries = $body['entries'] ?? [];
            if (empty($entries)) {
                break;
            }
            $orders = array_merge($orders, $entries);

            if (! empty($body['isLastPage'])) {
                break;
            }
            $page++;
            if ($page > 20) {
                break;
            }
        }

        $customers = [];
        foreach ($orders as $order) {
            $deliveryAddress = $this->extractDeliveryAddress($order);
            $customers[] = [
                'order_id' => (int) ($order['id'] ?? 0),
                'order_date' => $order['createdAt'] ?? ($order['dates'][0]['date'] ?? null),
                'total' => isset($order['amounts'][0]['invoiceTotal']) ? (float) $order['amounts'][0]['invoiceTotal'] : null,
                'currency' => $order['amounts'][0]['currency'] ?? null,
                'status_id' => isset($order['statusId']) ? (float) $order['statusId'] : null,
                'buyer_name' => trim(($deliveryAddress['name2'] ?? '').' '.($deliveryAddress['name3'] ?? '')),
                'buyer_company' => $deliveryAddress['name1'] ?? '',
                // Plenty option typeId konvansiyonu hesaplara göre değişebilir.
                // E-posta için "@" içeren değeri, telefon için kalan rakam ağırlıklı değeri seç.
                'buyer_email' => $this->extractEmailFromOptions($deliveryAddress),
                'buyer_phone' => $this->extractPhoneFromOptions($deliveryAddress),
                'buyer_postal_code' => $deliveryAddress['postalCode'] ?? '',
                'buyer_town' => $deliveryAddress['town'] ?? '',
                'buyer_country_id' => isset($deliveryAddress['countryId']) ? (int) $deliveryAddress['countryId'] : null,
            ];
        }

        return $customers;
    }

    /**
     * Auftrag içinden typeId=2 (Lieferadresse) olan adres objesini bul.
     */
    protected function extractDeliveryAddress(array $order): array
    {
        $addresses = $order['addresses'] ?? [];
        $relations = $order['addressRelations'] ?? [];

        // addressRelations'tan typeId=2 olan addressId'yi bul
        $deliveryAddressId = null;
        foreach ($relations as $r) {
            if ((int) ($r['typeId'] ?? 0) === 2) {
                $deliveryAddressId = (int) ($r['addressId'] ?? 0);
                break;
            }
        }

        if (! $deliveryAddressId) {
            return $addresses[0] ?? [];
        }

        foreach ($addresses as $a) {
            if ((int) ($a['id'] ?? 0) === $deliveryAddressId) {
                return $a;
            }
        }

        return $addresses[0] ?? [];
    }

    /**
     * Address.options array'inden e-posta benzeri ("@" içeren) ilk değeri bul.
     */
    protected function extractEmailFromOptions(array $address): string
    {
        foreach ($address['options'] ?? [] as $opt) {
            $value = (string) ($opt['value'] ?? '');
            if (str_contains($value, '@')) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Address.options array'inden telefon benzeri (sayı/+ ağırlıklı) ilk değeri bul.
     */
    protected function extractPhoneFromOptions(array $address): string
    {
        foreach ($address['options'] ?? [] as $opt) {
            $value = (string) ($opt['value'] ?? '');
            if ($value === '' || str_contains($value, '@')) {
                continue;
            }
            // ASCII alphabetic karakter ağırlıklı değilse (telefon gibi numara/+/space) seç
            $alpha = preg_match_all('/[A-Za-z]/', $value);
            if ($alpha < 3) {
                return $value;
            }
        }

        return '';
    }
}
