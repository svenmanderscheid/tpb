# Changelog

## 2026-08-14 – Projektgrundlage
- Initialer Commit: CLAUDE.md, TPB-Pure-PROJECT.md v1.4, Systemkonzept v1.5, Begleitdateien
- Noch kein Code – nächster Schritt: Meilenstein M0 (Branch `m0-fundament`, Kickoff-Prompt in PROJECT.md Anhang A)

## 2026-08-14 – Lokale Umgebung eingerichtet
- Branch: `main` (Setup vor M0)
- Git-Repo initialisiert, Remote `origin` gesetzt, erster Commit; Push offen (SSH-Host-Key-Verifikation schlug fehl → manuell via GitHub Desktop)
- Datenbanken `tpb_dev` und `tpb_test` (utf8mb4/unicode_ci) angelegt
- Apache-VirtualHost `tpb.local` → `public_html/` ergänzt (Backup `httpd-vhosts.conf.bak-2026-08-14`); hosts-Eintrag `127.0.0.1 tpb.local` gesetzt
- `.env` aus `.env.example` erstellt (XAMPP-Standard: root/leer, `APP_ENV=local`, `MAIL_DRIVER=file`)
- Composer 2.10.2 installiert (offizieller getcomposer.org-Installer, Signatur verifiziert; winget-Paket nicht mehr verfügbar) nach `C:\xampp\php`, Wrapper `composer.bat`, `C:\xampp\php` im User-PATH
- Umgebung: PHP 8.2.12 (laut PROJECT.md §3 zulässiger Fallback für 8.3 – keine Syntax > 8.2 ohne Rückfrage)
- Offen: `git push` (Auth), Apache-Neustart durch Nutzer für aktive `tpb.local`-Auflösung

## 2026-08-14 – M0 Fundament
- Branch: `m0-fundament`
- **Projektskelett** nach §2 (public_html, app/Core, app/Domain, app/Http, app/Views, private/, migrations, cli, tests), `composer.json` (PSR-4 `Tpb\`, Whitelist §1.1: phpmailer + phpunit; PDF/QR/Barcode folgen je Meilenstein), `phpunit.xml`
- **Core-Module** (§4): Env, Db (PDO-Wrapper + tx), Clock (UTC), Ulid (monoton, getestet), Canonical (kanonisches JSON), Money (Cents/Basispunkte, round-half-up), Request, Response (Security-Header/CSP), Router (+Middleware public/auth/csrf/rate), View, ErrorHandler (Correlation-ID), Csrf, RateLimit, Auth (Session-Härtung, Idle/Absolut-Timeout), Authz (Capability-Matrix), Audit (append-only)
- **Migration** `001_init.sql` exakt nach §5.2 (13 Fachtabellen + `schema_migrations`)
- **CLI**: `migrate.php` (Checksum-Schutz, `--status`/`--dry-run`), `user_create.php` (erster Owner nur per CLI), `backup.php` (mysqldump.gz + tar.gz von private/), `seed.php`-Grundgerüst (Env-Guard, Szenario-Dispatch), `outbox_worker.php`
- **Auth/Login + leeres Admin-Dashboard**; CSP `default-src 'self'`, CSRF auf mutierenden Routen, DB-basiertes Login-Rate-Limit
- **Asset-Upload** (§8): Whitelist svg/pdf/png/jpg → finfo → Größen-/Pixellimit → SHA-256 → Speicherung unter `artwork/{owner}/{ulid}/original` → Quarantäne → Preflight → GD-PNG-Thumbnail → clean; SVG mit aktiven Inhalten wird abgelehnt; **gesicherter Download** `/files/{publicId}` (Auth oder kurzlebiges Token, immer `attachment`, nie inline)
- **Outbox + Worker** (§10): Enqueue in derselben Transaktion, Mailer (file-Driver schreibt .eml via PHPMailer), GET_LOCK, Backoff 1/5/15/60, nach 5 Versuchen `failed` + Audit-Alarm
- **Tests** (§13): 32 Tests grün – Ulid, Canonical, Money, Capability-Matrix (jede Rolle × Capability), Upload-Preflight (falsche Endung/MIME/Übergröße/SVG-Script + Audit), Login-Audit
- `composer audit`: keine Advisories
- **DoD M0 vollständig verifiziert** (kein Web-Bootstrap, idempotente Migration auf leerer DB, Upload→Quarantäne→Download, private/ per Web = 404, Audit bei Login/Upload, Restore-fähiges Backup einmal zurückgespielt)
- **.gitignore-Korrektur** (`*.sql`→`*.sql.gz`) + `.gitattributes` (LF-Normalisierung); Details in `docs/DECISIONS.md`
- Offen (blockiert M0 nicht): `git push` (SSH-Auth), Apache-Neustart; Fachwerte für M1 stehen in `docs/OFFENE-FRAGEN.md`

## 2026-08-14 – Finanzmodul (Branch feature/finanzen, Abweichung)
- **Bewusste Abweichung** von der Meilenstein-Reihenfolge auf Owner-Wunsch (Details + Begründung in `docs/DECISIONS.md` #14-18)
- Migration `050_finance.sql`: `finance_partners` (Gründer + Gewinnanteil in Basispunkten), `capital_contributions` (Kapitaleinlagen), `finance_entries` (Einnahmen/Ausgaben in Cents); Nummer 050 hält 002–006 für die Roadmap frei
- Neue Capability `tpb_manage_finance` (owner/finance) für Schreibaktionen; Lesen über `tpb_view_costs`
- Domain `Finance`: `PartnerRepo`, `ContributionRepo`, `EntryRepo`, `FinanceReport` (Einnahmen/Ausgaben/Gewinn, Monatsreihen, Kategorien, Gewinnverteilung), `Amount` (Cents/Basispunkte-Parser ohne Floats)
- Seiten unter `/admin/finanzen`: Dashboard (KPIs + SVG-Statistiken + Jahr-Filter), Buchungen (erfassen/löschen), Gesellschafter (Gründer, Anteile, Kapitaleinlagen); Navigation im Layout
- **Diagramme** als eigenes ES-Modul `finance-charts.js` aus einem JSON-Datenblock – Inline-SVG, keine Bibliothek/CDN, CSP `default-src 'self'` unangetastet; CSP-konforme UI-Helfer in `admin.js` (Auto-Submit, Lösch-Rückfrage)
- Jede Mutation: CSRF + Capability + Audit; Geld als Cents, Anteile als Basispunkte
- Tests: +17 (`AmountTest`, `FinanceReportTest` inkl. negativer Gewinn) – gesamt **49 grün**
- End-to-End im Browser verifiziert (Charts rendern, keine CSP-Verstöße)

## 2026-08-14 – M1 begonnen: Katalog-Schema & Preis-Engine (Branch m1-katalog-preis)
- `main` per Fast-Forward auf M0 + Finanzmodul gebracht und gepusht
- Migration `002_catalog_pricing.sql` nach §5.3 (products, product_variants, techniques, placements, product_prints, price_books, price_tiers, price_params, cost_versions, cost_items; FKs ON DELETE RESTRICT, Maße DECIMAL(6,1))
- **Preis-Engine (§6)** `Domain/Pricing`: `PriceBook`/`CostVersion` (Value Objects), `PriceEngine::calculate` (Verkaufspreis + interne Untergrenze), `Breakdown` (kanonisierbar, `calc_hash`), `PricingException`; reine Int-Arithmetik, keine DB-Zugriffe
- Tabellengetriebene Engine-Tests (M1-DoD): Staffelgrenzen 4/5, 9/10, 24/25, 49/50; Setup-Waiver; Express-Rundung; `below_min_order`; `below_floor`; Standardartikel ohne Zuschläge; Mischentwurf; deterministischer `calc_hash` → **67 Tests grün**
- **Dokumentierte Annahmen** (in `docs/OFFENE-FRAGEN.md`, Owner-Bestätigung nötig): EXTRA_COLOR-Auslöser, `cost_items.ref_type`-Zuordnung
- **Offen für M1-Abschluss**: Admin-CRUD (Produkte/Varianten/Techniken/Placements), Preisbuch-/Kostenversions-Verwaltung mit Draft→Publish, Seed „Basissortiment" (braucht echte Fachwerte des Owners)

## 2026-08-14 – M1 Backend: Katalog- & Preis-Verwaltung
- **Katalog-CRUD**: `Domain/Catalog` (ProductRepo, VariantRepo, TechniqueRepo, PlacementRepo); Seiten `/admin/katalog` (Produkte, Varianten, Druckpositionen) und `/admin/techniken`
- **Preisbuch-Verwaltung** `/admin/preisbuecher`: Staffeln + Parameter, **Draft→Publish**; nach Veröffentlichung sind Staffeln/Parameter unveränderlich (serverseitig erzwungen → HTTP 403, per Smoke-Test bestätigt)
- **Kostenversions-Verwaltung** `/admin/kostenversionen`: Sätze (Lohn/Maschine/Ausschuss/Zielmarge) + Kostenpositionen, Draft→Publish
- Rechte: `tpb_manage_pricing` (owner/admin) auf allen Routen; CSRF + Audit auf jeder Mutation; Geld als Cents, Prozente als Basispunkte
- Navigation um „Katalog" und „Preise" erweitert; gemeinsamer `FlashTrait`
- End-to-End über Apache verifiziert (Produkt→Variante→Position→Technik→Preisbuch mit Staffeln+Parameter→Publish→Immutability-403→Kostenversion→Publish); 67 Tests grün
- **Hinweis**: DB→Value-Object-Loader für die Live-Preisberechnung sowie Seed „Basissortiment" folgen in M2; Publish-Sudo-Modus (§11) ist als TODO für M7 markiert

## 2026-08-14 – M2-Grundlage: Live-Preis-API + Testobjekt
- **`cli/seed.php --scenario=test`**: legt ein Testobjekt (Produkt „Testobjekt", Variante, Position, Technik FLEX) samt **veröffentlichtem Preisbuch v1** (Staffeln + Parameter) und **Kostenversion v1** an – Testwerte, idempotent
- **`PricingRepo`**: lädt das aktuell veröffentlichte Preisbuch/die Kostenversion in die Engine-Value-Objects (§6: Repos laden vorab, Engine rechnet DB-frei)
- **`POST /api/price`** (öffentlich, `Http/Api`): serverseitiger Live-Preis – akzeptiert Konfiguration mit externen Schlüsseln (Produkt-`public_id`, Varianten-SKU, Technik-Code), rechnet gegen das veröffentlichte Preisbuch, gibt Breakdown + `calc_hash` zurück; **kein Client-Betrag**; Rate-Limit-Bucket `price`
- End-to-End über Apache verifiziert (10 Stück FLEX = 347,50 €, `below_floor` bei Kleinstauftrag, unbekanntes Produkt → 400); Integrationstest `PricingRepoTest` → **69 Tests grün**
- Namespace-Entscheidung `Http/Api` statt `Http/Public` (`public` ist reserviert) in `docs/DECISIONS.md` #19; `price_calculations`-Persistenz folgt mit Migration 003
- **Offen für M2**: Migration 003 (Konfigurations-Tabellen + `price_calculations`), öffentlicher Konfigurator (Vanilla-JS: Produkt→Farbe→Größenmatrix→Upload→Placement-Presets→Live-Preis), Entwurf speichern/laden

## 2026-08-15 – M2: Migration 003 + Konfigurations-Persistenz
- **Migration `003_config_customer_quote.sql`** (§5.4): customers, customer_consents, configurations, configuration_items, configuration_item_sizes, configuration_layers, configuration_units, price_calculations, quotes, quote_items (FKs ON DELETE RESTRICT; `quotes.amends_order_id` bewusst ohne FK, da `orders` erst in 004)
- **`Domain/Config/ConfigMapper`**: externe Schlüssel (Produkt-`public_id`, Varianten-SKU, Technik-Code, Placement-Code, Asset-`public_id`) → interne IDs; **Positionen und Motive werden serverseitig aus den Layern abgeleitet** (Client bestimmt sie nicht)
- **`ConfigurationRepo`**: Entwurf speichern/aktualisieren (Positionsdaten in mm) + laden über `public_id`; `persistCalculation` schreibt jede Berechnung nach `price_calculations`
- **Konfigurations-API** (`Http/Api`): `POST /api/config/save` (serverseitige Preisberechnung + Persistenz), `GET /api/config/{publicId}` (Round-trip), `POST /api/config/upload` (Gast-Logo-Upload über dieselbe Preflight-/Quarantäne-Pipeline); `/api/price` nutzt jetzt denselben Mapper
- Tests: `ConfigSaveTest` (Ableitung Positionen/Motive, Preis→Speichern→Laden, Update ersetzt Positionen) → **72 Tests grün**; End-to-End über Apache verifiziert (Upload→Save 242,50 €→Load)
- **Offen für M2**: öffentliche Konfigurator-Seite (Vanilla-JS-UI) und Standardprodukt-Seite

## 2026-08-15 – M2: Öffentlicher Konfigurator (Vanilla-JS)
- **`Http/Site/SiteController`** + Views: `GET /konfigurator` (Produktliste), `GET /konfigurator/{publicId}` (Konfigurator); Produkt-/Varianten-/Placement-/Technik-Daten als JSON-Datenblock eingebettet
- **`assets/js/configurator.js`** (CSP-konform, keine Inline-Skripte, kein CDN): Größenmatrix, Technikwahl, Design-Layer mit Logo-Upload + Feinjustierung in mm, Personalisierungsliste, Express; **debounced Live-Preis über `/api/price`**; Entwurf speichern (`/api/config/save`) mit teilbarem Link; Entwurf laden über `?draft=<public_id>`; Standardprodukte ohne Design/Technik
- Konfigurator-Layout im CSS (sticky Preis-Panel), gemischtes Admin/Site-Styling wiederverwendet
- **Im Browser end-to-end verifiziert**: Formularaufbau, Live-Preis (10 Stück = 180,00 €), Upload, Speichern→Link, **Entwurf per Link wiederhergestellt** (Menge + Preis), keine Konsolen-/CSP-Fehler
- Damit M2-DoD-Kern erfüllt: Reload/anderes Gerät stellt Entwurf her; Client-Preismanipulation wirkungslos (Server rechnet); Positionsdaten in mm; Upload-Pipeline vollständig
- 72 Tests grün; alles auf `m1-katalog-preis`

## 2026-08-16 – M3: Kunde & Angebot (Branch m1-katalog-preis)
- **Fachliche Klärung (Owner):** Angebotsanfrage nur für **Clubs/Großbestellungen** (Pfad A); Einzelbestellungen zahlen sofort im Shop (Pfad B, M6b, blockiert auf Zahlungsanbieter). Siehe DECISIONS #22.
- **Migration 004** `order_proof_production_invoice.sql` vollständig nach §5.5 (orders, order_items, order_item_units, order_terms_acceptance, artwork_versions, proofs, proof_approvals, production_jobs, production_events, invoices, invoice_lines, payments, payment_allocations, deposit_requests, print_jobs) – M3 nutzt Order-/Terms-Teil, Rest ab M4–M6
- **Infrastruktur:** `NumberSequence` (§7-Referenz, lückenfrei), `States`+`Status` (alle Statusachsen §7, `status_events`), `PdfService` (dompdf **3.1.6** gehärtet §9: `isRemoteEnabled=false`, chroot, DejaVu), `AssetService::storeGenerated` (interne PDFs)
- **Angebots-Domäne:** `CustomerRepo`, `LegalDocRepo`, `AccessTokenService` (SHA-256-Token, TTL/Widerruf), `QuoteService` (Snapshot einfrieren → `send` [Q-Nummer, PDF, Token, Outbox-Mail] → `accept`/`decline`), `QuoteRepo`, `OrderRepo` (Order aus Snapshot, Achse→CONFIRMED)
- **Annahme idempotent:** genau eine Order je Angebot (FOR UPDATE + Statusprüfung); abgelaufenes Angebot wird EXPIRED gesetzt und abgewiesen; ungültiges/abgelaufenes Token abgewiesen
- **HTTP öffentlich:** Startseite `/`, Produktübersicht `/produkte`, Rechtstexte `/rechtliches[/{typ}]`, Angebotsanfrage `/anfrage` (Kontakt + versionierte Consents), Kundenansicht `/angebot/{publicId}?t=` mit Annahme/Ablehnung + PDF-Stream; Konfigurator-Seitenleiste verlinkt „Angebot anfragen"
- **HTTP Admin:** `/admin/anfragen` (offene Anfragen + Angebote), Anfrage→Angebot, Angebots-Detail, Versand; neue Capability `tpb_manage_quotes` (owner/admin/sales)
- **Mail-Templates** (quote_sent, order_confirmed) über Outbox; **Seed** `--scenario=m3` (Platzhalter-Rechtstexte published, `quote_expiry_days=14`)
- **Tests:** `QuoteFlowTest` (Idempotenz, Snapshot-Unveränderlichkeit gegen Preisbuchänderung, Token-/Ablauf-Abweisung); Capability-Matrix erweitert → **77 Tests grün**, `composer audit` sauber
- **Browser-End-to-End verifiziert:** Anfrage → Admin erstellt Angebot Q-2026-000001 → Versand (Kundenlink + PDF, .eml in Outbox) → Kunde nimmt an → Order **ORD-2026-000001** (eine Order, Terms×4 protokolliert, Status-Events sauber), keine CSP-/Konsolenfehler
- **DoD M3 erfüllt:** genau eine Order je Annahme; Preisbuchänderung nach Versand ändert das Angebot nicht (Snapshot); PDF = Snapshot-Inhalt; abgelaufenes/widerrufenes Token abgewiesen. Öffentliche Basisseiten stehen.
- Offen (blockiert M3 nicht): echte Rechtstexte/Seller-Daten (Platzhalter), Anzahlungsregel (Default 0), SMTP (file-Driver). InkTracker-Vergleich in `docs/INKTRACKER-VERGLEICH.md`.

## 2026-08-16 – M4: Proof & Freigabe (Branch m1-katalog-preis)
- **Proof-Domäne:** `ArtworkRepo` (versionierte finale Druckdateien je Auftrag), `ProofRepo`, `ProofService`
  (`addArtwork` → MISSING→UPLOADED; `createAndSend` → Proof-PDF, Token, Outbox-Mail, Artwork-Achse →PROOF_SENT;
  `approve`/`requestChanges` per Token)
- **Nur konfigurierte Positionen** durchlaufen den Proof; Standardartikel starten bereits bei LOCKED (v1.4, in `OrderRepo::createFromQuote`)
- **DoD erfüllt:** Freigabe referenziert exakt eine Proof-Version (Token an `proof_id` gebunden, PDF-SHA-256 im Audit); eine neue Version setzt die alte auf `superseded` und macht den alten Link ungültig (neue Freigabe nötig); Freigabe/Änderung mit Zeit/Actor/Token in `proof_approvals`; Freigabe sperrt Artwork (LOCKED)
- **HTTP Admin:** `/admin/auftraege` (Liste), `/admin/auftrag/{publicId}` (Positionen, Artwork-Versionen, Proofs, Upload, „Proof erstellen & versenden"); Recht `tpb_manage_artwork`; Nav „Aufträge"
- **HTTP Kunde:** `/proof/{orderPublicId}?t=` mit Freigabe/Änderung + Proof-PDF-Stream (token-gebunden an die aktive Version)
- **Proof-PDF-View** (Produktionsspezifikation: Positionen + Maße in mm aus dem Config-Snapshot); Mail-Template `proof_sent`
- **Tests:** `ProofFlowTest` (Freigabe→LOCKED mit protokollierter Freigabe; neue Version löst ab + alter Link abgewiesen; LOCKED nicht erneut proofbar; ungültiges Token) → **81 Tests grün**, `composer audit` sauber
- **HTTP end-to-end verifiziert (curl, echte CSRF/Session/Token):** Kunden-Proofansicht → Freigabe → Auftrag LOCKED, Freigabe protokolliert; Admin-Auftragsseiten 200 mit „gesperrt"-Badge + PDF-Links; Proof-Mail in der Outbox gerendert
- Offen (blockiert M4 nicht): gerasterte Motivvorschau im Proof-PDF (derzeit Maßangaben); Produktionsjobs (M5) entstehen aus LOCKED-Aufträgen

## 2026-08-16 – M5: Produktion & Etikett (Branch m1-katalog-preis)
- **Whitelist-Libs (§1.1):** `chillerlan/php-qrcode` 6.0.1, `picqer/php-barcode-generator` 3.2.4 (composer audit sauber). QR/Code128 als lokale PNG-Data-URIs (DECISIONS #26)
- **Produktions-Domäne:** `JobRepo` (ein Job je order_item, Nummern `JOB-…`), `ProductionService`
  (Job-Anlage, Freigabe-**Gate** §7.3, Statusfluss BLOCKED→READY→IN_PROGRESS→QUALITY_CHECK→DONE inkl. REWORK/SCRAPPED,
  Mengen-/Ausschusserfassung **idempotent** über `production_events.idem_key`)
- **Etikett:** `Label\Barcode` (QR + Code128), `Label\LabelService` (Jobetikett-PDF 62×100 mm via `PdfService::renderLabel`,
  `print_jobs`-Protokoll, **Neudruck nur mit Grund**), GD-Testrender in 203/300 dpi (`cli/label_test.php`)
- **HTTP Admin:** `/admin/produktion` (Warteschlange), `/admin/job/{publicId}` (Statusbuttons, Mengen, Etikett/Neudruck),
  „Produktionsjobs anlegen" am Auftrag; Recht `tpb_manage_production`; Nav „Produktion"
- **HTTP Werkstatt:** `/scan/{jobPublicId}?t=` (QR-Token, read-only Job-Statuskarte)
- **DoD erfüllt:** Job ohne erfülltes Gate bleibt BLOCKED (Artwork nicht LOCKED → Freigabe verweigert); doppelter
  Mengen-POST mit gleichem `idem_key` bucht nicht doppelt; Etikett in 203/300 dpi lesbar mit scanbarem QR/Code128;
  Neudruck nur mit Grund (`reprint_of_id` + `reprint_reason`); Standardartikel gelten am Gate als vorab freigegeben
- **Tests:** `ProductionFlowTest` (3) → **84 Tests grün**, `composer audit` sauber
- **Verifiziert:** CLI-Etikett-Render (JOB-2026-000001, 496×799 / 732×1181 px) visuell geprüft; Admin-Produktionsseiten
  200 (READY-Badge, Start-Button); Scan-Route weist ungültiges Token mit 404 ab

## 2026-08-16 – Versand & Fulfillment vorgezogen (Owner-Wunsch, Branch m1-katalog-preis)
- **Owner-Entscheidung:** Es wird versendet – **Premium = Eigenlieferung** (Hausetikett, kein Carrier), **Standard = günstigster externer Anbieter** (Carrier variabel). Echte Carrier-Anbindung + Tracking + stiller Autodruck bleiben extern blockiert (Zugang wie Zahlung erst nach Gründung). Siehe DECISIONS #28, OFFENE-FRAGEN.
- **Migration 051** `shipments` (Out-of-band-Block, da 005/006 reserviert): Versandart, Carrier, Lieferadresse-Snapshot, Kosten, Tracking, Etikett-Asset, Zeitstempel (eine Sendung je Auftrag)
- **Domäne `Shipping`:** `CarrierAdapter`-Schnittstelle + `HouseCarrier` + `CarrierRegistry::cheapest` („günstigster Anbieter"), `ShipmentRepo` (Adresse aus Kundenstamm), `ShippingService` (Versandart/Kosten, Fulfillment-Statusfluss §7 Abholung **oder** Versand, Hausetikett 100×150 mm mit Empfänger + Auftrags-Barcode/QR, Neudruck nur mit Grund)
- **HTTP Admin:** Versand-Karte am Auftrag (Versandart, Lieferadresse, Kosten, Statusbuttons pack→ready→ship/collect→deliver, Etikett rendern/neu); Recht `tpb_manage_production`
- **Tests:** `ShippingFlowTest` (5) → **89 Tests grün**, `composer audit` sauber
- **Verifiziert:** HTTP-Flow (configure → pack → ship → Etikett) über echte CSRF/Session; Hausetikett-PDF (100×150 mm, einseitig) visuell geprüft
- **Fix:** feste `height` an Etiketten-Views entfernt (verhinderte leere zweite PDF-Seite bei Job- und Versandetikett)

## 2026-08-16 – M6: Rechnung & Plan/Ist (Branch m1-katalog-preis)
- **Migration 005** `expenses` (genutzt) + bank_imports/bank_lines/reminders_sent (für M8 vorab, §14.5)
- **Rechnungsnummer** `2026-000001` (eigene Sequenz `invoice`, ohne Präfix, §9.3) via `NumberSequence::nextInvoice`
- **Issue-Flow §11.2:** `InvoiceService` (Draft aus Auftrag → `issue`: Nummer ziehen, Seller/Customer/Tax/Lines einfrieren, **kanonisches JSON und PDF aus DEMSELBEN Snapshot**, beide als Assets + SHA-256, Status **ISSUED unumkehrbar**); Steuer aus `tax_regime_versions` (Platzhalter Franchise Art. 57bis → 0 % USt, DECISIONS #29)
- **Gutschrift:** eigener Beleg `credit_note` mit `credited_invoice_id`, negierten Positionen, eigener Nummer; Original → FULLY_CREDITED
- **Zahlung:** `PaymentService` erfasst + ordnet automatisch zu; Zahlungsachse **abgeleitet** (NOT_DUE/UNPAID/PARTIALLY_PAID/PAID); Umsatz ≠ Zahlungseingang getrennt
- **Ausgaben:** `ExpenseRepo` + Erfassung mit Beleg-Upload + optionaler Auftragszuordnung
- **Plan/Ist-Report je Auftrag** (`OrderFinanceReport`, §11.7 vereinfacht): Umsatz/Fakturiert/Eingang, Selbstkosten-Untergrenze (Plan), DB, Ist-Minuten/Ausschuss aus `production_events`
- **HTTP Admin:** Rechnungen-Liste/Detail (Ausstellen/Gutschrift/PDF), Auftrag: Rechnung/Zahlung/DB-Karten, Ausgaben-Seite; Nav „Rechnungen"/„Ausgaben"; Rechte tpb_issue_invoices/tpb_view_costs/tpb_manage_finance
- **Seed** `--scenario=m6` (Platzhalter-Steuerregime + Verkäufer-Snapshot + Zahlungsziel 30 Tage)
- **DoD erfüllt:** zwei PDO-Verbindungen ⇒ keine Doppelnummer; ISSUED nicht editierbar; Snapshot/PDF/JSON-Summen identisch (SHA-256); Umsatz≠Zahlungseingang getrennt sichtbar; Gutschrift referenziert korrekt
- **Tests:** `InvoiceFlowTest` (6) → **95 Tests grün**, `composer audit` sauber
- **Verifiziert:** Rechnungs-PDF (2026-000001, 190,00 €, einseitig, Steuerlegende) visuell geprüft; Teilzahlung → PARTIALLY_PAID; Admin-Rechnungsseiten 200
- Offen (blockiert M6 nicht, siehe OFFENE-FRAGEN): USt-Regime-Bestätigung, echte Steuerlegende/Verkäuferdaten (Platzhalter)

## 2026-08-16 – M6b: Shop-Checkout & Online-Zahlung (Test-Adapter, Branch m1-katalog-preis)
- **Owner-Entscheidung:** Sofortkauf (Pfad B) mit **Test-Adapter**; echter Anbieter später nur ein weiterer Gateway-Adapter (KYC nach Gründung). DECISIONS #30.
- **Migration 006** `payment_intents` + `payment_webhook_events` (§5.7)
- **Gateway-Abstraktion** `Domain/Payment/Gateway` (`PaymentGateway`/`TestGateway`/`GatewayFactory`); HMAC-signierte Webhooks (`PAYMENT_WEBHOOK_SECRET`)
- **`CheckoutService`:** Preis serverseitig neu berechnet (Client-Betrag wirkungslos), Order **PENDING_PAYMENT** + Positionen/Einheiten + Rechtserklärungen + `payment_intents` in EINER Transaktion; Pflicht-Zustimmungen serverseitig erzwungen
- **`ShopWebhookService` (§5.7):** Signatur zuerst → Rohevent als Beleg → Betrag/Währung-Abgleich (Mismatch ⇒ Alarm-Audit, keine Buchung) → **Idempotenz doppelt** (UNIQUE(provider,event_ref) + `shop_paid_<order>`) → Rechnung ausstellen + Zahlung buchen (→ PAID) + Order **CONFIRMED** + Outbox-Mails
- **Refactor:** Snapshot-Builder in `OrderSnapshot` extrahiert (geteilt Angebot/Checkout); `OrderRepo::createFromSnapshot` mit variablem Startzustand; Order-Achse aus `status_events`
- **`cli/expire.php`:** PENDING_PAYMENT-Orders (nach `CHECKOUT_TTL_HOURS`) + abgelaufene Angebote → EXPIRED
- **HTTP:** Checkout-Seite (Endpreis, Gastdaten, Pflicht-Checkboxen inkl. Widerruf-Erlöschen, „zahlungspflichtig bestellen"), simulierte Bezahlseite, Webhook-Endpunkt; Admin **Shop-Zahlungen** (Intents + Webhook-Events); Konfigurator-Button „Jetzt kaufen"
- **DoD erfüllt:** derselbe Webhook zweimal ⇒ eine Zahlung + eine Rechnung; ungültige Signatur ⇒ 400 ohne Buchung; Betrags-/Währungs-Mismatch ⇒ Alarm-Audit, keine Buchung; Checkout ohne Checkboxen 422; Client-Preis wirkungslos; Expiry räumt PENDING_PAYMENT
- **Tests:** `ShopCheckoutTest` (7) → **102 Tests grün**, `composer audit` sauber
- **Verifiziert (Testmodus, echtes HTTP):** Konfigurator → Checkout → Bezahlseite → Zahlung simuliert → Order CONFIRMED, Rechnung 2026-000001 ISSUED, cur_payment PAID, 2 Mails versendet; Checkout CSP-/konsolensauber
- **Nicht in diesem Build:** Lager/Bestand (DECISIONS #21) – offene Sub-Fragen unbeantwortet; echter Zahlungsanbieter (nach Gründung)

## 2026-08-16 – M6c: Lagermodul (Owner-Antworten, Branch m1-katalog-preis)
- **Owner-Antworten 2026-08-16** zu den offenen Lager-Fragen umgesetzt (DECISIONS #31).
- **Migration 052:** `product_variants` um `stock_qty/reserve_qty/reorder_threshold` erweitert; `stock_movements` (Audit), `stock_reservations` (Checkout-Holds), `stock_alerts` (dedup je Stufe)
- **Domäne `Stock`:** `StockRepo` (Verfügbarkeit = max(0, Bestand − Reserve − aktive Reservierungen)), `StockService` (reservieren/abbuchen/freigeben, Wareneingang/Korrektur, Reserve/Meldebestand, Alarme)
- **Kein Oversell:** Checkout reserviert je Variante mit `SELECT … FOR UPDATE`; **Abbuchung erst bei bezahlter Bestellung** (Webhook → consume); **Freigabe** bei Abbruch/Ablauf (`cli/expire.php`, Cancel)
- **Meldebestand-Alarme** über Outbox an den Owner: Default 5, dann kritisch (≤2), dann leer – einmalig je Stufe, Rücksetzung bei Wiederauffüllung
- **Shop-Anzeige:** Konfigurator zeigt „noch X" je Größe + Warnung bei geringem/überschrittenem Bestand (CSP-konform)
- **Admin `/admin/lager`:** Sofort-Übersicht (Bestand/Reserve/Reserviert/Verfügbar/Meldebestand), Wareneingang/Korrektur/Reserve/Meldebestand je Variante + „für alle setzen"; Recht `tpb_manage_pricing`
- **Tests:** `StockFlowTest` (5) → **107 Tests grün**, `composer audit` sauber
- **Verifiziert (echtes HTTP):** Konfigurator zeigt Verfügbarkeit (17); Checkout reserviert (→7); Zahlung bucht ab (stock 10, Bewegung `sale`, Reservierung consumed); Admin-Lagerseite 200
- **Owner-Antworten zusätzlich dokumentiert** (OFFENE-FRAGEN): Produkte Hoodies/T-Shirts S–XXL; Versand national Post LU / international DHL, Kosten im Preis + Gratis ab 50 € national, Autodruck gewünscht (Etikett wird erzeugt; stiller Druck via Agent später). **Noch offen:** Farben/Preise/Kostenwerte, Recht/Steuer, Firmengründung.

## 2026-08-16 – M7: Härtung (Teil 1: MFA + Betrieb, Branch m1-katalog-preis)
- **TOTP-MFA** (Eigenimplementierung, RFC 6238): `Domain/Auth/Totp` (gegen RFC-Vektoren getestet) + `MfaService` (Aktivierung mit Code-Bestätigung, 8 Backup-Codes nur als SHA-256, Deaktivierung); **zweistufiger Login** (Passwort → Code/Backup); für owner/admin/finance ab Nicht-Lokal-Deployment verpflichtend (lokal optional). Selbstverwaltung `/admin/mfa`; QR über PNG-Endpunkt `/admin/mfa/qr` (CSP bleibt streng, kein `data:`)
- **Betrieb:** `HealthCheck` (DB/Backup/Outbox/Disk/Quarantäne) + öffentlicher **`/health`** (200/503, keine sensiblen Daten); `cli/healthcheck.php` (Mail nur bei Problemen + Montags-Summary); `cli/retention.php` (fällige Assets je `retention_class`, referenzierte übersprungen, Standard-Trockenlauf/`--apply`)
- **Doku:** `docs/RUNBOOK.md` (Cron/Handgriffe/Incidents) + `docs/DEPLOY.md` (Hostinger-Grundgerüst, §14.6/§14.7)
- **Tests:** `TotpTest` (RFC-Vektoren), `MfaServiceTest`, `HealthCheckTest` → **124 Tests grün**, `composer audit` sauber
- **Verifiziert:** `/health` 200 ok; healthcheck erkennt veraltetes Backup + reiht Alarm-Mail; retention Trockenlauf; MFA-Seiten 200, QR-Endpunkt liefert `image/png`
- **Noch offen für M7-Abschluss (beim Deployment, DECISIONS #33):** sudo-Modus, Checkout-Rechtsprüfung Kap. 12, Backup/Restore-Test-Doku, Seeds-End-to-End, konkrete Hostinger-Schritte

## 2026-08-16 – M8: Zahlungsabgleich & Forderungen (Branch m1-katalog-preis)
- **Bankimport** (`Domain/Bank`): **CSV** (Standardformat) + **CAMT.053** (ISO 20022). Doppelter Duplikatschutz: `file_sha256` (ganze Datei) + `dedupe_hash` je Zeile → **identische Datei = 0 neue Zeilen**
- **Matching** (`Matcher`): Rechnungs-/Auftragsnummer im Verwendungszweck/EndToEndId ⇒ hoch; Betrag exakt + Namensähnlichkeit ⇒ niedrig. **Nie automatisch buchen**
- **Bestätigung** (`BankReconciliation`): Eingangszeile → Payment + Allocation + abgeleitete Zahlungsachse in **einer Transaktion**; Ausgangszeile → Ausgabe; `match_status=confirmed`/`ignored`
- **Mahnwesen** (`DunningService`, `cli/reminders.php`): Zahlungserinnerung Stufe 1 (überfällige Rechnungen) + Wiedervorlage (ablaufende Angebote), **je Stufe genau einmal** (`reminders_sent` UNIQUE)
- **Dashboard „Heute"** (`TodayList`): ablaufende Angebote, wartende Proofs, überfällige Rechnungen, offene Bankzeilen, blockierte Jobs
- **HTTP:** `/admin/bank` (Import + offene Zeilen mit Vorschlag/Bestätigung/Ausgabe/Ignorieren), Dashboard-Umbau; Nav „Bank"; Recht `tpb_manage_finance`
- **Fixtures:** `tests/fixtures/bank/statement.csv` + `statement.camt053.xml`
- **Tests:** `BankFlowTest` (Dedup, Referenz-Match, Bestätigung→Payment/Allocation/Achse, Ausgabe, CAMT) + `DunningTest` (einmalige Stufe) → **131 Tests grün**, `composer audit` sauber
- **Fix:** SQL-Alias `lines` (reserviertes Wort in MariaDB) → `line_count` (im HTTP-Smoke gefunden, von Tests nicht abgedeckt)
- **Verifiziert (echtes HTTP):** Import 2 Zeilen; identische Datei erneut ⇒ 0 neu; Bankseite + Dashboard 200
- **DoD erfüllt:** identische Datei ⇒ 0 neue Zeilen; Referenzzeile ⇒ korrekter Vorschlag; Bestätigung bucht in einer Transaktion; keine Buchung ohne Bestätigung; mehrfacher Mahnlauf ohne Dublette; CSV-/CAMT-Fixtures grün

## 2026-08-16 – M9: Nachträge & Kapazität (Branch m1-katalog-preis)
- **Nachtragsangebot** (`amends_order_id`): `QuoteService::createFromConfiguration(..., amendsOrderId)` bindet an bestehende Order, Preis nach aktuellem Preisbuch; Annahme (§7) **hängt Positionen an** (`OrderRepo::appendFromSnapshot`), **keine neue Order**, Summe additiv, **idempotent**; konfigurierter Nachtrag bei gesperrtem Artwork ⇒ neuer Proof-Zyklus (`artwork LOCKED→MISSING`)
- **Kapazität:** `planned_min` je Job aus der Kostenversion; `CapacityReport` (Σ je Fälligkeitswoche vs. `capacity.week_minutes`, Ampel, **kein Auto-Block**); Admin `/admin/kapazitaet`, Termin je Job (`/admin/job/{id}/termin`); Seed `capacity.week_minutes=2400`
- **HTTP:** Nachtrag-Karte am Auftrag (`/admin/auftrag/{id}/nachtrag`), Kapazität-Nav; Rechte tpb_manage_quotes/tpb_manage_production
- **Tests:** `AmendFlowTest` (keine neue Order, Snapshot-Hash unverändert, additiv, idempotent, Re-Proof) + `CapacityTest` (planned_min, Wochenaggregation) → **135 Tests grün**, `composer audit` sauber
- **Verifiziert:** Admin-Seiten Kapazität/Produktion/Angebote 200
- **Manuell (nicht automatisierbar):** Vertretungstest (zweiter Gründer führt Szenario 2 inkl. Nachtrag nach RUNBOOK.md durch) – Abnahmeschritt beim Deployment

## 2026-08-17 – Design: Storefront-Theme, Konfigurator-Vorschau, Admin-Feinschliff (Branch m1-katalog-preis)
- **Eigenes Kunden-Theme** (`public_html/assets/css/site.css`): helles, markentaugliches Storefront-Design, getrennt vom dunklen Admin-Theme. Hero mit Gradient, Produktraster mit Karten-Hover, Formulare, Checkout-Summary, Footer – responsiv, CSP-streng (keine Inline-Styles/-Scripts).
- **Gemeinsames Site-Layout** (`app/Views/layout/site.php`): Header mit Logo + Navigation, Footer mit Rechtslinks. Alle **17 Kunden-Views** von dupliziertem `<html>`-Gerüst auf content-only umgebaut; Site-Controller rendern jetzt mit `layout/site`.
- **Platzhalter-Logo** `assets/img/logo.svg` (neutral, ersetzbar) – auch als Favicon (Storefront + Admin), behebt `/favicon.ico`-404.
- **Konfigurator-Live-Vorschau** (`configurator.js`): SVG-Mockup (T-Shirt/Hoodie), das live auf **Farbe** (`color_code`-Hex oder Farbnamen-Mapping), **Größe** und **platzierte Motive** reagiert (Druckfläche, Motiv-Rechtecke Front, Rückseiten-Hinweis, Farb-Swatch als SVG). Der „inktracker-Effekt" der Sofort-Vorschau.
- **Admin-Feinschliff** (`admin.css`, additiv): sticky Topbar mit Markenpunkt, Karten-/Kachel-Schatten, klarere Nav-Aktivzustände, Button-Präsenz, Tabellen-Zeilen-Hover.
- **Marke = Platzhalter** (Owner-Freigabe „neutrale Platzhalter" 2026-08-17): Logo, Farben (`#ff5a3c`/`#17171c`), Schrift, Produktbilder – alle klar markiert und in `docs/OFFENE-FRAGEN.md` zum Ersetzen gelistet, nichts erfunden.
- **Verifiziert:** Storefront-Seiten (Home/Produkte/Konfigurator/Checkout/Rechtliches) HTTP 200 im neuen Theme; Konfigurator-Vorschau im echten Browser gerendert, **keine CSP-Verstöße, keine Konsolenfehler**; Admin-Login mit Favicon + Theme; **142 Tests grün**.

## 2026-08-16 – M7: Härtung (Teil 2: sudo-Modus + Rechts-/Betriebsdoku, Branch m1-katalog-preis)
- **sudo-Modus** (`Domain/Auth/Sudo` + Router-Middleware `sudo`): kritische Aktionen (Rechnung ausstellen/gutschreiben, Preisbuch-/Kostenversion-Publish) verlangen eine frische Re-Auth (≤5 min) per Passwort **oder** TOTP; ist sie nicht frisch, rendert der Router `admin/sudo` (spiegelt alle ursprünglichen POST-Felder verdeckt + Passwortfeld) und sendet die Aktion nach Bestätigung erneut
- **Checkout-Rechtsprüfung (Kap. 12):** **Bestellbestätigung mit Vertragsinhalt** – `MailTemplates::orderConfirmed` listet nun die bestellten Positionen + Gesamtbetrag und ergänzt bei Shop-Käufen (`is_shop`) den Widerrufshinweis (Erlöschen bei personalisierter Ware); `ShopWebhookService` lädt dazu die `order_items`. Abnahme-Checkliste (Button „Zahlungspflichtig bestellen", serverseitige Endpreise, Pflicht-Checkboxen, published Rechtstexte) in `docs/DEPLOY.md`
- **Backup/Restore-Test** als verbindliche Prozedur in `docs/DEPLOY.md` (Dump → leere Test-DB → healthcheck → Snapshot-Stichprobe)
- **Sicherheits-Checkliste teil-automatisiert:** `RouteSecurityTest` prüft deklarativ gegen `routes.php`, dass jede mutierende Admin-Route CSRF+Auth trägt und die kritischen Aktionen `sudo` – verhindert, dass eine ungeschützte Route durchrutscht; dokumentierte Ausnahmen: signaturgeprüfter Webhook + zustandslose `/api/`-JSON
- **Tests:** `SudoTest` (isFresh/TTL, Passwort- + TOTP-Bestätigung, falsches Passwort) + `RouteSecurityTest` → **142 Tests grün**, `composer audit` sauber
- **Verifiziert (echtes HTTP):** Publish ohne frische Re-Auth ⇒ „Kritische Aktion bestätigen"-Seite; falsches Passwort ⇒ bleibt auf sudo-Seite; korrektes Passwort ⇒ Aktion läuft durch (302)
- **M7 abgeschlossen.** Noch beim Deployment (kein Code, DECISIONS #33): Seeds als voller End-to-End-Durchlauf (§13.2), Vertretungstest (manuell), konkrete Hostinger-Schritte, echte Fachwerte/Secrets
