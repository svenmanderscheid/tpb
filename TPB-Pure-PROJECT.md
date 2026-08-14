# TPB Pure – PROJECT.md

**Projekt:** The Printing Brothers – Betriebssystem (Konfigurator, Angebote, Proofs, Produktion, Rechnungen, Etiketten)
**Variante:** TPB Pure – strukturierter Eigenbau ohne Framework (Systemkonzept v1.2, Abschnitt 1.4)
**Version:** 1.4 · Stand 13.08.2026 · v1.1: Sicherheits-Härtung · v1.2: Betriebsvollständigkeit (M8/M9) · v1.3: Shop-Direktkauf Pfad B (M6b) · **v1.4: zwei Produkttypen – Standardprodukte (Hausdesigns) und Konfigurator-Produkte, mischbar im selben Bestellentwurf (Konzept 20.11)**
**Zweck dieses Dokuments:** verbindlicher Handoff für Claude Code. Alle §-Verweise beziehen sich auf `The-Printing-Brothers-Systemkonzept-v1.2.md` (liegt in `docs/`).

> Regel Nr. 1: Dieses Dokument ist die Wahrheit. Bei Widerspruch zwischen PROJECT.md und Systemkonzept gilt PROJECT.md für die Implementierung; der Widerspruch wird in `OFFENE-FRAGEN.md` notiert. Bei Unklarheit: **stoppen und fragen**, nicht annehmen.

---

## 0. Kontext und Zielbild

Zwei Gründer bauen einen Cricut-/Textildruckbetrieb in Luxemburg auf. Der MVP ist **Pfad A** (§7.2): Kunde konfiguriert → serverseitiger Richtpreis → Angebotsanfrage → Angebot (PDF) → Annahme per Token → Anzahlung → Proof → Freigabe → Produktion → Etikett → Rechnung → Plan/Ist-Deckungsbeitrag. **Neu ab v1.3 – Pfad B (Shop-Direktkauf, M6b):** dieselbe Konfiguration mündet wahlweise direkt in einen Checkout mit **Vollzahlung** über eine gehostete Zahlungsseite; bei bestätigtem Zahlungseingang wird die Rechnung **automatisch** über den regulären Issue-Flow ausgestellt und als bezahlt markiert. Beide Pfade speisen dieselbe Auftrags-Pipeline (Proof → Produktion → Etikett → Rechnung). Einen separaten Warenkorb gibt es nicht: **die Konfiguration mit ihren Positionen ist der Warenkorb** (fachlich: Bestellentwurf). **v1.4:** Es gibt zwei Produkttypen – `configurable` (durchläuft den Konfigurator) und `standard` (Hausdesign oder unbedrucktes Produkt: nur Variante + Menge wählen). Beide Typen sind im selben Bestellentwurf mischbar und funktionieren in **beiden** Pfaden (Shop und Angebotsweg). Steuerstatus: Art. 57bis (Kleinunternehmer, keine TVA-Berechnung, §11.5) – der Status ist versioniert und wird nicht hart codiert.

**Zielbild-Erweiterung (v1.2):** Das System ist die operative Firma (Systemkonzept Kapitel 20). Jede wiederkehrende Tätigkeit hat einen Ort im System; Fiduciaire, Bank, Lohn und Rechtsberatung docken über definierte Schnittstellen an. Die dafür nötigen Bausteine sind auf M3/M6/M7 verteilt bzw. bilden M8/M9.

Der bestehende Next.js-Prototyp (MOMNT Studio) bleibt unangetastet und dient nur als visuelle/fachliche Referenz (§2.3).

---

## 1. Verbindliche Rahmenbedingungen

**Stack (fest):**

- PHP **8.3** (Fallback 8.2, keine Syntax > 8.2 ohne Rückfrage), `declare(strict_types=1)` in jeder Datei
- MySQL/MariaDB über **PDO**, `utf8mb4_unicode_ci`, InnoDB, `ERRMODE_EXCEPTION`
- Vanilla JavaScript als ES-Module, **kein Build-Step, kein npm, kein Framework, kein Tailwind**
- Eigenes CSS mit Design-Tokens (Farben/Typo später aus dem Prototyp übernommen)
- Composer nur für PSR-4-Autoload und die Whitelist in §1.1
- Lokal: XAMPP mit VirtualHost `tpb.local`, DB `tpb_dev`; Ziel: Hostinger (Deployment erst ab M7)

**Verboten ohne ausdrückliche Freigabe:**

- neue Composer-Abhängigkeiten außerhalb der Whitelist
- Frameworks (Laravel, Symfony, Slim …), ORMs, Template-Engines
- Änderungen an bereits gemergten Migrationen
- `eval`, dynamische SQL-Strings mit Nutzereingaben, `SELECT *` in Repositories
- Speicherung von Kartendaten, Klartext-Tokens oder absoluten Serverpfaden in der DB

### 1.1 Composer-Whitelist

| Paket | Zweck |
|---|---|
| `dompdf/dompdf` | Angebots-, Proof-, Rechnungs- und Etiketten-PDFs |
| `chillerlan/php-qrcode` | QR-Codes auf Etiketten |
| `picqer/php-barcode-generator` | Code 128 auf Etiketten |
| `phpmailer/phpmailer` | SMTP-Versand über die Outbox |
| `phpunit/phpunit` (dev) | Tests |
| SDK des gewählten Zahlungsanbieters (genau **einer**: Stripe oder Mollie o. ä., Entscheidung §15) | Hosted Checkout + Webhook-Verifikation (ab M6b) |

Alles andere: erst Eintrag in `OFFENE-FRAGEN.md`, dann Entscheidung durch den Owner.

---

## 2. Projektstruktur

```text
tpb/
  public_html/
    index.php              # Front Controller, einziger Einstieg
    assets/
      css/  js/  img/
  app/
    Core/                  # Env, Db, Router, Auth, Authz, Csrf, RateLimit,
                           # Request, Response, View, Ulid, Clock, Canonical
    Domain/
      Catalog/             # Product, Variant, Technique, Placement + Repos
      Pricing/             # PriceBook, CostVersion, PriceEngine, Breakdown
      Config/              # Configuration, Layer, Unit, Preflight
      Customer/
      Quote/
      Order/
      Proof/
      Production/
      Payment/             # Gateway-Interface, Provider-Adapter, WebhookVerifier (v1.3)
      Invoice/             # Invoice, NumberSequence, SnapshotBuilder
      Label/               # LabelTemplate, PrintJob
      File/                # Asset, PrivateStorage, DownloadGuard
      Audit/  Outbox/  Legal/
    Http/
      Public/              # Konfigurator, Angebotsanfrage, Statuslink, Proof
      Admin/               # Backoffice-Controller
      Middleware.php
      routes.php
    Views/
      layout/  public/  admin/  pdf/  mail/
  private/                 # NIEMALS unter public_html; Struktur nach §10.5:
    tpb/artwork/  tpb/proofs/  tpb/orders/  tpb/invoices/  tpb/labels/  tpb/exports/
  migrations/              # 001_init.sql, 002_catalog_pricing.sql, ...
  cli/
    migrate.php  seed.php  outbox_worker.php  backup.php  user_create.php
  cron/README.md           # Hostinger-Cron-Definitionen (ab M7)
  tests/
    Unit/  Integration/  bootstrap.php
  docs/
    The-Printing-Brothers-Systemkonzept-v1.2.md
    OFFENE-FRAGEN.md  CHANGELOG.md  DECISIONS.md
  composer.json  composer.lock  .env.example  .gitignore  phpunit.xml
```

`.gitignore` mindestens: `.env`, `/private/`, `/vendor/`, `*.sql.gz`, lokale Dumps, `public_html/assets/img/uploads-cache/`.

---

## 3. Kernkonventionen (gelten überall)

1. **Geld:** ausschließlich `int` Cents (§10.4). Keine Floats im Geldpfad – nie. Anzeigeformatierung nur in Views.
2. **Prozente:** Basispunkte als `int` (2500 = 25,00 %). Helfer `Money::bp(int $cents, int $bps): int` mit Round-half-up.
3. **Zeit:** DB speichert UTC (`DATETIME(3)`), `Clock::nowUtc()`. Anzeige über `Clock::toLux()` (Europe/Luxembourg).
4. **IDs:** intern `BIGINT UNSIGNED AUTO_INCREMENT`; überall, wo eine ID nach außen sichtbar wird, zusätzlich `public_id CHAR(26)` (ULID, monoton). Öffentliche Tokens sind zufällige 32 Bytes (base64url), in der DB **nur als SHA-256-Hash**.
5. **Status:** `VARCHAR(32)`-Spalten, gültige Werte und Übergänge zentral in `Domain/*/States.php` (§7 dieses Dokuments). Jeder Übergang schreibt ein `status_events`-Ereignis. Direkte Status-UPDATEs ohne Ereignis sind verboten.
6. **Snapshots:** Angebote und Rechnungen frieren alles ein (§1 Punkt 7, §11.2). Kanonisches JSON über `Canonical::json(array $data)` (Keys rekursiv sortiert, keine Floats, `JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES`), Hash = `hash('sha256', $json)`.
7. **Audit:** jede fachliche Mutation ruft `Audit::log(...)` (§13.5). `audit_events` ist append-only – kein UPDATE/DELETE im Code.
8. **Idempotenz:** jeder Endpunkt mit Seiteneffekt (Annahme, Rechnungsausstellung, Druckjob, Outbox-Handler) prüft einen Idempotency-Key.
9. **Fehler:** zentraler Handler; Nutzer sehen generische Meldung + Correlation-ID, Details nur ins Log (`private/tpb/logs/`, ohne personenbezogene Daten/Dateiinhalte). `display_errors=Off` außer lokal.
10. **SQL:** ausschließlich Prepared Statements über den `Db`-Wrapper; Repositories kapseln alle Queries; Transaktionen über `Db::tx(callable)`.
11. **Code Englisch, UI Deutsch.** UI-Texte in `app/Core/i18n/de.php` (flaches Array) – Mehrsprachigkeit (§4.4) kommt später, aber kein Text hart in Views verdrahtet, wenn er kundensichtbar ist.
12. **Escaping:** `e()` (htmlspecialchars, ENT_QUOTES) in jedem View-Output. JSON-Antworten über `Response::json()`.

---

## 4. Core-Module (Spezifikation)

**Env** – parst `.env` (KEY=VALUE, `#` Kommentar). `Env::get('KEY')`, `Env::require('KEY')` wirft bei Fehlen. Keine externe Lib.

**Db** – Singleton-PDO. `Db::run($sql, $params): PDOStatement`, `Db::tx(callable $fn)` (BEGIN/COMMIT/ROLLBACK, gibt Rückgabewert von `$fn` durch, Rollback bei Exception, verschachtelte tx verboten → Exception).

**Router** – `routes.php` liefert Array:

```php
['GET',  '/konfigurator/{publicId}', [ConfiguratorController::class, 'show'],  ['public']],
['POST', '/admin/prices/publish',    [PriceBookController::class, 'publish'],  ['auth:tpb_manage_pricing', 'csrf']],
```

Pattern-Parameter `{name}` → Regex `[A-Za-z0-9_-]+`. Middleware-Tags: `public`, `auth:<capability>`, `csrf`, `rate:<bucket>`. 404/405 sauber.

**Auth/Authz** – Login mit `password_hash()`/`password_verify()`, Session-Regeneration nach Login, Session-Cookie `Secure/HttpOnly/SameSite=Lax`. Rollen: `owner, admin, sales, production, finance, readonly` (§6.3). Capability-Matrix als Konstante in `Authz` (Auszug, vollständig implementieren):

```php
'tpb_manage_pricing'    => ['owner','admin'],
'tpb_view_costs'        => ['owner','admin','finance'],
'tpb_manage_artwork'    => ['owner','admin','sales'],
'tpb_manage_production' => ['owner','admin','production'],
'tpb_issue_invoices'    => ['owner','finance'],
'tpb_manage_legal'      => ['owner'],
'tpb_reprint_labels'    => ['owner','admin','production'],
'tpb_view_audit'        => ['owner'],
'tpb_manage_users'      => ['owner'],
```

Serverseitige Prüfung auf **jeder** Adminroute und jedem privaten Download (§13.1). Menü-Ausblenden ist keine Zugriffskontrolle.

**Csrf** – Token pro Session, Hidden-Field + `X-CSRF-Token`-Header für `fetch`. Alle POST/PUT/DELETE außerhalb tokenbasierter Kundenendpunkte prüfen.

**RateLimit** – DB-basiert (`login_attempts` + generische Buckets): Login max. 5/15 min pro E-Mail und IP; Upload, Statuslink, Angebotsanfrage eigene Buckets (§13.1).

**Totp** – RFC-6238-Eigenimplementierung (~80 Zeilen: `hash_hmac('sha1')`, 30-Sekunden-Fenster ±1, Base32-Secret, otpauth-URI als QR über die vorhandene QR-Lib). Pflicht für `owner/admin/finance`, sobald das System nicht mehr rein lokal läuft (M7); Recovery über 8 gehashte Einmal-Backupcodes (`mfa_backup_codes`).

**Ulid** – ULID-Generator (Crockford-Base32, monoton innerhalb ms). Eigenimplementierung ~60 Zeilen, mit Tests.

**View** – `View::render('admin/quotes/show', $data)`; Layout-Wrapping; `e()` global verfügbar.

Front Controller (Sollzustand, M0):

```php
<?php declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
\Tpb\Core\Env::load(dirname(__DIR__) . '/.env');
\Tpb\Core\ErrorHandler::register();
\Tpb\Core\Router::dispatch(require dirname(__DIR__) . '/app/Http/routes.php');
```

---

## 5. Datenmodell und Migrationen

### 5.1 Migrationsrunner (`cli/migrate.php`)

- legt `schema_migrations (version VARCHAR(8) PK, checksum CHAR(64), applied_at DATETIME)` an, falls fehlend;
- führt `migrations/NNN_*.sql` in Reihenfolge aus, überspringt angewandte, **bricht ab**, wenn der Checksum einer bereits angewandten Datei abweicht (Migrationen sind unveränderlich, §14.5);
- `--status` listet offen/angewandt; `--dry-run` zeigt SQL. Kein Down-Migrations-Mechanismus: Rollback = Restore aus Backup (`cli/backup.php` vor jeder Migration aufrufen, wird in M0 mitgeliefert: mysqldump + tar von `private/`).

### 5.2 Migration `001_init.sql` – Fundament (SQL verbindlich)

```sql
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  role VARCHAR(32) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'active',
  mfa_secret VARCHAR(64) NULL,
  mfa_enabled_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mfa_backup_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(48) NOT NULL,
  identifier VARCHAR(190) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL,
  KEY idx_bucket (bucket, identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_settings (
  setting_key VARCHAR(64) PRIMARY KEY,
  value_json JSON NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tax_regime_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  regime_code VARCHAR(32) NOT NULL,
  legend_text VARCHAR(255) NOT NULL,
  valid_from DATE NOT NULL,
  valid_until DATE NULL,
  confirmed_by BIGINT UNSIGNED NULL,
  confirmed_at DATETIME NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE number_sequences (
  seq_type VARCHAR(32) NOT NULL,
  fiscal_year SMALLINT NOT NULL,
  next_value INT UNSIGNED NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (seq_type, fiscal_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  occurred_at DATETIME(3) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_label VARCHAR(64) NULL,
  aggregate_type VARCHAR(48) NOT NULL,
  aggregate_id VARCHAR(64) NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  from_state VARCHAR(32) NULL,
  to_state VARCHAR(32) NULL,
  reason_code VARCHAR(64) NULL,
  correlation_id CHAR(26) NULL,
  before_hash CHAR(64) NULL,
  after_hash CHAR(64) NULL,
  metadata_json JSON NULL,
  KEY idx_aggregate (aggregate_type, aggregate_id),
  KEY idx_time (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE status_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  aggregate_type VARCHAR(48) NOT NULL,
  aggregate_id BIGINT UNSIGNED NOT NULL,
  axis VARCHAR(24) NOT NULL,
  from_state VARCHAR(32) NULL,
  to_state VARCHAR(32) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_label VARCHAR(64) NULL,
  reason VARCHAR(255) NULL,
  correlation_id CHAR(26) NULL,
  occurred_at DATETIME(3) NOT NULL,
  KEY idx_axis (aggregate_type, aggregate_id, axis, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE outbox_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(64) NOT NULL,
  payload_json JSON NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'queued',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NOT NULL,
  locked_at DATETIME NULL,
  locked_by VARCHAR(64) NULL,
  last_error TEXT NULL,
  idempotency_key VARCHAR(80) NULL UNIQUE,
  created_at DATETIME NOT NULL,
  processed_at DATETIME NULL,
  KEY idx_due (status, next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE idempotency_keys (
  idem_key VARCHAR(80) PRIMARY KEY,
  scope VARCHAR(64) NOT NULL,
  result_json JSON NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  owner_type VARCHAR(32) NULL,
  owner_id BIGINT UNSIGNED NULL,
  kind VARCHAR(32) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  storage_key VARCHAR(255) NOT NULL UNIQUE,
  security_status VARCHAR(16) NOT NULL DEFAULT 'quarantine',
  retention_class VARCHAR(32) NOT NULL,
  delete_after DATE NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_owner (owner_type, owner_id),
  KEY idx_sha (sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE access_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL UNIQUE,
  purpose VARCHAR(32) NOT NULL,
  ref_type VARCHAR(32) NOT NULL,
  ref_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  last_used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_ref (ref_type, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legal_document_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_type VARCHAR(32) NOT NULL,
  language CHAR(2) NOT NULL,
  version VARCHAR(16) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  content_hash CHAR(64) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  valid_from DATETIME NULL,
  valid_until DATETIME NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_doc (doc_type, language, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Erster Owner wird **nicht** über die Web-UI erzeugt (kein Bootstrap-Muster wie im Prototyp, §2.2), sondern über `cli/user_create.php --role=owner` (fragt Passwort interaktiv ab).

### 5.3 Migration `002_catalog_pricing.sql` – Spezifikation

SQL nach den Konventionen aus 001 ableiten (alle Tabellen mit `created_at`/`updated_at`, FKs mit `ON DELETE RESTRICT`):

| Tabelle | Spalten (Kern) |
|---|---|
| `products` | id, public_id, **product_type(configurable/standard) (v1.4)**, sku_root UNIQUE, name, slug UNIQUE, description_md, status(draft/active/archived), sort |
| `product_variants` | id, product_id FK, sku UNIQUE, color_code, color_name, size, status, sort |
| `techniques` | id, code UNIQUE (FLEX, FLOCK, GLITTER, REFLEX …), name, status |
| `placements` | id, product_id FK, side(front/back/sleeve_l/sleeve_r/neck), code, name, area_x_mm, area_y_mm, max_w_mm, max_h_mm DECIMAL(6,1), preset_x_mm, preset_y_mm, is_preset TINYINT, sort — UNIQUE(product_id, code) |
| `product_prints` (v1.4) | id, product_id FK (nur `standard`), placement_id FK, technique_id FK, design_asset_id FK, design_name, design_version SMALLINT, width_mm, height_mm, offset_x_mm, offset_y_mm — katalogseitige Druckdefinition des Hausdesigns; Änderung erhöht design_version (Audit) |
| `price_books` | id, version SMALLINT UNIQUE, currency CHAR(3), status(draft/published/retired), valid_from, valid_until, published_by, published_at |
| `price_tiers` | id, price_book_id FK, product_id FK, qty_from SMALLINT, qty_to SMALLINT NULL, unit_cents INT — UNIQUE(price_book_id, product_id, qty_from) |
| `price_params` | id, price_book_id FK, param_key VARCHAR(48), value_int INT, note — UNIQUE(price_book_id, param_key) |
| `cost_versions` | id, version SMALLINT UNIQUE, status, valid_from, labor_rate_cents_h INT, machine_rate_cents_h INT, scrap_bps SMALLINT, target_margin_bps SMALLINT, published_by, published_at |
| `cost_items` | id, cost_version_id FK, ref_type(variant/product/technique), ref_id, param_key(BLANK_CENTS/MATERIAL_CENTS/SETUP_MIN/UNIT_MIN/MACHINE_MIN), value_int — UNIQUE(cost_version_id, ref_type, ref_id, param_key) |

`price_params`-Schlüssel (vollständige Liste, MVP): `SETUP_FEE_CENTS_PER_MOTIF`, `SETUP_FEE_WAIVER_QTY`, `EXTRA_POSITION_CENTS`, `EXTRA_COLOR_CENTS`, `NAME_NUMBER_CENTS`, `FILEPREP_CENTS`, `EXPRESS_BPS`, `MIN_ORDER_CENTS`, `TECH_SURCHARGE_<CODE>_CENTS`; ab M6b optional: `SHIPPING_FLAT_CENTS` (Versandpauschale – Abholung bleibt 0, Entscheidung §15).

Preisbücher/Kostenversionen haben einen Draft→Published-Workflow (§6.2); nach `published` sind Tiers/Params dieser Version unveränderlich (App-seitig erzwingen + Audit).

### 5.4 Migration `003_config_customer_quote.sql` – Spezifikation

| Tabelle | Spalten (Kern) |
|---|---|
| `customers` | id, public_id, type(private/business), company_name NULL, first_name, last_name, email, phone, lang CHAR(2), billing_street/zip/city/country, delivery_* NULL, created/updated |
| `customer_consents` | id, customer_id FK, purpose, legal_doc_version_id FK NULL, granted_at, source, revoked_at NULL |
| `configurations` | id, public_id, customer_id NULL, guest_email NULL, guest_name NULL, status(draft/submitted/quoted/expired/discarded), price_book_id FK, cost_version_id FK, express TINYINT, note, expires_at, created/updated |
| `configuration_items` | id, configuration_id FK, pos_no, **item_type(configured/standard) (v1.4)**, product_id FK, technique_id FK **(NULL bei `standard`)**, comment |
| `configuration_item_sizes` | id, item_id FK, variant_id FK, qty SMALLINT — UNIQUE(item_id, variant_id) |
| `configuration_layers` | id, item_id FK, placement_id FK, layer_no, layer_type(logo/text), asset_id FK NULL, text_content NULL, color_name, width_mm, height_mm, offset_x_mm, offset_y_mm DECIMAL(6,1) |
| `configuration_units` | id, item_id FK, unit_no, variant_id FK, name VARCHAR(60) NULL, number VARCHAR(8) NULL, note |
| `price_calculations` | id, configuration_id FK, price_book_id, cost_version_id, input_hash CHAR(64), breakdown_json JSON, total_cents INT, floor_cents INT, below_floor TINYINT, calc_hash CHAR(64), created_at |
| `quotes` | id, public_id, quote_number VARCHAR(20) UNIQUE NULL (erst bei Versand), customer_id FK, configuration_id FK, **amends_order_id FK NULL (v1.2: Nachtragsangebot, §7)**, status, currency, total_cents, valid_until DATE, snapshot_json JSON, snapshot_sha256 CHAR(64), pdf_asset_id FK NULL, sent_at, accepted_at, declined_at, created_by, created/updated |
| `quote_items` | id, quote_id FK, pos_no, sku NULL, description, qty, unit_cents, line_cents |

Positionen in **realen Millimetern** speichern, nie in Browserpixeln (§5.3). Der Konfigurator rechnet clientseitig px↔mm über die Druckfläche um. **Standardartikel (v1.4)** haben keine `configuration_layers` und keine `configuration_units`; ihre Druckdefinition kommt zur Produktionszeit aus `product_prints` (Snapshot ins `config_snapshot_json` des Order-Items bei Bestellung).

### 5.5 Migration `004_order_proof_production_invoice.sql` – Spezifikation

| Tabelle | Spalten (Kern) |
|---|---|
| `orders` | id, public_id, order_number UNIQUE, quote_id FK NULL, customer_id FK, customer_snapshot_json, currency, total_cents, deposit_required_cents INT DEFAULT 0, ordered_at, cur_payment, cur_artwork, cur_production, cur_fulfillment, cur_invoice (VARCHAR(32), Cache – Wahrheit ist `status_events`), completed_at NULL, cancelled_at NULL |
| `order_items` | id, order_id FK, pos_no, product_id, sku, description, qty, unit_cents, line_cents, config_snapshot_json |
| `order_item_units` | id, order_item_id FK, unit_no, variant_sku, name NULL, number NULL |
| `order_terms_acceptance` | id, order_id FK NULL, quote_id FK NULL, doc_type, legal_doc_version_id FK, shown_at, accepted_at NULL, actor_label |
| `artwork_versions` | id, order_id FK, asset_id FK, version_no, kind(original/cleaned), preflight_json, status, created_by, created_at |
| `proofs` | id, order_id FK, artwork_version_id FK, version_no, pdf_asset_id FK, status(draft/sent/approved/changes_requested/superseded), sent_at, created_at |
| `proof_approvals` | id, proof_id FK, decision(approved/changes_requested), comment TEXT NULL, actor_label, access_token_id FK NULL, decided_at |
| `production_jobs` | id, public_id, job_number UNIQUE, order_id FK, order_item_id FK, status, route VARCHAR(64), planned_min INT, due_date DATE NULL, gate_override_by NULL, gate_override_reason NULL, created/updated |
| `production_events` | id, job_id FK, event_type(start/stop/qty_good/scrap/rework/qc_pass/qc_fail), step VARCHAR(24), actor_user_id, qty INT NULL, minutes INT NULL, note NULL, idem_key VARCHAR(80) NULL UNIQUE, occurred_at |
| `invoices` | id, public_id, doc_type(invoice/credit_note), invoice_number VARCHAR(20) UNIQUE NULL (erst bei ISSUED), status, order_id FK NULL, credited_invoice_id FK NULL, seller_snapshot_json, customer_snapshot_json, tax_regime_code, tax_legend VARCHAR(255), currency, net_cents, tax_cents, gross_cents, prepayment_applied_cents INT DEFAULT 0, issued_at NULL, due_date NULL, snapshot_json JSON NULL, snapshot_sha256 NULL, pdf_asset_id NULL, json_asset_id NULL, created_by, created/updated |
| `invoice_lines` | id, invoice_id FK, pos_no, sku NULL, description, qty, unit_cents, line_cents |
| `payments` | id, order_id FK NULL, method(bank_transfer/cash/other), amount_cents, currency, received_at DATE, reference, fee_cents INT DEFAULT 0, note, recorded_by, created_at |
| `payment_allocations` | id, payment_id FK, invoice_id FK, amount_cents — UNIQUE(payment_id, invoice_id) |
| `deposit_requests` | id, public_id, request_number VARCHAR(20) UNIQUE (Sequenz `deposit`, Format `ZA-2026-000001`), order_id FK, amount_cents, due_date, bank_reference (= order_number), status(draft/sent/settled/cancelled), pdf_asset_id NULL, sent_at NULL, created_by, created/updated — ausdrücklich **keine Rechnung** (Konzept 20.2); Wortlaut laut §15 von der Fiduciaire freigegeben |
| `print_jobs` | id, label_type, entity_type, entity_id, template_version VARCHAR(16), copies TINYINT, idempotency_key UNIQUE, payload_json, render_format(pdf), rendered_asset_id NULL, rendered_sha256 NULL, status(queued/rendered/dialog_opened/error), requested_by, requested_at, completed_at NULL, error_message NULL, reprint_of_id NULL, reprint_reason NULL |


### 5.6 Migration `005_operations.sql` – Spezifikation (v1.2, Konzept 20.3/20.5)

| Tabelle | Spalten (Kern) |
|---|---|
| `expenses` | id, public_id, expense_date DATE, vendor VARCHAR(120), category VARCHAR(48), description, amount_cents, currency, receipt_asset_id FK NULL, order_id FK NULL, bank_line_id FK NULL, recorded_by, created/updated |
| `bank_imports` | id, account_label, format(csv/camt053), original_asset_id FK, file_sha256 CHAR(64) UNIQUE, line_count, imported_by, imported_at |
| `bank_lines` | id, bank_import_id FK, line_no, booking_date DATE, value_date DATE NULL, amount_cents INT **signiert** (Eingang +, Ausgang −), currency, counterparty_name, counterparty_iban NULL, remittance_info VARCHAR(500), end_to_end_id NULL, dedupe_hash CHAR(64) UNIQUE, match_status(open/suggested/confirmed/ignored), matched_payment_id FK NULL, matched_expense_id FK NULL, confirmed_by NULL, confirmed_at NULL |
| `reminders_sent` | id, ref_type(quote/invoice), ref_id, reminder_type(expiry/payment), level TINYINT, sent_at, outbox_id NULL — UNIQUE(ref_type, ref_id, reminder_type, level) |

Konfiguration über `business_settings` statt eigener Tabellen: `reminder.quote_expiry_days`, `dunning.levels` (JSON: Tage + Textbaustein-Key je Stufe), `capacity.week_minutes`.

**Matching (`Domain/Bank/Matcher`):** (1) exakte Auftrags-/Rechnungs-/ZA-Nummer in `remittance_info` oder `end_to_end_id` ⇒ Vorschlag hoch; (2) Betrag exakt + Namensähnlichkeit ⇒ Vorschlag niedrig. **Nie automatisch buchen** – Bestätigung durch `finance/owner` erzeugt in einer Transaktion `payments` + `payment_allocations` (bzw. `expenses` bei Ausgangszeilen) und setzt `confirmed`. `dedupe_hash` = sha256(Konto + booking_date + amount + remittance_info + line_no).


### 5.7 Migration `006_shop_checkout.sql` – Spezifikation (v1.3, Konzept 20.10)

| Tabelle | Spalten (Kern) |
|---|---|
| `payment_intents` | id, public_id, order_id FK, provider VARCHAR(24), provider_ref VARCHAR(120) NULL — UNIQUE(provider, provider_ref), amount_cents, currency, status(created/pending/succeeded/failed/expired/canceled), checkout_url VARCHAR(500) NULL, failure_reason VARCHAR(255) NULL, created/updated |
| `payment_webhook_events` | id, provider, event_ref VARCHAR(120) — UNIQUE(provider, event_ref), event_type, payload_json JSON, signature_valid TINYINT, received_at, processed_at NULL, process_status(pending/done/ignored/error), error_message NULL |

**Regeln:** Der Webhook-Endpunkt verifiziert die Signatur **vor** jeder Verarbeitung, persistiert das Rohevent als Beleg und verarbeitet dann in einer Transaktion (Idempotenz doppelt: UNIQUE(provider, event_ref) **und** Idempotency-Key `shop_paid_<order_id>`). **Betrags- und Währungsabgleich gegen die Order ist Pflicht** – bei Abweichung keine Buchung, sondern Alarm-Audit. Erstattungen im ersten Ausbau: manuell im Anbieter-Backend + Gutschrift im System (§7); kein automatischer Refund-Flow.

---

## 6. Preis-Engine (verbindliche Spezifikation)

`Domain/Pricing/PriceEngine::calculate(Configuration $c, PriceBook $pb, CostVersion $cv): Breakdown`

Deterministisch, reine Int-Arithmetik, keine DB-Zugriffe innerhalb der Rechnung (Repos laden vorher alles in Value Objects).

**Verkaufspreis (veröffentlicht):**

1. Je Item: `qty_total = Σ configuration_item_sizes.qty`.
2. `unit_base = tier(product_id, qty_total)` – fehlender Tier ⇒ `PricingException` (niemals 0 annehmen).
3. Zuschläge je Stück: `TECH_SURCHARGE_<CODE>_CENTS` + `(positions − 1) × EXTRA_POSITION_CENTS` + ggf. `EXTRA_COLOR_CENTS`. `positions` = Anzahl belegter Placements des Items.
4. Personalisierung: je Unit mit Name **oder** Nummer `NAME_NUMBER_CENTS` (einmal pro Stück, nicht doppelt).
5. `line_cents = qty_total × (unit_base + stückzuschläge) + Σ personalisierung`.
6. Einrichtung: je **distinktem Motiv** (Asset-`sha256` über die ganze Konfiguration) `SETUP_FEE_CENTS_PER_MOTIF`, entfällt, wenn `qty_total_gesamt ≥ SETUP_FEE_WAIVER_QTY`. Textlayer zählen nicht als Motiv.
7. `subtotal = Σ lines + setups (+ FILEPREP_CENTS, wenn Flag gesetzt)`.
8. Express: `Money::bp(subtotal, EXPRESS_BPS)`.
9. `total = subtotal + express` (Versand im MVP: Abholung = 0; Feld im Breakdown vorsehen).
10. `total < MIN_ORDER_CENTS` ⇒ Ergebnisflag `below_min_order` – UI blockiert das Absenden mit verständlicher Meldung.

**Standardartikel (v1.4):** `line_cents = qty_total × tier(product_id, qty_total)` – keine Technik-/Positions-/Farbzuschläge, keine Personalisierung, kein Setup (das Hausdesign ist eingerichtet; seine Kosten stecken in den `cost_items` des Produkts). `MIN_ORDER_CENTS` und `EXPRESS_BPS` gelten über den gesamten Entwurf, Staffelmengen je Item wie bei konfigurierten Artikeln. Die Untergrenze wird identisch parallel gerechnet.

**Interne Untergrenze (parallel, §5.5 Systemkonzept):**

```text
selbst_je_stück = BLANK + MATERIAL
               + round_half_up(((SETUP_MIN / qty_total) + UNIT_MIN) / 60 × labor_rate)
               + round_half_up(MACHINE_MIN / 60 × machine_rate)
selbst_je_stück += Money::bp(selbst_je_stück, scrap_bps)
floor_total      = ceil_div(selbst_gesamt × 10000, 10000 − target_margin_bps)
```

`below_floor = total < floor_total` ⇒ Warnflag im Breakdown und im Admin sichtbar; Override-Workflow kommt erst nach dem MVP (bis dahin: Angebot unter Floor nur durch `owner`, mit Audit-Grund).

**Breakdown:** vollständige Aufschlüsselung als Array (Items → Zeilen → Komponenten, alle Werte Cents/Basispunkte), kanonisiert, `calc_hash = sha256(Canonical::json([inputs, price_book_version, cost_version]))`. Jede Berechnung wird in `price_calculations` persistiert. **Der Browser sendet niemals einen Betrag** – Annahme/Angebot referenzieren immer eine `price_calculations.id` und rechnen serverseitig nach (§1 Punkt 6).

---

## 7. Statusachsen und Übergänge

Erlaubte Übergänge zentral in `States.php` je Domain; alles andere wirft `IllegalTransitionException`. Achsen (§7.1, pure-adaptiert):

| Achse | Zustände | Terminal |
|---|---|---|
| quote | DRAFT → SENT → ACCEPTED \| DECLINED \| EXPIRED \| CANCELLED | ACCEPTED, DECLINED, EXPIRED, CANCELLED |
| order | Pfad B: PENDING_PAYMENT → CONFIRMED \| EXPIRED \| CANCELLED (v1.3); Pfad A startet direkt bei CONFIRMED; CONFIRMED → COMPLETED \| CANCELLED | COMPLETED, CANCELLED, EXPIRED |
| payment (order) | NOT_DUE → UNPAID → PARTIALLY_PAID → PAID; OVERDUE als Flag aus Fälligkeit; REFUNDED | – (abgeleitet aus payments/allocations, nie manuell gesetzt) |
| artwork (order) | MISSING → UPLOADED → PREPRESS_REVIEW → PROOF_SENT → CHANGES_REQUESTED ↔ PROOF_SENT → APPROVED → LOCKED — nur konfigurierte Positionen; ein Auftrag ohne solche startet direkt bei LOCKED (v1.4) | LOCKED |
| production (job) | BLOCKED → READY → IN_PROGRESS → QUALITY_CHECK → DONE; REWORK ↔ IN_PROGRESS; SCRAPPED | DONE, SCRAPPED |
| fulfillment (order) | UNFULFILLED → PACKING → READY_FOR_PICKUP → COLLECTED (Versand: READY_TO_SHIP → SHIPPED → DELIVERED) | COLLECTED, DELIVERED |
| invoice | NONE → DRAFT → ISSUED → SENT → PARTIALLY_CREDITED \| FULLY_CREDITED | FULLY_CREDITED |

**Produktionsfreigabe-Gate (§7.3, v1.3):** Job → READY nur wenn (a) Order CONFIRMED (aus Quote-Annahme **oder** bezahltem Shop-Checkout), (b) Pfad A: Anzahlung erfasst **oder** `deposit_required_cents = 0` **oder** Override durch `owner/admin` mit Grund; Pfad B: Zahlungsachse PAID (Vollzahlung – kein Override nötig), (c) Artwork LOCKED – **je Position geprüft (v1.4):** Standardartikel gelten mit ihrem Hausdesign als vorab freigegeben und erfüllen (c) immer; Jobs für konfigurierte Positionen brauchen die Kundenfreigabe. Gate-Prüfung serverseitig im Übergang, nicht in der UI.

**Angebotsannahme (idempotent):** Kunde öffnet `/angebot/{publicId}?t=<token>` → Annahme-POST prüft Token-Hash, Ablauf, Status; in **einer** Transaktion: Quote → ACCEPTED, Order + Items + Units aus Snapshot erzeugen (`order_number` via NumberSequence), Terms-Acceptance schreiben, Statusereignisse, Audit; Idempotency-Key `quote_accept_<quote_id>` verhindert Doppel-Order bei Doppelklick.

**Nachtragsannahme (v1.2, Konzept 20.1):** Ein Angebot mit `amends_order_id` durchläuft dieselbe Quote-Achse, wird aber zum **aktuell veröffentlichten** Preisbuch bepreist. Die Annahme erzeugt **keine** neue Order: In einer Transaktion werden neue `order_items` (+Units) an die referenzierte Order angehängt, `orders.total_cents` additiv aktualisiert, Statusereignis + Audit geschrieben; Idempotency-Key `quote_accept_<quote_id>`. Bestehende Snapshots (Ursprungsangebot, ausgestellte Rechnungen) bleiben binär unverändert; Abrechnung über die nächste Rechnung. Betrifft der Nachtrag ein Item mit Artwork LOCKED ⇒ neuer Proof-Zyklus für die betroffenen Positionen. Minderungen nur vor Produktionsstart, Ausgleich über Gutschrift.

**Shop-Bestellung (Pfad B, v1.3):** Konfiguration → Checkout (Kundendaten als Gast, versionierte Rechtserklärungen via `order_terms_acceptance` inkl. gesonderter Kenntnisnahme des Widerrufs-Erlöschens bei personalisierter Ware, Button-Lösung „zahlungspflichtig bestellen“) → in **einer** Transaktion: Order mit Status PENDING_PAYMENT + Items/Units aus der Konfiguration (Preis ausschließlich aus server-seitiger Neuberechnung via `price_calculations`) + `payment_intents`-Zeile → Redirect zur gehosteten Zahlungsseite. Webhook „bezahlt“ (§5.7): transaktional Zahlung erfassen + allokieren, Order → CONFIRMED, **Rechnung automatisch über den Issue-Flow ausstellen** und via Allocation als bezahlt markieren, Bestell- + Rechnungsmail über Outbox. Kein Zahlungseingang bis Ablauf (Default 24 h, `.env` `CHECKOUT_TTL_HOURS`) ⇒ `cli/expire.php` setzt PENDING_PAYMENT → EXPIRED (keine Rechnung, kein Lagerbezug); dasselbe Skript setzt abgelaufene Angebote → EXPIRED. Shop-Orders haben `deposit_required_cents = 0`.

**Nummernvergabe (Referenzimplementierung, überall so):**

```php
public static function next(string $type, int $year, string $prefix, int $pad = 6): string {
    return Db::tx(function (PDO $pdo) use ($type, $year, $prefix, $pad) {
        $pdo->prepare('INSERT IGNORE INTO number_sequences (seq_type, fiscal_year, next_value, updated_at)
                       VALUES (?, ?, 1, UTC_TIMESTAMP())')->execute([$type, $year]);
        $stmt = $pdo->prepare('SELECT next_value FROM number_sequences
                               WHERE seq_type = ? AND fiscal_year = ? FOR UPDATE');
        $stmt->execute([$type, $year]);
        $n = (int)$stmt->fetchColumn();
        $pdo->prepare('UPDATE number_sequences SET next_value = next_value + 1, updated_at = UTC_TIMESTAMP()
                       WHERE seq_type = ? AND fiscal_year = ?')->execute([$type, $year]);
        return sprintf('%s-%d-%0' . $pad . 'd', $prefix, $year, $n);
    });
}
```

Formate (§9.3): `ORD-2026-000123`, `JOB-2026-000456`, Angebot `Q-2026-000123`, Rechnung `2026-000001` (eigene Sequenz `invoice`, Vergabe **nur** im Issue-Flow §11.2).

**Rechnungsausstellung (Issue-Flow, exakt §11.2):** Draft validieren → in einer Transaktion Nummer ziehen + Seller/Customer/Tax/Lines einfrieren → kanonisches JSON bauen → PDF aus **demselben** Snapshot rendern → beide als Assets speichern, SHA-256 in `invoices` → Status ISSUED (unumkehrbar, App verweigert danach jedes UPDATE außer Statusversand/Gutschrift-Verknüpfung) → Versand als Outbox-Event. `tax_legend` aus aktiver `tax_regime_versions`-Zeile (Volltext Art. 57bis, §11.5), nie hart codiert.

---

## 8. Dateien und privater Speicher

- Physisch: `private/tpb/...` (§10.5-Struktur), **außerhalb** `public_html`. DB speichert nur `storage_key` (relativ), nie absolute Pfade.
- Upload-Pipeline (§5.4): Endungs-Whitelist (svg, pdf, png, jpg) → `finfo`-MIME-Prüfung → Größenlimits (`.env`: `UPLOAD_MAX_BYTES`, Default 25 MB; Pixelmaß-Limit für Raster) → SHA-256 → Speicherung unter `artwork/{customerOrGuest}/{assetUlid}/original` → `security_status = quarantine` → Preflight (Maße, effektive DPI bei Zielgröße, Transparenz-Hinweis) → Thumbnail als PNG (GD) getrennt vom Original → `clean`.
- **SVG niemals inline ausliefern** – Vorschau ausschließlich als serverseitig gerastertes PNG (§13.1). PDFs nur als Download mit `Content-Disposition: attachment`.
- Download-Endpunkt `/files/{publicId}`: Auth + Objektberechtigung (Owner/Rolle/Auftragszuordnung) **oder** gültiges kurzlebiges Token (`access_tokens`, purpose `file_download`, TTL ≤ 15 min); Streaming mit `readfile`, korrekte Header, `X-Content-Type-Options: nosniff`. Direktzugriff testweise anonym prüfen (M7-Checkliste).
- Jedes Asset trägt `retention_class` (`invoice_10y`, `artwork_short`, `proof_contract`, `temp_30d` …); `cli/retention.php` (M7) löscht fällige Klassen mit Audit.
- **Kompensation fehlender Malware-Scanner** auf Shared Hosting (§5.4 fordert einen Scan, Hostinger bietet keinen nutzbaren): strikte Typ-Whitelist + `finfo`, keinerlei Skriptausführung im Uploadbereich, Originale nur als `attachment`-Download, Vorschau ausschließlich serverseitig gerastert, Quarantäne-Status bis Preflight. Diese Kompensation wird in `docs/DECISIONS.md` dokumentiert; ein externer Scan-Dienst bleibt spätere Option.

---

## 9. PDF, Etiketten, QR/Barcode

- `PdfService::render(string $view, array $data): string` (dompdf, A4, eingebettete Schrift; Umlaute/Akzente testen).
- **dompdf-Härtung (verbindlich):** `isRemoteEnabled = false`, `chroot` auf `Views/pdf` + Temp-Verzeichnis; niemals nutzerkontrolliertes HTML/CSS rendern – alle dynamischen Werte laufen durch `e()` in die PDF-Views; Bilder ausschließlich über lokale Pfade aus dem privaten Speicher. dompdf hatte historisch CVEs – Updates zeitnah einspielen (siehe §11 Audit-Routine).
- PDF-Views unter `Views/pdf/`: `quote.php`, `proof.php`, `invoice.php`, `label_job.php`, `deposit_request.php` (v1.2: Zahlungsaufforderung Anzahlung – bewusst ohne Rechnungs-Pflichtangaben, Wortlaut aus §15), `reminder.php` (Zahlungserinnerung/Mahnung Stufe 1). Rechnungs-PDF rendert **nur** aus `snapshot_json`, nie aus Live-Daten.
- Jobetikett (§9.2): 62 × 100 mm, Inhalt: Jobnummer (Code 128), QR (interne Scan-URL mit Token), Produkt, Farbe, Größenraster, Menge, Technik, Positionen, Artwork-Version, Termin. Rendering in exakter physischer Größe; `cli`-Testrender in 203 und 300 dpi als PNG zur Sichtprüfung (M5-DoD).
- Druck im MVP = **Stufe 1** (§9.6): PDF erzeugen, `print_jobs.status = rendered`, Nutzer druckt über Systemdialog, Status `dialog_opened` – niemals fälschlich „gedruckt“. QZ Tray/Print-Agent sind ausdrücklich **nicht** Teil dieses Handoffs.

---

## 10. Outbox, Cron und E-Mail

- Fachlogik erzeugt nie direkt Seiteneffekte: erst DB-Transaktion inkl. `outbox_events`-Zeile, dann Verarbeitung durch `cli/outbox_worker.php` (§3.1 Hintergrundverarbeitung).
- Worker: Lock via `GET_LOCK('tpb_outbox', 0)` (Fallback Lockfile), claimt fällige Events (`status='queued' AND next_attempt_at <= now`, `LIMIT 20`), Backoff 1/5/15/60 min, nach 5 Versuchen `failed` + Audit-Alarm-Eintrag.
- Handler-Registry: `mail.quote_sent`, `mail.order_confirmed`, `mail.deposit_request` (v1.2), `mail.proof_sent`, `mail.invoice_issued`, `mail.payment_reminder` (v1.2), `pdf.render`, später mehr. Jeder Handler idempotent.
- Mail: PHPMailer über SMTP aus `.env`; lokal Mailpit (`127.0.0.1:1025`) oder `MAIL_DRIVER=file` (schreibt .eml nach `private/tpb/outbox-mails/` – Default für Tests). Templates `Views/mail/` Text + einfaches HTML, Kundensprache DE.
- Lokal: Worker manuell oder per geplantem Task jede Minute; Hostinger-Cron erst M7.

---

## 11. Sicherheit – Pflicht-Checkliste (Auszug §13.1, gilt ab M0)

- `password_hash(PASSWORD_DEFAULT)`; Session-Fixation-Schutz; Logout zerstört Session serverseitig.
- Capability-Check + CSRF auf jeder mutierenden Route; `permission`-Prüfung zusätzlich im Controller (Defense in depth).
- Security-Header global: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, CSP mindestens `default-src 'self'` (Konfigurator ohne Inline-Skripte bauen).
- Rate Limits: Login, Upload, Statuslink-Aufrufe, Angebotsanfrage.
- Keine personenbezogenen Daten, Tokens oder Dateiinhalte in Logs; Logs unter `private/`, Rotation.
- Fortlaufende Nummern sind nie Zugriffsschutz – öffentliche URLs nur über `public_id`/Token (§9.3).
- Admin-Sitzungen: Idle-Timeout 30 min, absolute Lebensdauer 12 h; erneute Passwort-/TOTP-Bestätigung („sudo-Modus“, 5 min gültig) für kritische Aktionen: Rechnungsausstellung, Gutschrift, Preisbuch-Publish, Angebot unter Floor, Benutzerverwaltung.
- MFA (TOTP, §4) für `owner/admin/finance` verpflichtend ab dem ersten Nicht-Lokal-Deployment; lokal optional.
- `composer audit` beim Abschluss jedes Meilensteins und vor jedem Release; gemeldete Advisories werden vor dem Merge bewertet und dokumentiert.
- `.env`-Beispieldatei ohne Secrets; Produktions-Secrets nie in Git, nie in Seeds, nie in Tests.

---

## 12. Meilensteine und Definition of Done

Reihenfolge fix, ein Meilenstein pro Branch (`m0-fundament`, `m1-katalog-preis`, …). Ein Meilenstein ist fertig, wenn seine DoD-Punkte erfüllt, alle Tests grün und `CHANGELOG.md` aktualisiert sind.

**M0 – Fundament** (Migration 001)
Repo-Skelett, Composer/Autoload, Env, Db, Router, View, ErrorHandler, Auth/Authz, Csrf, RateLimit, Ulid, Audit, Outbox-Grundgerüst + Worker, Asset-Upload in Quarantäne + gesicherter Download, `cli/migrate|seed|backup|user_create`, Admin-Login + leeres Dashboard.
*DoD (§15.2):* kein Web-Weg zum ersten Admin; Migrationen laufen auf leerer DB und erneut ohne Effekt; Upload → Quarantäne → Preflight → autorisierter Download funktioniert; anonymer Direktzugriff auf `private/` scheitert nachweislich; Audit-Eintrag bei Login/Upload; `backup.php` erzeugt Restore-fähigen Dump (einmal zurückgespielt).

**M1 – Katalog & Preis-Engine** (Migration 002)
Admin-CRUD für Produkte/Varianten/Techniken/Placements; Preisbuch- und Kostenversions-Verwaltung mit Draft→Publish; PriceEngine + Breakdown; Seed „Basissortiment“.
*DoD (§16.1-Teilmenge):* tabellengetriebene Engine-Tests inkl. Staffelgrenzen 4/5, 9/10, 24/25, 49/50; Waiver ab `SETUP_FEE_WAIVER_QTY`; Express-Rundung; `below_floor`/`below_min_order` korrekt; publizierte Preisbücher unveränderlich; identische Eingabe ⇒ identischer `calc_hash`.

**M2 – Konfigurator (gespeichert)** (Migration 003, Teil 1)
Öffentliche Konfiguratorseite (Vanilla-JS-Modul): Produkt → Farbe → Größenmatrix → Logo-Upload → Placement-Presets + Feinjustierung in mm → Live-Preis über `POST /api/price` (serverseitig, debounced); Entwurf speichern/laden über `public_id`; Personalisierungsliste manuell (Name/Nummer je Stück). **Standardprodukt-Seite (v1.4):** Varianten-/Mengenwahl ohne Konfigurator, legt `standard`-Items in denselben Bestellentwurf; gemischte Entwürfe (Standard + konfiguriert) sind der Normalfall, nicht die Ausnahme.
*DoD:* Reload/anderes Gerät stellt Entwurf her; Browser-Manipulation des Preises wirkungslos (Server rechnet immer neu); Upload-Pipeline vollständig; Positionsdaten in mm; Tastaturbedienbarkeit der Presets (§4.5-Minimum); Standardartikel landen im selben Entwurf, werden ohne Zuschläge bepreist und der Gesamtpreis über beide Itemtypen stimmt (Engine-Test).

**M3 – Kunde & Angebot** (Migration 003, Teil 2)
Angebotsanfrage (Kontakt + Rechtserklärungen §5.4 mit `legal_document_versions`-Bezug), Admin: Anfrage → Angebot (Snapshot + `Q-`Nummer + PDF) → Versand (Outbox) → Kundenansicht per Token → Annahme/Ablehnung. Öffentliche Basisseiten (Startseite, Produktübersicht aus dem Katalog, Rechtstexte aus `legal_document_versions`) entstehen hier mit. Annahme erzeugt die Order (Tabellen aus §5.5) und bei `deposit_required_cents > 0` automatisch die **Anzahlungsaufforderung** (`deposit_requests`, ZA-PDF + Mail über Outbox, strukturierte Referenz = Auftragsnummer).
*DoD:* Annahme erzeugt idempotent genau eine Order; Preisbuchänderung nach Versand ändert das Angebot nicht (Snapshot-Test); PDF = Snapshot-Inhalt; abgelaufenes/widerrufenes Token wird abgewiesen.

**M4 – Proof & Freigabe**
Artwork-Versionen, Proof-PDF aus finaler Datei + Produktionsmaßen, Versand, Kundenfreigabe/Änderungswunsch über Statuslink, LOCKED. **Nur für konfigurierte Positionen** – Standardartikel überspringen den Proof-Zyklus vollständig (v1.4).
*DoD:* Freigabe referenziert exakt eine Proof-Version (Hash); neue Version setzt alte auf `superseded` und erfordert neue Freigabe; Ereignis mit Zeit/Actor/Token.

**M5 – Produktion & Etikett**
Jobs aus Order-Items, Gate (§7 dieses Dokuments), Statusfluss mit Buttons (Scanner erst später), Mengen-/Ausschusserfassung, Jobetikett-PDF + `print_jobs`.
*DoD:* Job ohne Gate bleibt BLOCKED; doppelter Event-POST (gleicher `idem_key`) bucht nicht doppelt; Etikett in 203/300 dpi lesbar, QR/Code 128 mit Smartphone scanbar; Neudruck nur mit Grund; Jobetikett zeigt bei Standardartikeln Designname + `design_version` aus dem Order-Item-Snapshot (v1.4).

**M6 – Rechnung & Plan/Ist**
Invoice-Draft aus Order, Issue-Flow (§7), Zahlungs-Erfassung + Zuordnung, abgeleitete Payment-Achse, Gutschrift, **Ausgabenerfassung** (`expenses`, §5.6) mit Beleg-Upload und optionaler Auftragszuordnung, Mini-Report: geplanter vs. tatsächlicher Deckungsbeitrag je Auftrag (§11.7 vereinfacht: Istzeit aus `production_events`, Ausschuss, eingefrorene Sätze).
*DoD (§16.4-Teilmenge):* zwei parallele Issue-Requests ⇒ keine Doppelnummer (Test mit zwei PDO-Verbindungen); ISSUED nicht editierbar; PDF/JSON-Summen identisch; Umsatz ≠ Zahlungseingang getrennt sichtbar; Gutschrift referenziert korrekt.

**M6b – Shop-Checkout & Online-Zahlung (Pfad B)** (Migration 006, Konzept 20.10)
Checkout-Seite auf der Konfiguration (Zusammenfassung mit vollständigem Endpreis, Gast-Kundendaten, Pflicht-Checkboxen, Button-Lösung); Gateway-Abstraktion `Domain/Payment` + erster Adapter (Anbieter laut §15, Entwicklung komplett im Testmodus); Order-Erzeugung PENDING_PAYMENT + Redirect; signierter, idempotenter Webhook mit automatischer Rechnungsausstellung (§7); `cli/expire.php`; Admin: Zahlungsintents und Webhook-Events einsehbar.
*DoD:* derselbe Webhook zweimal ⇒ genau eine Zahlung und eine Rechnung (Idempotenz-Test); ungültige Signatur ⇒ 4xx ohne jeden Effekt; Betrag-/Währungs-Mismatch ⇒ Alarm-Audit, keine Buchung; Checkout ohne Checkboxen nicht absendbar; manipulierter Client-Preis wirkungslos; Expiry räumt PENDING_PAYMENT auf; kompletter Kauf im Anbieter-Testmodus end-to-end grün inkl. Rechnungs-PDF und Mails. **Go-live-Blocker außerhalb des Codes:** Anbieter-Onboarding (KYC) erfordert gegründete Firma + Geschäftskonto (Konzept 20.7).

**M7 – Härtung & Abnahme**
Sicherheits-Checkliste §11 komplett verifiziert, MFA-Rollout (TOTP) für `owner/admin/finance`, `composer audit` ohne offene Advisories, Retention-Job, Seeds als End-to-End-Durchlauf (Szenarien §13.2), Backup/Restore-Test dokumentiert, Checkout-Rechtsprüfung gegen Kapitel 12 des Systemkonzepts (Button-Text, Endpreise, Widerrufsbelehrung Standard/personalisiert, Bestellbestätigung mit Vertragsinhalt), **`cli/healthcheck.php`** (täglicher Cron: letztes Backup, failed Outbox, Speicherplatz, Quarantäne-Stau ⇒ Mail nur bei Problemen + Montags-Summary), **`/health`-Endpunkt** ohne sensible Daten + externer Uptime-Monitor, **`docs/RUNBOOK.md`-Grundgerüst** (Konzept 20.6), Hostinger-Deployment-Runbook (`docs/DEPLOY.md`) nach §14.6/§14.7 des Systemkonzepts – Ausführung des Deployments erfolgt gemeinsam mit dem Owner, nicht autonom.

**M8 – Zahlungsabgleich & Forderungen** (Migration 005, Konzept 20.3/20.5)
Bankimport (CSV-Spaltenmapping der Hausbank + CAMT.053), Duplikatschutz (`file_sha256`, `dedupe_hash`), Matching-Vorschläge + Bestätigungs-UI, Offene-Posten-Warteschlange, ausgehende Zeilen ⇒ Ausgaben-Vorschlag; Wiedervorlage (ablaufende Angebote) und Mahnwesen (Erinnerung + Stufe 1) über Outbox mit `reminders_sent`; Dashboard-„Heute“-Liste (ablaufende Angebote, wartende Proofs, überfällige Rechnungen, offene Bankzeilen, blockierte Jobs).
*DoD:* identische Datei erneut importiert ⇒ 0 neue Zeilen; Zeile mit Auftragsreferenz ⇒ korrekter Vorschlag, Bestätigung erzeugt Payment + Allocation + Achsen-Update in einer Transaktion; mehrfacher Mahnlauf versendet keine Stufe doppelt (UNIQUE-Test); keine Buchung ohne Bestätigung; Fixtures aus `tests/fixtures/bank/` laufen grün.

**M9 – Nachträge, Kapazität & Vertretungstest** (Konzept 20.1/20.4/20.6)
Nachtragsangebot aus der Auftragsansicht (Konfiguration klonen, `amends_order_id`, Annahme-Flow aus §7), Kapazitäts-Wochenansicht (Σ `planned_min` je Fälligkeitswoche vs. `capacity.week_minutes`, Ampel + Auslastungshinweis bei Termineingabe – kein Auto-Block), Vertretungstest: zweiter Gründer führt Szenario 2 inkl. Nachtrag ausschließlich mit `RUNBOOK.md` durch, Befunde werden als UX-Fixes umgesetzt.
*DoD:* Nachtragsannahme erzeugt keine neue Order (Idempotenz-Test), Ursprungs-Snapshots binär unverändert (Hash-Vergleich); Kapazitätsrechnung stimmt gegen Seeds; Vertretungstest protokolliert, alle Blocker behoben.

---

## 13. Tests und Seeds

- PHPUnit; Unit-Tests ohne DB (PriceEngine, Ulid, Canonical, Money, States), Integration-Tests gegen `tpb_test` (Migrationen laufen im Bootstrap, Transaktions-Rollback je Test wo möglich).
- Pflicht-Testfelder: Engine-Tabelle (M1), Nummern-Parallelität (M6), Snapshot-Unveränderlichkeit (M3/M6), Statusübergangs-Matrix erlaubt/verboten (alle Achsen), Upload-Preflight (falsche Endung, falscher MIME, Übergröße, SVG mit `<script>` ⇒ blocked), Capability-Matrix (jede Adminroute je Rolle), Token-Ablauf/-Widerruf.
- `cli/seed.php --scenario=<n>` (nur `APP_ENV=local|test`), Szenarien aus §14.10: (1) Privatkunde 20 T-Shirts ein Logo, (2) Verein mit Größenmatrix, Sponsor hinten, Namen im Nacken, (5) Proof v1→v2, (6) Anzahlung/Teilzahlung, (9) Rechnung + Gutschrift; (v1.2) Nachtrag auf Szenario 2 sowie Bank-Fixtures unter `tests/fixtures/bank/` (Beispiel-CSV + CAMT.053, inkl. Zeile mit und ohne Auftragsreferenz); (v1.3) Shop-Kauf im Testmodus: Checkout → Webhook-Simulation (Fixtures unter `tests/fixtures/payment/`) → automatische Rechnung; (v1.4) gemischter Entwurf: 20 konfigurierte Shirts + 5 Standardartikel in einem Auftrag – Preis, Proof-Umfang (nur Shirts) und Jobs korrekt. Testlogos werden generiert (GD-PNG/valides Test-SVG), keine echten Personen, keine echten Marken.

---

## 14. Arbeitsregeln für Claude Code (verbindlich)

1. **Vor jeder Session:** dieses Dokument lesen; `OFFENE-FRAGEN.md` und `CHANGELOG.md` prüfen.
2. **Ein Meilenstein, ein Branch.** Kleine, thematisch geschlossene Commits mit Präfix (`m1: price tiers CRUD`). Nie direkt auf `main`.
3. **Migrationen sind unveränderlich**, sobald gemergt – Korrekturen immer als neue Nummer. Vor jeder Migration lokal `cli/backup.php`.
4. **Nichts stillschweigend erfinden:** fehlende Fachwerte (Preise, Texte, Produktdaten) als `TODO(§15)`-Platzhalter + Eintrag in `OFFENE-FRAGEN.md`. Keine erfundenen Firmendaten in Ausgabedokumenten (§12.1) – Platzhalter `[nach Gründung]`.
5. **Konventionen aus §3 sind nicht verhandelbar** (Cents, UTC, Snapshots, Audit, Prepared Statements, Escaping).
6. Bestehenden Code **iterativ verfeinern**, keine Großumbauten ohne Auftrag; Refactorings als eigener Commit, getrennt von Features.
7. Jede neue Route in `routes.php` mit Middleware-Tags; jeder neue Seiteneffekt über Outbox; jede Mutation mit Audit.
8. Nach jedem Meilenstein: `CHANGELOG.md`-Eintrag (Datum, Branch, Umfang, offene Punkte) und kurze Selbstprüfung gegen die DoD-Liste im Commit-/PR-Text.
9. Keine Netzwerkzugriffe zur Laufzeit außer SMTP; keine externen CDNs, Fonts oder Skripte (§12.9 – MVP ist cookie-arm: nur Session-Cookie).
10. Entscheidungen mit Tragweite (Schemaabweichung, neue Dependency, Strukturänderung) → `docs/DECISIONS.md` mit Begründung, erst nach Freigabe umsetzen.
11. **`composer audit` gehört zum Abschluss jedes Meilensteins.** Offene Advisories blockieren den Merge, bis sie bewertet sind (Update, Workaround oder dokumentierte Risikoakzeptanz in `DECISIONS.md`).

---

## 15. Offene Parameter – vor M1 vom Owner zu füllen

| Parameter | Quelle/Format |
|---|---|
| MVP-Produktliste (welche der 6 Prototyp-Produkte), Farben, Größen, SKUs | §8.1-Schema `TSH-COT-BLK-M` |
| Staffelpreise je Produkt (Preisbuch v1) | Cents, Staffeln inkl. Grenzen |
| `price_params`-Werte (Setup, Waiver-Menge, Extra-Position, Name/Nummer, Express-Bps, Mindestwert) | Cents/Bps |
| Kostenversion v1: Rohlingkosten je Variante, Zeitwerte, Stundensätze, Ausschuss-Bps, Zielmarge-Bps | mit Fiduciaire plausibilisieren |
| Anzahlungsregel (`deposit_required_cents`-Logik, z. B. ab Auftragswert X → Y %) | Geschäftsentscheidung §17 |
| Angebots-Gültigkeitsdauer (Default `valid_until`) | Tage |
| Rechtstexte v1 (AGB, Datenschutz, Widerruf/Personalisierung, Datei-Erklärungen) | juristisch geprüft, je Sprache |
| Seller-Snapshot-Felder | `[nach Gründung]` bis Eintrag vorliegt (§12.1) |
| SMTP-Zugang (erst ab M3-Abnahme nötig; bis dahin `MAIL_DRIVER=file`) | `.env` |
| Anzahlungs-Wortlaut („keine Rechnung“-Formulierung, Fälligkeit) | Fiduciaire-Freigabe (Konzept 20.2) |
| Mahnstufen: Intervalle, Textbausteine, Verzugsfolgen-Hinweis | juristisch geprüft (Konzept 20.5) |
| Hausbank + Exportformat (CSV-Spaltenbelegung oder CAMT.053) | Beispieldatei für `tests/fixtures/bank/` |
| Verfügbare Produktionsminuten pro Woche | Geschäftsentscheidung (Konzept 20.4) |
| Rechtsform, Versicherungen, Gründungsstatus | Konzept 20.7 – blockiert Seller-Snapshot und Go-live |
| Zahlungsanbieter: gehostete Zahlungsseite, signierte Webhooks, Gebühren je Zahlart, Auszahlungsrhythmus, in Luxemburg relevante Zahlarten | Entscheidung vor M6b; **Onboarding erst nach Gründung möglich** |
| Versand: nur Abholung oder zusätzlich Pauschale (`SHIPPING_FLAT_CENTS`) | Geschäftsentscheidung |
| Rechnungszeitpunkt bei Shop-Vollzahlung (automatische Ausstellung bei Zahlungseingang, Art. 57bis) | Fiduciaire-Bestätigung (Konzept 20.10) |
| Standardsortiment: Designs als Assets, Platzierungen/Maße (`product_prints`), Staffelpreise | Katalogpflege durch den zweiten Gründer, vor M2-Abnahme (Konzept 20.11) |

---

## Anhang A – Kickoff-Prompt für Claude Code

```text
Lies zuerst vollständig: TPB-Pure-PROJECT.md (Wurzelverzeichnis) und docs/OFFENE-FRAGEN.md.
Die Arbeitsregeln in §14 und die Konventionen in §3 sind verbindlich.

Auftrag: Setze Meilenstein M0 (§12) um.
- Branch: m0-fundament
- Reihenfolge: Projektskelett + Composer → Core-Module (§4) → migrations/001_init.sql
  exakt wie in §5.2 → cli/migrate.php + cli/user_create.php + cli/backup.php →
  Auth/Login + leeres Admin-Dashboard → Asset-Upload/Preflight/Download (§8) →
  Outbox + Worker (§10) → Tests (§13) → Seeds-Grundgerüst.
- Fehlende Fachwerte: Platzhalter + Eintrag in OFFENE-FRAGEN.md, nicht erfinden.
- Am Ende: DoD-Selbstprüfung M0 als Checkliste ausgeben, CHANGELOG.md aktualisieren.
Beginne mit einem kurzen Umsetzungsplan (max. 15 Zeilen), dann implementiere.
```

---

*Ende PROJECT.md v1.4 – Änderungen an diesem Dokument nur versioniert (v1.1, v1.2 …) mit Eintrag in `docs/DECISIONS.md`.*
