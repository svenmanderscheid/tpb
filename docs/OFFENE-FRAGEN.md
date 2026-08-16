# Offene Fragen & Fachwerte

Antworten direkt unter dem jeweiligen Punkt eintragen. `[ ]` offen · `[x]` beantwortet.
Claude Code liest diese Datei vor jedem Meilenstein und trägt eigene Fragen unten ein – niemals Werte erfinden (PROJECT.md §14.4).

## Vor M1 – Katalog & Preise (§15)

- [~] MVP-Produktliste (Owner 2026-08-16): **Hoodies und T-Shirts, Größen S/M/L/XL/XXL**. **Noch offen:** Farben, exakte SKUs, Staffelpreise, Kostenwerte.
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

## Vor M6-Abnahme – Rechnung (Owner/Fiduciaire)

- [ ] **USt-Regime bestätigen:** Franchise/Kleinunternehmer (Art. 57bis, 0 % USt) **oder** USt-pflichtig? M6 rechnet aktuell mit dem Platzhalter-Regime `FRANCHISE_57BIS` (0 % USt). Bei USt-Pflicht muss die Engine um Steuersätze erweitert werden.
- [ ] **Steuerlegende Volltext** (Art. 57bis, §11.5) – ersetzt den Platzhalter in `tax_regime_versions`.
- [ ] **Verkäufer-Snapshot** (Firmendaten/Adresse/Autorisation/Registernummern, Konzept 12.1) – ersetzt `business_settings.seller.snapshot`; **blockiert Go-live**.
- [ ] **Zahlungsziel** bestätigen (derzeit Platzhalter 30 Tage, `business_settings.invoice.due_days`).

## Vor M6b – Shop & Zahlung

- [ ] Zahlungsanbieter-Entscheidung (gehostete Seite, signierte Webhooks, Gebühren, Auszahlungsrhythmus, Luxemburg-Zahlarten) – **Onboarding erst nach Gründung möglich**
- [x] **Versand (Owner 2026-08-16):** Es wird versendet. **Premium-Lieferungen = Eigenlieferung** (selbst zugestellt, Hausetikett, kein Carrier). **Normale Lieferungen = günstigster externer Anbieter** (Carrier variabel, pro Sendung wählbar). Umsetzung: Versand-Grundgerüst + Hausetikett **jetzt** gebaut (Migration 051, DECISIONS #28); generische `CarrierAdapter`-Schnittstelle, „günstigster Anbieter" als Registry. **Noch offen für die Umsetzung:**
    - [x] **Carrier (Owner 2026-08-16):** **National = Post Luxembourg**, **International = DHL**. Echte Label/Tracking erst mit API-Zugang (nach Gründung); bis dahin Hausetikett.
    - [x] **Versandkosten (Owner 2026-08-16):** **im Preis einkalkuliert**; **ab 50 € Bestellwert nationaler Gratisversand**. → noch umzusetzen: Versandkosten-Regel in Preis/Checkout (national vs. international, Gratis-Schwelle 50 €). Kostenhöhe je Zone noch offen.
    - [x] **Autodruck gewünscht (Owner 2026-08-16):** Bei eingehender (bezahlter) Bestellung soll **automatisch ein Etikett gedruckt** werden. Braucht lokalen Druck-Agenten + Zieldrucker (Ausbaustufe); Systemseitig wird das Etikett bereits automatisch erzeugt – der stille Druck folgt mit dem Agenten.
- [ ] Rechnungszeitpunkt bei Shop-Vollzahlung – Fiduciaire-Bestätigung (Konzept 20.10)
- [x] **Bestandsanzeige „noch X auf Lager"** (Owner 2026-08-15/16): Bestand = Rohlinge je Variante, Reserve je Variante (Default 3, nie angezeigt/verkauft), Verfügbarkeit = max(0, Bestand − Reserve − offene Reservierungen). Umsetzung als eigenes **Lagermodul** (Migration 052, DECISIONS #31). **Owner-Antworten 2026-08-16:**
    - [x] **Konfigurierte Artikel bei knappem Bestand:** Shop zeigt „nur noch X", beim Konfigurieren **Warnung** bei geringem Bestand; **beim Bezahlen erneute Prüfung** (Reservierung, kein Oversell); **Abbuchung erst bei erfolgreicher Zahlung**; bei Abbruch/Ablauf Reservierung freigeben.
    - [x] **Meldebestand je Variante** gewünscht, **Benachrichtigung** bei Unterschreiten (Default 5), dann bei 2, dann leer. Dashboard-Seite mit Sofort-Übersicht; Schwellwert je Artikel **und** „für alle setzen".
    - [x] **Pflege:** vorerst **manuell**, später automatischer **Wareneingang** (Lieferschein).

## Vor M8/M9 – Betrieb

- [~] Hausbank + Exportformat (M8 umgesetzt mit **Standard-CSV + CAMT.053** und Fixtures in `tests/fixtures/bank/`). **Noch offen:** die **konkrete Spaltenbelegung der Hausbank** (falls abweichend vom Standard-CSV) + eine echte Beispieldatei.
- [~] Mahnstufen: **Stufe 1 (Erinnerung)** umgesetzt. **Noch offen:** weitere Stufen, Intervalle, Textbausteine, Verzugsfolgen-Hinweis (juristisch geprüft) – Konfig über `business_settings.dunning.levels` vorgesehen.
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
