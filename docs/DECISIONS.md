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
