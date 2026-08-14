# TPB Pure – Arbeitsanweisung für Claude Code

Betriebssystem von The Printing Brothers: PHP 8.3, MySQL (PDO), Vanilla JS, **kein Framework, kein Build-Step, kein npm**.

## Pflichtlektüre vor jeder Aufgabe

1. `TPB-Pure-PROJECT.md` (Wurzelverzeichnis) – **die verbindliche Implementierungswahrheit**: Konventionen §3, Core-Module §4, Datenmodell §5, Meilensteine mit DoD §12, Arbeitsregeln §14
2. `docs/OFFENE-FRAGEN.md` und `docs/CHANGELOG.md` – aktueller Stand und offene Punkte
3. `docs/The-Printing-Brothers-Systemkonzept-v1.5.md` – **nur** die per §-Verweis referenzierten Abschnitte gezielt nachschlagen, nicht komplett lesen (178 KB)

Bei Widerspruch zwischen den Dokumenten gilt PROJECT.md; der Widerspruch wird in `docs/OFFENE-FRAGEN.md` notiert.

## Harte Regeln (Kurzfassung – Details in PROJECT.md §3 und §14)

- Geld ausschließlich als `int` Cents, Prozente als Basispunkte, Zeit in UTC, `declare(strict_types=1)` überall
- IDs intern `BIGINT AUTO_INCREMENT`, nach außen `public_id` (ULID); Tokens nur als SHA-256-Hash speichern
- Jede fachliche Mutation schreibt ein Audit-Event; jeder externe Effekt läuft über die Outbox; Statuswechsel nur über `States.php` mit `status_events`
- Nur Prepared Statements über den `Db`-Wrapper; `e()` in jedem View-Output; CSRF + Capability-Check auf jeder mutierenden Route
- Migrationen sind nach dem Merge unveränderlich – Korrektur = neue Nummer, niemals editieren
- Composer-Whitelist in PROJECT.md §1.1 ist abschließend – nichts anderes installieren
- Fehlende Fachwerte (Preise, Texte, Firmendaten) **niemals erfinden**: Platzhalter setzen + Eintrag in `docs/OFFENE-FRAGEN.md`
- Ein Meilenstein pro Branch (`m0-fundament`, `m1-katalog-preis`, …); kleine thematische Commits; nie direkt auf `main`
- Angebots-/Rechnungs-Snapshots sind unantastbar; Preise werden immer serverseitig berechnet
- Bei Unklarheit: **stoppen und fragen**, nicht annehmen

## Umgebung

- Lokal: XAMPP, VirtualHost `tpb.local` → `public_html/`, DB `tpb_dev` (Tests: `tpb_test`), PHP 8.3
- Befehle: `vendor/bin/phpunit` · `php cli/migrate.php` · `php cli/seed.php --scenario=N` · `php cli/outbox_worker.php`
- Mail lokal: `MAIL_DRIVER=file` (schreibt .eml nach `private/tpb/outbox-mails/`); Zahlungen ausschließlich im Anbieter-Testmodus
- `private/` liegt außerhalb des Docroots und gehört nie ins Git

## Abschluss jeder Aufgabe

- Alle Tests grün, `composer audit` ohne offene Advisories
- DoD-Selbstprüfung gegen PROJECT.md §12 als Checkliste ausgeben
- `docs/CHANGELOG.md` aktualisieren (Datum, Branch, Umfang, offene Punkte)
