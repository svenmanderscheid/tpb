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
