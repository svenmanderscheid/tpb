# Entscheidungslog

## 2026-08-13/14 – Grundsatzentscheidungen (Planungsphase)

1. **Plattform: TPB Pure** – strukturierter framework-freier PHP/MySQL-Eigenbau statt WordPress/WooCommerce (Begründung: Systemkonzept 1.4). Formelle Bestätigung beider Gründer nach 17.2 steht noch aus (siehe OFFENE-FRAGEN).
2. **Composer-Whitelist abschließend** (PROJECT.md §1.1): dompdf, chillerlan/php-qrcode, picqer/php-barcode-generator, phpmailer, phpunit (dev), später genau ein Zahlungs-SDK.
3. **Erster Owner nur per CLI** (`cli/user_create.php`), kein Web-Bootstrap.
4. **MFA (TOTP)** als Eigenimplementierung, verpflichtend für owner/admin/finance ab erstem Nicht-Lokal-Deployment (M7).
5. **Malware-Scan-Kompensation** auf Shared Hosting: Typ-Whitelist + finfo, keine Skriptausführung, Attachment-only-Downloads, gerasterte Vorschauen (PROJECT.md §8).
6. **Pfad B ab Go-live** mit Vollzahlung im Checkout; **Rechnung automatisch bei Zahlungseingang**, nicht beim Bestellklick (Konzept 20.10).
7. **Zwei Produkttypen** (standard/configurable), mischbar im selben Bestellentwurf; Standardartikel ohne Proof-Zyklus (Konzept 20.11).
8. **Kein separater Warenkorb** – die Konfiguration ist der Bestellentwurf.
9. **Erstattungen** vorerst manuell (Anbieter-Backend + Gutschrift), kein automatischer Refund-Flow.
10. **„Ab Lager“-Standardartikel** bewusst verschoben (erst mit Lagermodul-Ausbau, Konzept 20.11 Punkt 4).

## 2026-08-14 – M0-Start (Branch m0-fundament)

11. **`.gitignore`-Korrektur:** Der ursprüngliche Eintrag `*.sql` hätte `migrations/*.sql` vom Commit ausgeschlossen. Gemäß PROJECT.md §2 (dort `*.sql.gz`) auf `*.sql.gz` geändert und `public_html/assets/img/uploads-cache/` ergänzt. Migrationen gehören versioniert ins Repo.
12. **PHP-Laufzeit lokal 8.2.12** (XAMPP) statt 8.3. PROJECT.md §3 erlaubt 8.2 ausdrücklich als Fallback („keine Syntax > 8.2 ohne Rückfrage"). Es wird keine PHP-8.3-only-Syntax verwendet; `composer.json` fordert `php: >=8.2`.
13. **Composer 2.10.2** über den offiziellen getcomposer.org-Installer installiert (Signatur SHA-384 gegen `composer.github.io/installer.sig` verifiziert), da das winget-Paket `Composer.Composer` nicht mehr im Katalog ist. Ablage `C:\xampp\php\composer.phar` + `composer.bat`, `C:\xampp\php` im User-PATH.

## 2026-08-14 – Finanzmodul (Branch feature/finanzen, bewusste Abweichung)

14. **Eigenständiges Finanzmodul außerhalb der Meilenstein-Reihenfolge** – auf Wunsch des Owners vorgezogen (Roadmap sähe Finanzen in M6/M8). Manuelle Erfassung, unabhängig von der Auftrags-Pipeline; späterer Abgleich mit M6 (Ausgaben §5.6) und M8 (Bankabgleich/Dashboard) bleibt möglich. Begründung: operative Finanzübersicht (Einnahmen/Ausgaben/Gewinn, Kapitaleinlagen und Gewinnanteil je Gründer) wird schon vor dem Pipeline-Ausbau gebraucht.
15. **Migrationsnummer 050** für `050_finance.sql`. Die Nummern **002–006 bleiben reserviert** für die geplante Roadmap (Katalog/Preis, Config/Quote, Order/Proof/Invoice, Operations, Shop-Checkout). Out-of-band-Module verwenden den 050er-Block, damit die geplante Sequenz unangetastet bleibt. Die Finanztabellen sind unabhängig (nur FK auf `users`), daher keine Reihenfolgeabhängigkeit.
16. **Neue Capability `tpb_manage_finance` => [owner, finance]** für schreibende Finanzaktionen; Lesen über die bestehende `tpb_view_costs` (owner/admin/finance). Ergänzt die Matrix aus §4 (dort als „Auszug, vollständig implementieren" gekennzeichnet).
17. **Begriffsdefinition (mit Owner geklärt):** „Investitionen pro Mitarbeiter" = **Kapitaleinlagen der Gründer** (`capital_contributions`); „Verdienste" = **Gewinnanteil je Gründer** (berechnet aus Gewinn × `profit_share_bps`, keine eigene Auszahlungstabelle). Gewinn = Einnahmen − Ausgaben; Einlagen zählen als Eigenkapital, nicht als Einnahme.
18. **Diagramme ohne Bibliothek/CDN** (§14.9): Server rendert einen JSON-Datenblock (`<script type="application/json">`, nicht ausführbar), ein eigenes ES-Modul `assets/js/finance-charts.js` erzeugt daraus Inline-SVG. CSP `default-src 'self'` bleibt unangetastet (keine Inline-Skripte).

## 2026-08-14 – M2-Grundlage (Live-Preis)

19. **Namespace `Http/Api` statt `Http/Public`**: PHP erlaubt `public` (case-insensitiv, also auch `Public`) nicht als Namensraum-Segment. Der in PROJECT.md §2 skizzierte Ordner `Http/Public/` ist als PSR-4-Namespace ungültig. Öffentliche/API-Controller liegen daher unter `app/Http/Api/` (`Tpb\Http\Api`); die späteren öffentlichen Seiten (Konfigurator, Statuslink) kommen unter `app/Http/Site/` (`Tpb\Http\Site`).
20. **`POST /api/price`** ist der serverseitige Live-Preis (§6): akzeptiert eine Konfiguration mit externen Schlüsseln (Produkt-`public_id`, Varianten-SKU, Technik-Code), rechnet gegen das aktuell veröffentlichte Preisbuch/die Kostenversion und gibt den Breakdown inkl. `calc_hash` zurück. **Kein Client-Betrag.** Persistenz in `price_calculations` folgt mit Migration 003 (Konfigurations-Tabellen).
