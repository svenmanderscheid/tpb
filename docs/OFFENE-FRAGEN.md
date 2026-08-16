# Offene Fragen & Fachwerte

Antworten direkt unter dem jeweiligen Punkt eintragen. `[ ]` offen · `[x]` beantwortet.
Claude Code liest diese Datei vor jedem Meilenstein und trägt eigene Fragen unten ein – niemals Werte erfinden (PROJECT.md §14.4).

## Vor M1 – Katalog & Preise (§15)

- [ ] MVP-Produktliste: welche Produkte, Farben, Größen, SKUs (Schema `TSH-COT-BLK-M`)
- [ ] Standardsortiment: Hausdesigns als Assets, Platzierungen/Maße für `product_prints` (Konzept 20.11)
- [ ] Staffelpreise je Produkt in Cents (Preisbuch v1, inkl. Staffelgrenzen)
- [ ] `price_params`-Werte: Setup-Gebühr + Erlassmenge, Extra-Position, Extra-Farbe, Name/Nummer, Dateiaufbereitung, Express-Bps, Mindestbestellwert
- [ ] Kostenversion v1: Rohlingkosten je Variante, Zeitwerte (Setup/Stück/Maschine), Stundensätze, Ausschuss-Bps, Zielmarge-Bps (mit Fiduciaire plausibilisieren)

## Vor M3 – Angebot & Auftrag

> **Geklärt (Owner 2026-08-16):** Die **Angebotsanfrage ist nur für Clubs / größere Bestellungen**. Einzelbestellungen sieht der Kunde sofort im Konfigurator und **zahlt sofort** (Pfad B, Shop-Checkout M6b). Siehe DECISIONS #22. M3 baut ausschließlich den Angebots-/Auftragsweg (Pfad A).

- [ ] Anzahlungsregel (ab Auftragswert X → Y %) — **M3-Default bis dahin: `deposit_required_cents = 0`** (keine Anzahlungsaufforderung); sobald die Regel vorliegt, greift der `deposit_requests`-Zweig automatisch.
- [ ] Angebots-Gültigkeitsdauer in Tagen (Default `valid_until`) — **M3-Platzhalter: 14 Tage** (`business_settings.reminder.quote_expiry_days`), jederzeit im Admin änderbar; kein erfundener Fachwert, nur ein sichtbarer Default.
- [ ] Rechtstexte v1 beauftragt (AGB, Datenschutz, Widerruf/Personalisierung, Datei-Erklärungen) – Datum: __ — **M3 nutzt bis dahin markierte Platzhalter** (`v0-PLATZHALTER`, DECISIONS #25).
- [ ] Anzahlungs-Wortlaut („keine Rechnung“-Formulierung, Fälligkeit) – Fiduciaire-Freigabe (Konzept 20.2)
- [ ] SMTP-Zugang (erst ab M3-Abnahme; bis dahin `MAIL_DRIVER=file`) — **lokal aktiv `MAIL_DRIVER=file`**, Angebots-/Auftragsmails landen als `.eml` in `private/tpb/outbox-mails/`.

## Vor M6b – Shop & Zahlung

- [ ] Zahlungsanbieter-Entscheidung (gehostete Seite, signierte Webhooks, Gebühren, Auszahlungsrhythmus, Luxemburg-Zahlarten) – **Onboarding erst nach Gründung möglich**
- [x] **Versand (Owner 2026-08-16):** Es wird versendet. **Premium-Lieferungen = Eigenlieferung** (selbst zugestellt, Hausetikett, kein Carrier). **Normale Lieferungen = günstigster externer Anbieter** (Carrier variabel, pro Sendung wählbar). Umsetzung: Versand-Grundgerüst + Hausetikett **jetzt** gebaut (Migration 051, DECISIONS #28); generische `CarrierAdapter`-Schnittstelle, „günstigster Anbieter" als Registry. **Noch offen für die Umsetzung:**
    - [ ] **Carrier-Zugang(e)** für Standardversand (welche Anbieter kommen in die „günstigster"-Auswahl, API/Label-Format, Vertrag) – echte Carrier-Label + Tracking erst mit Zugang (wie Zahlung: nach Gründung).
    - [ ] **Versandkosten-Logik** für Standardversand: feste Pauschale (`SHIPPING_FLAT_CENTS`) oder gewichts-/carrierabhängig? Bis dahin manuelle Kosteneingabe je Sendung.
    - [ ] **Autodruck** (still) für Job-/Versandetiketten: lokaler Druck-Agent (QZ Tray/Print-Server) + Zieldrucker – spätere Ausbaustufe (§9.6 Stufe 1 bleibt bis dahin).
- [ ] Rechnungszeitpunkt bei Shop-Vollzahlung – Fiduciaire-Bestätigung (Konzept 20.10)
- [x] **Bestandsanzeige „noch X auf Lager"** (Owner 2026-08-15): Bestand = Rohlinge je Variante, Reserve je Variante (Default 3), Verfügbarkeit = max(0, Bestand − Reserve), Verkaufssperre bei ≤ 0. Umsetzung in **M6b** (siehe DECISIONS #21). **Noch offen für die Umsetzung:**
    - [ ] Verhalten bei **konfigurierten** Artikeln, wenn der Rohling-Bestand knapp ist: nur Warnung oder harte Sperre?
    - [ ] Meldebestand/Nachbestell-Schwelle je Variante gewünscht (für die „Heute"-Liste in M8)?
    - [ ] Wird Bestand manuell gepflegt oder soll ein Rohling-Wareneingang (Lieferschein) erfasst werden?

## Vor M8/M9 – Betrieb

- [ ] Hausbank + Exportformat (CSV-Spaltenbelegung oder CAMT.053) + Beispieldatei für `tests/fixtures/bank/`
- [ ] Mahnstufen: Intervalle, Textbausteine, Verzugsfolgen-Hinweis (juristisch geprüft)
- [ ] Verfügbare Produktionsminuten pro Woche (Konzept 20.4)

## Vor Go-live

- [ ] Gründungs-Checkliste Konzept 20.7 abgearbeitet (Rechtsform, Autorisation, AED/57bis, CCSS, Geschäftskonto, Betriebshaftpflicht)
- [ ] Seller-Snapshot-Daten eingetragen (Konzept 12.1) – **blockiert Go-live**
- [ ] Plattform-Entscheidung nach Konzept 17.2 formell dokumentiert (beide Gründer, Datum)

## InkTracker-Vergleich (Owner-Wunsch 2026-08-16, Details in docs/INKTRACKER-VERGLEICH.md)

- [ ] **Welcher konkrete InkTracker-Screen** ist vorbildlich? Belegbar ist ein Preis-Wizard, **kein** grafischer Designer (TPB ist hier bereits überlegen). Antwort steuert, wie stark wir die M2-Preis-Transparenz ausbauen.
- [ ] **Vereins-/Broker-Portal** (wiederkehrende Kunden sehen eigene Angebote/Aufträge) gewünscht? (Adaption #6, M8+)
- [ ] **Lieferanten-Preisimport** (Adaption #4): welche LU/EU-Lieferanten, welches CSV-Exportformat? → speist neue `cost_version` mit Diff-Vorschau.

## Von Claude Code eingetragene Fragen

### M1 – Preis-Engine (§6): Klärungsbedarf, aktuell mit dokumentierten Default-Annahmen umgesetzt

- [ ] **EXTRA_COLOR_CENTS-Auslöser**: §6.3 nennt „ggf. EXTRA_COLOR_CENTS", ohne den Auslöser zu definieren. Aktuelle Annahme: je Position eine Basisfarbe frei, jede weitere Farbe = ein `extra_colors`-Zähler je Position × `EXTRA_COLOR_CENTS`. Der Zähler kommt aus der Konfiguration; Default 0. **Bitte fachliche Definition bestätigen** (Wann/womit entstehen Extrafarben? Pro Position oder pro Motiv?).
- [ ] **Kostenzuordnung (`cost_items.ref_type`)** für die interne Untergrenze: §6/§5.3 legen nicht fest, welcher Kostenparameter an welchem `ref_type` hängt. Aktuelle Annahme: `BLANK_CENTS`/`MATERIAL_CENTS` an `variant` (Fallback `product`); `SETUP_MIN`/`UNIT_MIN`/`MACHINE_MIN` an `technique` (Fallback `product`). **Bitte bestätigen oder verbindlich festlegen.**
- [ ] **M1-Fachwerte** (Preisbuch v1, price_params, Kostenversion v1) fehlen weiterhin (siehe Abschnitt „Vor M1"). Die Engine ist implementiert und tabellengetrieben getestet; die **echten Werte** werden über die Admin-UI/Seeds eingepflegt, sobald sie vorliegen – sie werden nicht erfunden.
