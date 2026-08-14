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
