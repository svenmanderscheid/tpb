<?php
declare(strict_types=1);

/**
 * Seed-Runner (§13). NUR in APP_ENV=local|test.
 * Aufruf: php cli/seed.php --scenario=<n>
 *
 * Die fachlichen Szenarien (§13.2: 1,2,5,6,9, Nachtrag, Shop, gemischt) werden
 * ab M1 befüllt, sobald Katalog/Preis-Engine stehen. M0 liefert nur das
 * Grundgerüst inkl. Umgebungs-Guard und Dispatcher.
 */

require __DIR__ . '/_bootstrap.php';

use Tpb\Core\Env;

$env = Env::get('APP_ENV', 'local');
if (!in_array($env, ['local', 'test'], true)) {
    fwrite(STDERR, "Seeds sind nur in APP_ENV=local|test erlaubt (aktuell: {$env}).\n");
    exit(1);
}

$argv = $_SERVER['argv'] ?? [];
$scenario = cli_opt($argv, 'scenario');

/**
 * Registry der geplanten Szenarien (§13.2). Callable folgt ab dem jeweiligen
 * Meilenstein; bis dahin dokumentierter Platzhalter (TODO §15/Meilenstein).
 * @var array<int,string> $scenarios
 */
$scenarios = [
    1 => 'Privatkunde: 20 T-Shirts, ein Logo (ab M1/M2)',
    2 => 'Verein: Größenmatrix, Sponsor hinten, Namen im Nacken (ab M2)',
    5 => 'Proof v1 -> v2 (ab M4)',
    6 => 'Anzahlung/Teilzahlung (ab M3/M6)',
    9 => 'Rechnung + Gutschrift (ab M6)',
];

if ($scenario === null) {
    echo "Verfügbare Szenarien:\n";
    echo "  --scenario=test  Testobjekt + veröffentlichtes Preisbuch/Kostenversion (M1/M2)\n";
    echo "  --scenario=m3    Platzhalter-Rechtstexte (published) + Angebots-Gültigkeitsdauer (M3)\n";
    echo "  --scenario=m6    Platzhalter-Steuerregime (Art. 57bis) + Verkäufer-Snapshot + Zahlungsziel (M6)\n";
    foreach ($scenarios as $n => $desc) {
        echo "  --scenario={$n}  {$desc}\n";
    }
    exit(0);
}

if ($scenario === 'test') {
    seed_test_object();
    exit(0);
}

if ($scenario === 'm3') {
    seed_m3_basics();
    exit(0);
}

if ($scenario === 'm6') {
    seed_m6_basics();
    exit(0);
}

$n = (int) $scenario;
if (!isset($scenarios[$n])) {
    fwrite(STDERR, "Unbekanntes Szenario: {$n}\n");
    exit(1);
}

fwrite(STDERR, "Szenario {$n} ({$scenarios[$n]}) ist noch nicht implementiert (folgt ab M2+).\n");
exit(2);

/**
 * Legt ein Testobjekt mit Preis an: Produkt + Variante + Position + Technik,
 * ein veröffentlichtes Preisbuch (Staffeln + Parameter) und eine veröffentlichte
 * Kostenversion. Testwerte – bewusst kein echtes Sortiment. Idempotent.
 */
function seed_test_object(): void
{
    if (\Tpb\Domain\Catalog\ProductRepo::skuRootExists('TEST')) {
        echo "Testobjekt (sku_root=TEST) existiert bereits – nichts zu tun.\n";
        return;
    }

    [$productPublic, $variantSku, $bookVersion, $costVersion] = \Tpb\Core\Db::tx(function () {
        // Technik
        if (!\Tpb\Domain\Catalog\TechniqueRepo::codeExists('FLEX')) {
            \Tpb\Domain\Catalog\TechniqueRepo::create('FLEX', 'Flexfolie');
        }
        $flexId = (int) \Tpb\Domain\Catalog\TechniqueRepo::idByCode('FLEX');

        // Produkt + Variante + Position
        $productPublic = \Tpb\Domain\Catalog\ProductRepo::create('configurable', 'TEST', 'Testobjekt', 'testobjekt', 'Automatisch angelegtes Testobjekt.', null);
        $product = \Tpb\Domain\Catalog\ProductRepo::findByPublicId($productPublic);
        $productId = (int) $product['id'];
        // aktiv schalten
        \Tpb\Domain\Catalog\ProductRepo::update($productPublic, 'configurable', 'TEST', 'Testobjekt', 'testobjekt', 'Automatisch angelegtes Testobjekt.', 'active', 0);

        $variantSku = 'TEST-BLK-M';
        $variantId = \Tpb\Domain\Catalog\VariantRepo::create($productId, $variantSku, 'BLK', 'Schwarz', 'M');
        \Tpb\Domain\Catalog\PlacementRepo::create($productId, 'front', 'brust', 'Brust', ['max_w_mm' => '100.0', 'max_h_mm' => '100.0'], true);

        // Preisbuch
        $bookVersion = \Tpb\Domain\Pricing\PriceBookRepo::nextVersion();
        $bookId = \Tpb\Domain\Pricing\PriceBookRepo::create($bookVersion, 'EUR');
        \Tpb\Domain\Pricing\PriceBookRepo::addTier($bookId, $productId, 1, 9, 2000);
        \Tpb\Domain\Pricing\PriceBookRepo::addTier($bookId, $productId, 10, 49, 1800);
        \Tpb\Domain\Pricing\PriceBookRepo::addTier($bookId, $productId, 50, null, 1600);
        foreach ([
            'SETUP_FEE_CENTS_PER_MOTIF' => 5000,
            'SETUP_FEE_WAIVER_QTY'      => 50,
            'EXTRA_POSITION_CENTS'      => 300,
            'EXTRA_COLOR_CENTS'         => 200,
            'NAME_NUMBER_CENTS'         => 250,
            'FILEPREP_CENTS'            => 1500,
            'EXPRESS_BPS'               => 2500,
            'MIN_ORDER_CENTS'           => 3000,
            'TECH_SURCHARGE_FLEX_CENTS' => 100,
        ] as $k => $v) {
            \Tpb\Domain\Pricing\PriceBookRepo::setParam($bookId, $k, $v, 'Testwert');
        }
        \Tpb\Domain\Pricing\PriceBookRepo::publish($bookVersion, null);

        // Kostenversion
        $costVersion = \Tpb\Domain\Pricing\CostVersionRepo::nextVersion();
        $cvId = \Tpb\Domain\Pricing\CostVersionRepo::create($costVersion, 3500, 1200, 300, 5000);
        \Tpb\Domain\Pricing\CostVersionRepo::addItem($cvId, 'variant', $variantId, 'BLANK_CENTS', 800);
        \Tpb\Domain\Pricing\CostVersionRepo::addItem($cvId, 'variant', $variantId, 'MATERIAL_CENTS', 200);
        \Tpb\Domain\Pricing\CostVersionRepo::addItem($cvId, 'technique', $flexId, 'SETUP_MIN', 10);
        \Tpb\Domain\Pricing\CostVersionRepo::addItem($cvId, 'technique', $flexId, 'UNIT_MIN', 3);
        \Tpb\Domain\Pricing\CostVersionRepo::addItem($cvId, 'technique', $flexId, 'MACHINE_MIN', 1);
        \Tpb\Domain\Pricing\CostVersionRepo::publish($costVersion, null);

        return [$productPublic, $variantSku, $bookVersion, $costVersion];
    });

    echo "Testobjekt angelegt:\n";
    echo "  Produkt public_id : {$productPublic}\n";
    echo "  Variante SKU      : {$variantSku}\n";
    echo "  Technik           : FLEX\n";
    echo "  Preisbuch         : v{$bookVersion} (published)\n";
    echo "  Kostenversion     : v{$costVersion} (published)\n";
}

/**
 * M3-Grundlagen: veröffentlichte PLATZHALTER-Rechtstexte (DECISIONS #25) und die
 * Angebots-Gültigkeitsdauer (business_settings). Kein echter Rechtstext – bewusst
 * als Platzhalter markiert, damit Consent-/Snapshot-Mechanik testbar ist. Idempotent.
 */
function seed_m3_basics(): void
{
    $now = \Tpb\Core\Clock::nowUtcSeconds();

    $docs = [
        'agb'         => 'Allgemeine Geschäftsbedingungen',
        'datenschutz' => 'Datenschutzerklärung',
        'widerruf'    => 'Widerrufsbelehrung und Erlöschen des Widerrufsrechts bei personalisierter Ware',
        'datei'       => 'Erklärung zu Druckdaten und Nutzungsrechten',
    ];

    foreach ($docs as $type => $title) {
        $exists = \Tpb\Core\Db::run(
            "SELECT id FROM legal_document_versions WHERE doc_type = ? AND language = 'de' AND status = 'published' LIMIT 1",
            [$type]
        )->fetch();
        if ($exists !== false) {
            echo "Rechtstext '{$type}' (published) existiert bereits – übersprungen.\n";
            continue;
        }
        $content = "# {$title}\n\n"
            . "PLATZHALTER – juristisch geprüfte Fassung ausstehend (siehe docs/OFFENE-FRAGEN.md).\n\n"
            . "Dieser Text dient ausschließlich dazu, den Zustimmungs- und Snapshot-Mechanismus "
            . "technisch zu ermöglichen. Er stellt KEINE rechtsverbindliche Erklärung dar.";
        \Tpb\Core\Db::run(
            "INSERT INTO legal_document_versions
                (doc_type, language, version, content, content_hash, status, valid_from, approved_at, created_at)
             VALUES (?, 'de', 'v0-PLATZHALTER', ?, ?, 'published', ?, ?, ?)",
            [$type, $content, hash('sha256', $content), $now, $now, $now]
        );
        echo "Rechtstext '{$type}' (v0-PLATZHALTER, published) angelegt.\n";
    }

    // Angebots-Gültigkeitsdauer (Platzhalter-Default 14 Tage).
    \Tpb\Core\Db::run(
        "INSERT INTO business_settings (setting_key, value_json, updated_at)
         VALUES ('reminder.quote_expiry_days', '14', ?)
         ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), updated_at = VALUES(updated_at)",
        [$now]
    );
    echo "business_settings.reminder.quote_expiry_days = 14 gesetzt.\n";
}

/**
 * M6-Grundlagen: PLATZHALTER-Steuerregime (Art. 57bis Franchise = keine USt), Verkäufer-
 * Snapshot und Zahlungsziel. Alles klar als Platzhalter markiert – echte Werte (Volltext
 * Art. 57bis, Firmendaten) blockieren den Go-live und werden nicht erfunden (§14.4). Idempotent.
 */
function seed_m6_basics(): void
{
    $now = \Tpb\Core\Clock::nowUtcSeconds();
    $today = \Tpb\Core\Clock::nowUtc()->format('Y-m-d');

    $exists = \Tpb\Core\Db::run("SELECT id FROM tax_regime_versions WHERE regime_code = 'FRANCHISE_57BIS' LIMIT 1")->fetch();
    if ($exists === false) {
        \Tpb\Core\Db::run(
            "INSERT INTO tax_regime_versions (regime_code, legend_text, valid_from, created_at)
             VALUES ('FRANCHISE_57BIS', ?, ?, ?)",
            ['PLATZHALTER – Steuerregelung (Art. 57bis / Kleinunternehmer): keine USt ausgewiesen. Juristischer Volltext ausstehend.', $today, $now]
        );
        echo "tax_regime_versions FRANCHISE_57BIS (Platzhalter) angelegt.\n";
    } else {
        echo "tax_regime_versions FRANCHISE_57BIS existiert bereits – übersprungen.\n";
    }

    $seller = json_encode([
        'placeholder'   => true,
        'name'          => 'The Printing Brothers (PLATZHALTER)',
        'address_lines' => ['Adresse ausstehend', 'L-0000 Luxembourg'],
        'contact'       => 'kontakt@tpb.local',
        'legal_note'    => 'Rechtsform/Autorisation/Registernummern ausstehend (blockiert Go-live).',
    ], JSON_UNESCAPED_UNICODE);
    foreach ([
        'seller.snapshot'    => $seller,
        'invoice.due_days'   => '30',
        'tax.active_regime'  => '"FRANCHISE_57BIS"',
        // Versandkosten (Owner 2026-08-16, Beträge Platzhalter): national Post LU,
        // international DHL, national gratis ab 50 €. Im Preis einkalkuliert.
        'shipping.national_cents'                => '500',
        'shipping.international_cents'            => '1500',
        'shipping.free_national_threshold_cents' => '5000',
        'shipping.national_carrier'              => '"Post Luxembourg"',
        'shipping.international_carrier'          => '"DHL"',
        // Wochenkapazität (M9, Platzhalter 40 h) für die Kapazitätsampel.
        'capacity.week_minutes'                  => '2400',
    ] as $k => $v) {
        \Tpb\Core\Db::run(
            "INSERT INTO business_settings (setting_key, value_json, updated_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), updated_at = VALUES(updated_at)",
            [$k, $v, $now]
        );
    }
    echo "business_settings: seller.snapshot (Platzhalter), invoice.due_days=30, tax.active_regime gesetzt.\n";
}
