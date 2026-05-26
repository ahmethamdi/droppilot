# DropPilot — Projektkontext für AI-Assistenten

Diese Datei wird automatisch von Claude Code, Cursor und ähnlichen
AI-Coding-Tools gelesen. Sie fasst das Wesentliche zusammen, damit
neue Mitarbeiter (Mensch oder AI) sofort produktiv arbeiten können.

## Was ist DropPilot?

**DropPilot** ist eine Multi-Tenant-Middleware, die Shopify-Shops von
B2B-Händlern an das PlentyMarkets-Backend der Firma **Vapor Handels GmbH**
(myhookah.de) anbindet.

Geschäftsablauf:

1. Vapor Handels führt einen Großhandelskatalog in PlentyMarkets
   (~9.500 Artikel, davon ca. 1.000 als „Paket" gekennzeichnet — nur
   diese werden weiterverkauft).
2. B2B-Kunden (Händler) betreiben jeweils einen eigenen Shopify-Shop.
3. DropPilot überträgt Paket-Artikel auf Knopfdruck aus Plenty in die
   Shopify-Shops (Produkt-Push).
4. Wenn ein Endkunde im Shopify-Shop bestellt, wird die Bestellung
   in Plenty als Auftrag angelegt — auf den **B2B-Händler als
   Rechnungsempfänger**, mit dessen individuellem **Verkaufspreistyp**.
   Lieferadresse ist der Endkunde aus Shopify.
5. Vapor Handels packt und versendet aus dem eigenen Lager (Dropshipping).

## Tech-Stack

| Bereich | Technologie |
| --- | --- |
| Sprache / Framework | PHP 8.3, Laravel 11 |
| Admin-Panel | Filament v3 (Livewire-basiert) |
| HTTP-Client | Saloon (für Plenty & Shopify REST APIs) |
| DB | MySQL |
| Shopify-Integration | `kyon147/laravel-shopify` Paket (OAuth) + eigene Custom-App-Implementierung |
| Berechtigungen | `spatie/laravel-permission` |
| Hosting | Plesk auf dediziertem Server, Domain `droppilot.34devs.com` |
| Repo | GitHub: `ahmethamdi/droppilot` (Main-Branch: `main`) |

## Verzeichnisstruktur (wesentlich)

```
app/
  Console/Commands/        → Artisan-Befehle (plenty:sync-products, plenty:sync-references)
  Filament/
    Pages/                 → Eigene Filament-Pages (B2B-Kunden, Hersteller)
    Resources/             → Filament-Resources (CRUD pro Model)
    Widgets/               → Dashboard-Widgets
  Models/                  → Eloquent-Models
  Providers/Filament/      → AdminPanelProvider (Theme, Locale, Navigation)
  Services/
    Plenty/                → PlentyClient, PushOrderToPlenty, Requests/
    Shopify/               → ShopifyClient, PushProductToShopify, Requests/
database/migrations/       → Alle Migrations chronologisch
resources/views/filament/  → Filament-Blade-Views (Modals, Pages, Widgets)
docs/                      → Onboarding-Anleitungen (z. B. Custom App)
```

## Datenmodell (Eloquent)

| Tabelle / Model | Zweck |
| --- | --- |
| `users` | Admin-Benutzer (Spatie-Roles) |
| `tenants` | Händler (Bayi) — jeder B2B-Kunde wird hier abgebildet |
| `tenant_user` | n:m Tenant ↔ User mit Rolle |
| `suppliers` | Lieferanten = Plenty-Konten (aktuell nur: Vapor Handels) |
| `tenant_supplier` | Pivot: Welcher Tenant ist welcher Plenty-Kontakt? |
| `supplier_references` | Plenty-Stammdaten gecacht (referrer, warehouse, sales_price, order_status) |
| `shopify_stores` | Verbundene Shopify-Shops. Spalte `name` = Domain, `password` = Access-Token (Trait `kyon147/laravel-shopify`). Auch `supplier_id`, `plenty_contact_id`, `plenty_sales_price_id` |
| `products` | Plenty-Katalog (nur `is_package=true`-Artikel werden gespeichert) |
| `product_variations` | Varianten + SKU + Preis + Bestand + Image-URL |
| `sku_lookups` | Cache: SKU → Plenty-Variation-ID (positive & negative) |
| `shopify_pushed_products` | Pivot: welcher Product wurde in welchen Shop gepusht (mit `shopify_product_id`) |
| `plenty_orders` | Tracking: Jede Shopify-Bestellung, die wir als Plenty-Auftrag angelegt haben (state, attempts, payload, response) |
| `manufacturer_shop_permissions` | Welche Hersteller darf der Admin in welche B2B-Shops pushen |

## Wichtige Konventionen / Geschäftsregeln

1. **SKU als universeller Join-Key.** Plenty-Variation-Nummer == Shopify-Variant-SKU. Kein manuelles Mapping. Negative Lookups werden gecacht (Plenty hat keinen passenden SKU).

2. **Two-Tier Pricing.** Der Shopify-Preis ist reine Referenz (Händler setzt selbst). Im Plenty-Auftrag wird **immer** der Preis aus `shopify_stores.plenty_sales_price_id` (Verkaufspreistyp) gezogen — das ist der B2B-Großhandelspreis, der dem Händler in Rechnung gestellt wird.

3. **Nur Paket-Artikel.** In Plenty sind Großhandelsmengen mit dem Hersteller-Namen-Postfix „Paket" gekennzeichnet (z. B. „ELFBAR V1 2% Paket"). `syncProducts()` überspringt Einzelartikel automatisch.

4. **Rechnungsadresse aus Plenty, Lieferadresse aus Shopify.** Beim Anlegen eines Plenty-Auftrags:
   - Rechnung: über `addressRelations[].typeId === 1` aus den vorhandenen Adressen des B2B-Kontakts ermitteln (Plenty-Query `?typeId=1` ist UNZUVERLÄSSIG, daher manuelle Filterung im Code).
   - Lieferung: neue Adresse auf den Kontakt anlegen (`typeId=2`) aus Shopify `shipping_address`.

5. **Custom App vs. OAuth.** Beide Wege koexistieren in `ShopifyStoreResource`:
   - OAuth über `/authenticate?shop=…` (kyon147-Paket)
   - Manuell: „Shop manuell hinzufügen"-Button. Händler erstellt eine private App in seinem Shopify-Admin, schickt den `shpat_…`-Token (siehe `docs/SHOPIFY_CUSTOM_APP_ANLEITUNG.md`)
   - `ShopifyClient::ensureFreshToken()` skippt den Token-Refresh, wenn kein `shopify_offline_refresh_token` vorhanden (Custom-Tokens sind unbefristet).

6. **Sprache.** Das Admin-Panel ist komplett **auf Deutsch** (`APP_LOCALE=de`, Filament-Vendor liefert `de`-Übersetzungen mit). Code-Kommentare oft auf Türkisch (Entwickler-Sprache des Gründers Ahmet), aber neue Code-Beiträge gern auf Deutsch oder Englisch — beides ok.

## Wichtige Dateien (Tour)

- [app/Services/Plenty/PlentyClient.php](app/Services/Plenty/PlentyClient.php) — Zentraler Plenty-Wrapper (Auth, Caching, alle Endpunkte: `listB2BContacts()`, `lookupSku()`, `syncProducts()`, `syncReferences()`, `createOrder()`, `getEndCustomersByContact()`, …)
- [app/Services/Plenty/PushOrderToPlenty.php](app/Services/Plenty/PushOrderToPlenty.php) — Orchestrator: Shopify-Order JSON → Plenty-Auftrag
- [app/Services/Shopify/ShopifyClient.php](app/Services/Shopify/ShopifyClient.php) — Shopify-Wrapper mit Auto-Refresh-Guard
- [app/Services/Shopify/PushProductToShopify.php](app/Services/Shopify/PushProductToShopify.php) — Plenty-Artikel → Shopify Produkt anlegen
- [app/Filament/Resources/ShopifyStoreResource.php](app/Filament/Resources/ShopifyStoreResource.php) — Komplexeste Resource, beherbergt B2B-Mapping-Autocomplete, Custom-App-Onboarding, „An Plenty senden"-Modal
- [app/Filament/Pages/B2bKundenList.php](app/Filament/Pages/B2bKundenList.php) + Detail — Plenty-Kunden-Übersicht
- [app/Filament/Pages/HerstellerList.php](app/Filament/Pages/HerstellerList.php) + Detail — Hersteller-Freigaben + Bulk-Push
- [database/migrations/](database/migrations/) — chronologisch, jede Phase erkennbar

## Deployment

- **Code** wird per Git (Plesk Deployment) gepullt
- Nach jedem Pull manuell ausführen (Plesk Artisan-Tab):
  - `migrate --force` (falls neue Migration)
  - `optimize:clear`
- **Webhook-OAuth-URL:** `https://droppilot.34devs.com/authenticate?shop=…`
- **Wichtig:** PHP `max_execution_time` in Plesk ist begrenzt (oft 300 s). Lange Synchronisationen (Plenty-Katalog, 9.500 Items) werden per **chunked Artisan-Command** ausgeführt:
  ```
  plenty:sync-products --max-pages=10 --start-page=1
  plenty:sync-products --max-pages=10 --start-page=11
  …
  ```

## Häufige Aufgaben (Cheat-Sheet)

| Aufgabe | Wo / Wie |
| --- | --- |
| Neuen B2B-Händler verbinden | Custom-App-Anleitung an Händler schicken, Token in „Shop manuell hinzufügen" eintragen, dann Edit → Händler-Zuordnung + Verkaufspreistyp |
| Plenty-Katalog aktualisieren | `plenty:sync-products` (chunked) oder UI: Artikel → „Katalog aus Plenty synchronisieren" |
| Plenty-Stammdaten aktualisieren | `plenty:sync-references` (referrer, warehouses, sales_prices, order_status) |
| Bestellung manuell an Plenty senden | Shopify-Shops → „Bestellungen anzeigen" → Zeile → „An Plenty senden" |
| Hersteller einem B2B-Shop freigeben + alle Artikel pushen | Hersteller → Detail → Shop(s) markieren → „Freigaben speichern" → „Alle Artikel senden" |

## Bekannte Stolpersteine

- **Plenty `?typeId=1` Query-Filter** für Contact-Addresses **funktioniert nicht zuverlässig** — gibt alle Adressen zurück. Daher immer `contactRelations[].typeId` im Code filtern.
- **Plenty `with[]` Query-Parameter** in Saloon: Array-Syntax (`'with' => ['addresses', 'addressRelations']`) wird korrekt zu `?with[]=…&with[]=…` serialisiert. Komma-Strings funktionieren nicht.
- **Plenty Option-typeIds** für E-Mail/Telefon im Address-Objekt sind **kontoabhängig** (4 vs. 5). Code (`extractEmailFromOptions`, `extractPhoneFromOptions`) erkennt Werte heuristisch (am „@"-Zeichen für E-Mail).
- **Token-Refresh** bei Shopify nur ausführen, wenn `shopify_offline_refresh_token` gesetzt ist — sonst Custom-App-Tokens fälschlich invalidieren.
- **Shopify App Distribution:** App ist derzeit in Partner Dashboard **noch nicht distributiert** (weder Public noch Custom). Aktuelle Lösung: Custom-App pro Händler im jeweiligen Shopify-Admin → unbefristete Tokens, keine Shopify-Inspektion.

## Repository-Workflow

- `main` Branch ist Production
- Direkter Push (kein PR-Zwang) — aber Commits sollten klar und in Imperativ formuliert sein, gern auf Deutsch
- Co-Author-Trailer für AI-Commits üblich: `Co-Authored-By: Claude … <noreply@anthropic.com>`

## Wo finde ich mehr?

- [README.md](README.md) — Standard-Laravel-Readme (knapp)
- [docs/PROJECT_OVERVIEW.md](docs/PROJECT_OVERVIEW.md) — Geschäftliche & funktionale Übersicht für Stakeholder
- [docs/SHOPIFY_CUSTOM_APP_ANLEITUNG.md](docs/SHOPIFY_CUSTOM_APP_ANLEITUNG.md) — Anleitung für B2B-Händler (auf Deutsch)

## Kontakt

- Owner / Lead Developer: **Ahmet Hamdi Kılıç** — ahmethamdik8@gmail.com
- Unternehmen: Vapor Handels GmbH (`myhookah.de`)
