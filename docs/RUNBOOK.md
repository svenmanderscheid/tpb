# Betriebs-Runbook – TPB Pure (Grundgerüst, Konzept 20.6)

Stand: M7. Dieses Runbook wird bis zum Go-live vervollständigt.

## Regelmäßige Aufgaben (Cron)

| Job | Befehl | Takt | Zweck |
|---|---|---|---|
| Outbox-Worker | `php cli/outbox_worker.php` | jede Minute | Mails/Effekte verarbeiten (Backoff, nach 5 Versuchen `failed` + Audit) |
| Ablauf | `php cli/expire.php` | stündlich | unbezahlte Shop-Orders (PENDING_PAYMENT) + abgelaufene Angebote → EXPIRED, Reservierungen frei |
| Health-Check | `php cli/healthcheck.php` | täglich | DB/Backup/Outbox/Disk/Quarantäne; Mail nur bei Problemen + Montags-Summary |
| Backup | `php cli/backup.php` | täglich | mysqldump.gz + private/-Archiv nach `private/tpb/backups/` |
| Retention | `php cli/retention.php --apply` | wöchentlich | fällige Assets je `retention_class` löschen (referenzierte werden übersprungen) |

## Wichtige Orte

- **Code/Docroot:** `public_html/` (Front-Controller `index.php`)
- **Privater Speicher:** `private/tpb/` (Uploads, generierte PDFs/JSON, Mail-.eml im file-Driver, Backups) – **außerhalb** des Docroots
- **Migrationen:** `migrations/` (unveränderlich nach Merge – Korrektur = neue Nummer)
- **Logs/Audit:** `audit_events` (fachlich), `status_events` (Statuswechsel), `outbox_events` (Effekte)
- **Health:** `GET /health` (öffentlich, ohne sensible Daten) für Uptime-Monitor

## Häufige Handgriffe

- **Mail hängt?** `outbox_events` mit `status='failed'` prüfen; `last_error` lesen; Ursache beheben; Zeile zurück auf `queued` + `next_attempt_at=now` setzen.
- **Zahlungseingang fehlt (Shop)?** `payment_webhook_events` prüfen (Signatur gültig? `process_status`); `payment_intents` Status; Order-Achse in `status_events`.
- **Bestand falsch?** `/admin/lager` (Bewegungen in `stock_movements`); Reservierungen in `stock_reservations`.
- **Backup zurückspielen:** `gzip -dc <dump>.sql.gz | mysql -u <user> <db>` (Details in DEPLOY.md).

## Incidents (Grundregeln)

1. Umfang feststellen (Health-Check, Audit-Log, betroffene Aggregate).
2. Keine Snapshots/Nummern nachträglich ändern (Angebote/Rechnungen sind unantastbar) – Korrektur über Gutschrift/Storno.
3. Änderung dokumentieren (Audit bleibt Quelle der Wahrheit).

*(To do bis Go-live: Kontakte/Eskalation, konkrete Hostinger-Pfade, Monitoring-Zugänge, Mahnwesen-Ablauf M8.)*
