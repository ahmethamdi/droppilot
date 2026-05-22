# DropPilot — Shopify Custom App Einrichtung

Diese Anleitung erklärt, wie Sie Ihren Shopify-Shop mit DropPilot verbinden, ohne dass die App im Shopify App Store gelistet sein muss.

**Geschätzte Dauer: 5 Minuten.**

## 1. Im Shopify-Admin anmelden

Öffnen Sie Ihr Shopify-Backend: `https://IHR-SHOP.myshopify.com/admin`

## 2. App-Bereich öffnen

Im linken Seitenmenü ganz unten: **Einstellungen** (Zahnrad-Symbol).

In der Einstellungen-Seite: **Apps und Vertriebskanäle**.

## 3. Custom App erlauben (einmalig)

Wenn dies der erste Custom App ist:

1. Oben rechts auf **„Apps entwickeln"** klicken.
2. Falls erforderlich: **„Custom-App-Entwicklung erlauben"** bestätigen.

## 4. Neue App erstellen

1. Auf **„Eine App erstellen"** klicken.
2. App-Name: **DropPilot**
3. App-Entwickler: Ihre eigene E-Mail
4. **„App erstellen"**

## 5. API-Berechtigungen konfigurieren

Auf der Konfigurationsseite: **„Admin-API-Integration konfigurieren"**.

Folgende Berechtigungen (Scopes) müssen aktiviert werden:

| Scope | Bereich |
| --- | --- |
| `read_products` | Produkte lesen |
| `write_products` | Produkte schreiben |
| `read_product_listings` | Produktlistings lesen |
| `read_orders` | Bestellungen lesen |
| `read_customers` | Kunden lesen |
| `read_inventory` | Bestand lesen |
| `write_inventory` | Bestand schreiben |
| `read_locations` | Standorte lesen |
| `read_fulfillments` | Versand lesen |
| `write_fulfillments` | Versand schreiben |
| `read_shipping` | Versandprofile lesen |

Tipp: Im Suchfeld nach jedem Scope-Namen suchen und Häkchen setzen.

**„Speichern"** klicken.

## 6. App installieren

Zurück zur App-Übersicht. Oben rechts: **„App installieren"** klicken.

## 7. Access-Token anzeigen und kopieren

Nach der Installation erscheint **einmalig** der **Admin-API-Access-Token**:

```
shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

⚠️ **Wichtig:** Diesen Token jetzt kopieren! Shopify zeigt ihn nur ein einziges Mal an. Wenn Sie die Seite schließen, müssen Sie einen neuen erstellen.

## 8. Token an DropPilot senden

Senden Sie folgende zwei Angaben an Ihren DropPilot-Ansprechpartner:

- **Shop-Domain:** `IHR-SHOP.myshopify.com`
- **Access-Token:** `shpat_...` (komplett)

Per E-Mail oder einem sicheren Messenger.

## 9. Fertig

Sobald wir den Token in DropPilot eingetragen haben, sind:

- Ihre Produkte automatisch synchronisiert
- Bestellungen direkt an unser PlentyMarkets-System weitergeleitet
- Versand und Zahlungsabwicklung über uns

Bei Fragen melden Sie sich jederzeit.
