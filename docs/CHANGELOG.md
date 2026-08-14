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
