# Deployment-Runbook – Hostinger (Grundgerüst, §14.6/§14.7)

**Ausführung gemeinsam mit dem Owner, nicht autonom.** Dieses Dokument ist das Gerüst; die konkreten Hostinger-Zugänge/Pfade werden beim ersten Deployment ergänzt.

## Vorbedingungen (Go-live-Blocker)

- Firmengründung abgeschlossen (Konzept 20.7): Rechtsform, Autorisation, AED/57bis, CCSS, Geschäftskonto, Betriebshaftpflicht.
- **Verkäufer-Snapshot** in `business_settings.seller.snapshot` eingetragen (echte Firmendaten).
- **Rechtstexte** (AGB/Datenschutz/Widerruf/Datei) als `published` (statt Platzhalter).
- **Steuerregime** bestätigt + Legende (`tax_regime_versions`).
- **Zahlungsanbieter** onboardet (Adapter + Webhook-Secret), **Carrier**-Zugänge (Post LU/DHL) falls Live-Label.

## Umgebung

- `.env` produktiv (nie in Git): `APP_ENV=production`, DB-Zugang, `MAIL_DRIVER=smtp` + SMTP-Zugang, `APP_URL`, `PAYMENT_PROVIDER`/`PAYMENT_WEBHOOK_SECRET`, `CHECKOUT_TTL_HOURS`.
- **Docroot** auf `public_html/` zeigen lassen; `private/` MUSS außerhalb des Docroots liegen (kein Web-Zugriff).
- PHP 8.2+ mit ext-pdo/json/fileinfo/gd.

## Schritte (Erstdeployment)

1. Code ausrollen (ohne `vendor/` → `composer install --no-dev --optimize-autoloader` auf dem Server, falls möglich; sonst `vendor/` mit ausliefern).
2. `.env` produktiv anlegen (Secrets nicht aus lokal übernehmen).
3. `php cli/migrate.php` (idempotent) – Schema erstellen.
4. Ersten Owner per `php cli/user_create.php` anlegen (kein Web-Bootstrap).
5. Fachdaten einpflegen: Preisbuch/Kostenversion **published**, Rechtstexte, Steuerregime, Verkäufer-Snapshot, Versand-Settings.
6. **MFA erzwingen:** Da `APP_ENV != local`, ist MFA für owner/admin/finance verpflichtend – beim ersten Login wird zur Einrichtung geleitet (`/admin/mfa`).
7. Cron einrichten (siehe RUNBOOK.md): Outbox jede Minute, expire stündlich, backup + healthcheck täglich, retention wöchentlich.
8. **Sicherheits-Checkliste (§11)** verifizieren: HTTPS/HSTS, CSP greift, `private/` per Web = 404, Download nur mit Auth/Token, Rate-Limits aktiv, `composer audit` sauber.
9. Externen **Uptime-Monitor** auf `GET /health` setzen.
10. Smoke-Test im Anbieter-Testmodus (Checkout → Webhook → Rechnung/Mail), dann live schalten.

## Rollback

- Kein Down-Migrations-Mechanismus. **Rollback = Restore** des letzten Backups (`private/tpb/backups/`) + Code-Stand zurücksetzen.
- Restore: `gzip -dc <db>-<stamp>.sql.gz | mysql -u <user> <db>`; `private/`-Archiv entpacken.

*(To do beim Deployment: konkrete Pfade, Cron-Syntax der Hostinger-Oberfläche, TLS-Setup, Backup-Ziel extern.)*
