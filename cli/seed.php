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
    foreach ($scenarios as $n => $desc) {
        echo "  --scenario={$n}  {$desc}\n";
    }
    exit(0);
}

if ($scenario === 'test') {
    seed_test_object();
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
