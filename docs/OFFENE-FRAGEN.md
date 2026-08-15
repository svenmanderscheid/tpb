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

- [ ] Anzahlungsregel (ab Auftragswert X → Y %)
- [ ] Angebots-Gültigkeitsdauer in Tagen (Default `valid_until`)
- [ ] Rechtstexte v1 beauftragt (AGB, Datenschutz, Widerruf/Personalisierung, Datei-Erklärungen) – Datum:
- [ ] Anzahlungs-Wortlaut („keine Rechnung“-Formulierung, Fälligkeit) – Fiduciaire-Freigabe (Konzept 20.2)
- [ ] SMTP-Zugang (erst ab M3-Abnahme; bis dahin `MAIL_DRIVER=file`)

## Vor M6b – Shop & Zahlung

- [ ] Zahlungsanbieter-Entscheidung (gehostete Seite, signierte Webhooks, Gebühren, Auszahlungsrhythmus, Luxemburg-Zahlarten) – **Onboarding erst nach Gründung möglich**
- [ ] Versand: nur Abholung oder zusätzlich Pauschale (`SHIPPING_FLAT_CENTS`)
- [ ] Rechnungszeitpunkt bei Shop-Vollzahlung – Fiduciaire-Bestätigung (Konzept 20.10)

## Vor M8/M9 – Betrieb

- [ ] Hausbank + Exportformat (CSV-Spaltenbelegung oder CAMT.053) + Beispieldatei für `tests/fixtures/bank/`
- [ ] Mahnstufen: Intervalle, Textbausteine, Verzugsfolgen-Hinweis (juristisch geprüft)
- [ ] Verfügbare Produktionsminuten pro Woche (Konzept 20.4)

## Vor Go-live

- [ ] Gründungs-Checkliste Konzept 20.7 abgearbeitet (Rechtsform, Autorisation, AED/57bis, CCSS, Geschäftskonto, Betriebshaftpflicht)
- [ ] Seller-Snapshot-Daten eingetragen (Konzept 12.1) – **blockiert Go-live**
- [ ] Plattform-Entscheidung nach Konzept 17.2 formell dokumentiert (beide Gründer, Datum)

## Von Claude Code eingetragene Fragen

### M1 – Preis-Engine (§6): Klärungsbedarf, aktuell mit dokumentierten Default-Annahmen umgesetzt

- [ ] **EXTRA_COLOR_CENTS-Auslöser**: §6.3 nennt „ggf. EXTRA_COLOR_CENTS", ohne den Auslöser zu definieren. Aktuelle Annahme: je Position eine Basisfarbe frei, jede weitere Farbe = ein `extra_colors`-Zähler je Position × `EXTRA_COLOR_CENTS`. Der Zähler kommt aus der Konfiguration; Default 0. **Bitte fachliche Definition bestätigen** (Wann/womit entstehen Extrafarben? Pro Position oder pro Motiv?).
- [ ] **Kostenzuordnung (`cost_items.ref_type`)** für die interne Untergrenze: §6/§5.3 legen nicht fest, welcher Kostenparameter an welchem `ref_type` hängt. Aktuelle Annahme: `BLANK_CENTS`/`MATERIAL_CENTS` an `variant` (Fallback `product`); `SETUP_MIN`/`UNIT_MIN`/`MACHINE_MIN` an `technique` (Fallback `product`). **Bitte bestätigen oder verbindlich festlegen.**
- [ ] **M1-Fachwerte** (Preisbuch v1, price_params, Kostenversion v1) fehlen weiterhin (siehe Abschnitt „Vor M1"). Die Engine ist implementiert und tabellengetrieben getestet; die **echten Werte** werden über die Admin-UI/Seeds eingepflegt, sobald sie vorliegen – sie werden nicht erfunden.
