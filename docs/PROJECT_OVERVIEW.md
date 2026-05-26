# DropPilot — Projektübersicht

> Diese Datei richtet sich an neue Teammitglieder (IT-Manager, Entwickler,
> AI-Assistenten wie Claude oder ChatGPT). Sie erklärt das Geschäft, das
> Datenmodell, den aktuellen Stand und die geplanten nächsten Schritte.
>
> Technische Schnellreferenz: siehe [`CLAUDE.md`](../CLAUDE.md) im Repo-Root.

## Inhaltsverzeichnis

1. [Was ist DropPilot?](#was-ist-droppilot)
2. [Geschäftliche Akteure & Datenfluss](#geschäftliche-akteure--datenfluss)
3. [Funktionsumfang (Stand heute)](#funktionsumfang-stand-heute)
4. [Architektur in 30 Sekunden](#architektur-in-30-sekunden)
5. [Datenmodell](#datenmodell)
6. [Wichtige Geschäftsregeln](#wichtige-geschäftsregeln)
7. [Onboarding eines neuen B2B-Händlers (Schritt-für-Schritt)](#onboarding-eines-neuen-b2b-händlers)
8. [Plenty-Verkaufspreistypen (Tier-Modell)](#plenty-verkaufspreistypen)
9. [Bekannte Limitationen & nächste Schritte](#bekannte-limitationen--nächste-schritte)
10. [Glossar](#glossar)

---

## Was ist DropPilot?

DropPilot ist eine **Multi-Tenant-Middleware**, die als Brücke zwischen
**Shopify-Shops von B2B-Händlern** und dem **PlentyMarkets-Backend von
Vapor Handels GmbH** dient.

Vapor Handels GmbH (Webshop: myhookah.de) ist Großhändler im Bereich
E-Zigaretten, Liquids und Shisha-Zubehör. Das Unternehmen verkauft an
**B2B-Wiederverkäufer**, die ihrerseits eigene Online-Shops (Shopify)
betreiben.

Bisheriger Prozess (vor DropPilot):

- Wiederverkäufer ruft an, bestellt manuell per E-Mail oder Telefon
- Vapor Handels legt Auftrag manuell in Plenty an, verschickt Ware
- Wiederverkäufer pflegt Bestand & Produkte selbst in seinem Shop

Neuer Prozess (mit DropPilot):

- Wiederverkäufer betreibt seinen Shopify-Shop normal
- Endkunde kauft im Shop
- DropPilot überträgt die Bestellung automatisch (oder per Knopfdruck)
  an Plenty als Auftrag — Rechnung geht an den Wiederverkäufer mit
  dessen individuellem B2B-Preis
- Vapor Handels versendet als Dropshipping aus eigenem Lager direkt
  an den Endkunden

Effektiv: **Vapor Handels ist Lager & Versand, der Wiederverkäufer
betreibt nur das Shop-Frontend und macht Marketing.**

## Geschäftliche Akteure & Datenfluss

```
┌─────────────────┐                      ┌──────────────────────┐
│  Endkunde (B2C) │  Bestellung im Shop  │ Shopify-Shop des     │
│                 │ ────────────────────▶│ Wiederverkäufers     │
└─────────────────┘                      └──────────┬───────────┘
                                                    │
                                            Order-JSON
                                            (line items, addr)
                                                    │
                                                    ▼
                                         ┌──────────────────────┐
                                         │  DropPilot           │
                                         │  (Laravel/Filament)  │
                                         │  - SKU lookup        │
                                         │  - Preis per Tier    │
                                         │  - Adresse anlegen   │
                                         └──────────┬───────────┘
                                                    │
                                            Plenty REST API
                                                    │
                                                    ▼
                                         ┌──────────────────────┐
                                         │  PlentyMarkets       │
                                         │  (Vapor Handels)     │
                                         │  - Auftrag angelegt  │
                                         │  - Versand           │
                                         └──────────────────────┘
```

Rechnungsrelation: Vapor Handels stellt die Rechnung an den
**Wiederverkäufer**, nicht an den Endkunden. Der Wiederverkäufer
rechnet wiederum dem Endkunden gegenüber ab (außerhalb DropPilot).

## Funktionsumfang (Stand heute)

| Modul | Status |
| --- | --- |
| **Mandanten-Management** (Händler, Lieferanten, Benutzer, Shopify-Shops) | ✅ Live |
| **Shopify-Onboarding via OAuth** (Public-App-Install-Flow) | ⚠️ Funktioniert, App ist aber noch nicht öffentlich distribuiert |
| **Shopify-Onboarding via Custom App + Token** | ✅ Live, dokumentiert |
| **Plenty-Stammdaten-Sync** (Referrer, Lager, Status, Verkaufspreise) | ✅ via Artisan `plenty:sync-references` oder Lieferanten-Aktion |
| **Plenty-Katalog-Sync** (~9.500 Items, davon Pakete) | ✅ via Artisan `plenty:sync-products` (chunked) oder UI |
| **B2B-Kunden-Liste** (live aus Plenty, mit Suche & Filter) | ✅ Eigene Filament-Page |
| **B2B-Kunde Detailansicht** (Shopify-Käufer, Plenty-Aufträge, Plenty-Endkunden in 3 Tabs) | ✅ |
| **Produkt-Push Shopify ←  Plenty** (einzeln + bulk + Hersteller-Bulk) | ✅ |
| **Hersteller-Freigaben** (welcher Hersteller darf in welche B2B-Shops) | ✅ Neu in 2026-05 |
| **Shopify → Plenty Auftrag (manuell pro Bestellung)** | ✅ Live, getestet mit echten Bestellungen |
| **Shopify → Plenty Auftrag (automatisch via Webhook)** | ⏳ Geplant |
| **Plenty → Shopify Fulfillment-Rückkanal** (Tracking, Versand) | ⏳ Geplant |
| **Bestandssynchronisation Plenty → Shopify** | ⏳ Geplant |
| **Public App Store Distribution + Billing API** | ⏳ Optional, nicht prioritär |

Stand: **Mai 2026**.

## Architektur in 30 Sekunden

- **Laravel 11** Backend (PHP 8.3)
- **Filament v3** Admin-Panel (Livewire) für alles Operative
- **Saloon** HTTP-Client für saubere REST-Wrapper (Plenty + Shopify)
- **MySQL** als einzige Persistenz
- **kyon147/laravel-shopify** Paket für OAuth-Install-Flow (mit Auto-Refresh
  von Offline-Tokens)
- Eigene Custom-App-Implementierung daneben — beide Ansätze koexistieren
- Hosting auf **Plesk-Server** (`droppilot.34devs.com`)
- Multi-Tenancy logisch im Datenmodell (`tenants`, `suppliers`, `shopify_stores`),
  **keine** separaten Datenbanken pro Tenant

## Datenmodell

```
┌──────────┐       ┌──────────────────┐       ┌────────────┐
│ tenants  │◀─────▶│ tenant_supplier  │◀─────▶│ suppliers  │
│ (Händler)│       │ (mit plenty_     │       │ (Plenty-   │
└─────┬────┘       │  contact_id)     │       │  Konten)   │
      │            └──────────────────┘       └──────┬─────┘
      │                                              │
      │            ┌─────────────────┐               │
      └───────────▶│ shopify_stores  │◀──────────────┘
                   │ (Shop-Domain,   │
                   │  Token, Plenty- │
                   │  Mapping)       │
                   └────────┬────────┘
                            │
        ┌───────────────────┴────────────────────┐
        ▼                                        ▼
┌──────────────────┐                  ┌─────────────────────┐
│ shopify_pushed_  │                  │ plenty_orders       │
│ products         │                  │ (Bestellungs-Track) │
│ (Push-Tracking)  │                  └─────────────────────┘
└──────────────────┘
        ▲
        │
┌──────────────┐         ┌─────────────────────┐
│ products     │◀────────│ product_variations  │
│ (Katalog-    │         │ (SKU, Preis, Stock) │
│  Cache)      │         └─────────────────────┘
└──────┬───────┘
       │
       │ manufacturer_id
       ▼
┌──────────────────────────────────────┐
│ manufacturer_shop_permissions        │
│ (welcher Hersteller → welche Shops)  │
└──────────────────────────────────────┘
```

Eine detaillierte Auflistung jeder Spalte: siehe Migrations unter
`database/migrations/`.

## Wichtige Geschäftsregeln

### 1. SKU = universeller Join-Key

Plenty-Variation-Nummer und Shopify-Variant-SKU sind **identisch**. Kein
manuelles Mapping. Wenn ein SKU in Plenty nicht gefunden wird, wird das
Ergebnis als „negativer Cache" gespeichert, damit nicht wiederholt
gesucht wird.

### 2. Two-Tier-Pricing

- **Shopify-Preis** ist nur Referenz für den Endkunden — der Wiederverkäufer
  setzt diesen selbst nach Marktbedingungen.
- **Plenty-Auftragspreis** ist der **B2B-Preis** des Wiederverkäufers
  (ein konfigurierbarer Verkaufspreistyp, z. B. „Level 5" oder „B2B Standard").
- Jeder Shopify-Shop hat die Spalte `plenty_sales_price_id` — beim Auftragsanlegen
  wird der Preis dieses Typs aus den `variationSalesPrices` der jeweiligen
  Variation gezogen.

**Konsequenz:** Vapor Handels rechnet immer mit dem korrekten Großhandelspreis
ab, unabhängig davon, was der Wiederverkäufer im Shop anzeigt.

### 3. Nur Paket-Artikel

In Plenty führt Vapor Handels sowohl Einzelartikel (für den eigenen Webshop
myhookah.de) als auch Großhandelspakete (für Wiederverkäufer). Die Pakete
sind erkennbar am Hersteller-Namens-Postfix **„Paket"** (z. B.
„ELFBAR V1 2% Paket"). DropPilot synchronisiert **ausschließlich** Pakete
in die `products`-Tabelle (`is_package=true`-Flag).

### 4. Rechnung vs. Lieferung

Beim Anlegen eines Plenty-Auftrags:

- **Rechnungsadresse** wird aus den vorhandenen Adressen des Plenty-Kontakts
  des Wiederverkäufers gezogen (die Adresse mit `contactRelations[].typeId = 1`).
  Ist diese nicht gepflegt, sieht der Auftrag in Plenty „komisch" aus (z. B.
  Plenty-Default „Max Mustermann"). Lösung: Plenty-Admin pflegt für jeden
  B2B-Kunden die Rechnungsadresse einmalig.
- **Lieferadresse** wird bei jeder Bestellung **neu** angelegt auf den
  Kontakt (typeId=2), basierend auf der Shopify `shipping_address`. So
  geht jeder Versand an den richtigen Endkunden.

### 5. Externe Auftrags-ID

Im Plenty-Auftrag wird die Shopify-Order-ID als Eigenschaft mit `typeId=7`
gespeichert. So lässt sich später jederzeit der ursprüngliche Shopify-Auftrag
zuordnen.

## Onboarding eines neuen B2B-Händlers

**Voraussetzung:** Der Händler ist in PlentyMarkets als B2B-Kontakt
angelegt und einer B2B-Kundenklasse zugeordnet (z. B. Klasse 12 oder 50).

### Schritt 1: B2B-Kunde in DropPilot sichtbar machen

Wenn der Plenty-Kontakt zu einer Klasse gehört, die im Lieferanten unter
**B2B-Kundenklassen** ausgewählt ist, erscheint er automatisch in der
**B2B-Kunden-Liste** im Panel.

### Schritt 2: Shopify-Shop verbinden

**Variante A — Custom App (empfohlen):**

1. Händler bekommt die Anleitung [`SHOPIFY_CUSTOM_APP_ANLEITUNG.md`](SHOPIFY_CUSTOM_APP_ANLEITUNG.md)
2. Händler erstellt im eigenen Shopify-Admin eine Custom App mit den
   benötigten Scopes
3. Händler schickt Shop-Domain + Access-Token an den Vapor-Handels-Admin
4. Admin öffnet DropPilot → **Shopify-Shops → „Shop manuell hinzufügen"**
5. Daten eintragen → Verbindungstest läuft automatisch

**Variante B — OAuth-Install-Link:**

- Aktuell **nicht produktiv nutzbar** weil die App in Shopify Partner
  Dashboard noch nicht distribuiert ist (Stand 2026-05).
- Wird in Zukunft (App Store Submission) freigeschaltet.

### Schritt 3: Händler-Zuordnung

Im Shopify-Shop-Edit:

- **Händler-Zuordnung:** Plenty-Kontakt aussuchen (Dropdown mit Autocomplete)
- **Plenty-Auftragseinstellungen:**
  - **Verkaufspreistyp** (z. B. Level 5)
  - Lager (optional)
  - Auftragsstatus (optional, sonst Default-Status des Lieferanten)
- Speichern

### Schritt 4: Produkte ausspielen

Zwei Wege:

- **Artikel-Liste** → Mehrfachauswahl → „In Shopify-Shop übertragen"
- **Hersteller** → Detailseite → Shops markieren → Freigaben speichern →
  „Alle Artikel an freigegebene Shops senden"

### Schritt 5: Bestellung empfangen

Sobald der Endkunde im Shopify-Shop bestellt:

- **Manuell:** Admin geht in Shopify-Shops → „Bestellungen anzeigen" →
  Zeile → „An Plenty senden"
- **Geplant (Phase nächste):** Automatisch via Shopify-Webhook
  `orders/paid`

## Plenty-Verkaufspreistypen

Vapor Handels führt in Plenty mehrere Preistypen (z. B. „Level 1" für
Endkunden auf myhookah.de bis „Level 6" für Top-Großhändler). Jeder
Wiederverkäufer wird einem dieser Tiers zugeordnet.

Zu jedem Plenty-Item kann pro Tier ein eigener Preis hinterlegt sein.
DropPilot liest beim Auftragsanlegen den Preis des im Shop konfigurierten
Tiers aus dem `variationSalesPrices`-Array der Plenty-Variation.

Ist für einen SKU kein Preis im konfigurierten Tier hinterlegt, wirft
DropPilot einen Fehler („SKU X: salesPriceId=Y nicht definiert"), damit
nicht versehentlich ein falscher Preis abgerechnet wird.

## Bekannte Limitationen & nächste Schritte

### Limitationen (Stand 2026-05)

1. **Manueller Auftrag-Push.** Jede Bestellung muss aktuell per Klick
   in Plenty angelegt werden. Eine Automation via Shopify-Webhook ist
   geplant.

2. **Keine Bestandssynchronisation.** Wenn Plenty einen Artikel
   ausverkauft, weiß der Shopify-Shop das nicht. Risiko von Übersales.

3. **Kein Fulfillment-Rückkanal.** Nachdem Vapor Handels versendet hat,
   bekommt der Shopify-Shop des Wiederverkäufers keine Tracking-Nummer
   zurück automatisch. Muss manuell nachgepflegt werden.

4. **Shopify App noch nicht im App Store.** Daher derzeit Custom-App-
   Onboarding pro Händler. Skaliert bis ~10 Händler bequem, danach
   wird App-Store-Submission sinnvoll (siehe unten).

5. **Plenty-Katalog-Sync zeitintensiv.** ~9.500 Items + Varianten +
   Bilder = ~30 Min für Vollsync. Daher chunked (`plenty:sync-products
   --max-pages=10`) ausführen.

### Geplante nächste Schritte

In ungefährer Priorität:

1. **Webhook-basierter Auftrag-Push.** Shopify-Webhook `orders/paid` →
   Job-Queue → automatischer `PushOrderToPlenty`-Aufruf. Kein Klick mehr
   nötig.

2. **Fulfillment-Rückkanal.** Plenty-Webhook (oder Polling) bei
   Auftragsversand → Shopify-API `fulfillment_create` mit Tracking-Nummer
   und Carrier.

3. **Bestandssynchronisation.** Plenty `inventoryUpdate`-Webhook →
   Shopify `inventoryItem` aktualisieren.

4. **Public App Distribution + App Store Submission.** Erfordert:
   - Privacy Policy
   - GDPR-Webhook-Handler (3 Pflicht-Webhooks)
   - Billing-API-Integration (optional, für SaaS-Modell)
   - Screencast & App-Listing
   - Inspection-Prozess (2–6 Wochen)

5. **Reporting-Dashboard.** Umsatz pro Händler, pro Hersteller, pro
   Zeitraum.

## Glossar

| Begriff | Bedeutung |
| --- | --- |
| **B2B-Kunde / Händler / Tenant** | Wiederverkäufer mit eigenem Shopify-Shop, ist Plenty-Contact bei Vapor Handels |
| **Lieferant / Supplier** | Plenty-Konto (aktuell nur eines: Vapor Handels GmbH / myhookah.de) |
| **Mandant / plentyId** | Plenty-interne Mandanten-ID (für Multi-Shop-Plenty-Konten) |
| **Auftrag** | Plenty-Verkaufsbestellung (entspricht Shopify-Order) |
| **Hersteller** | Plenty-Manufacturer (z. B. „ELFBAR V1 2% Paket", „Lost Mary Liquid Paket") |
| **Paket** | Großhandelseinheit (mehrere Stück pro Verkaufseinheit). Erkennbar am Hersteller-Namens-Suffix |
| **Verkaufspreistyp** | Plenty-Preis-Tier (z. B. Level 5). Pro Händler eines |
| **Referrer / Herkunft** | Plenty-Quellen-ID, markiert woher der Auftrag kam |
| **Lieferadresse / Versandadresse** | Adresse mit typeId=2 in Plenty |
| **Rechnungsadresse** | Adresse mit typeId=1 in Plenty |
| **Externe Auftrags-ID** | Shopify-Order-ID, gespeichert als Property mit typeId=7 im Plenty-Auftrag |

---

**Letztes Update:** 2026-05-26. Pflege diese Datei mit, wenn größere
Architektur- oder Geschäftsänderungen geschehen.
