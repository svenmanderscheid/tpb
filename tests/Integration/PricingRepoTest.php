<?php
declare(strict_types=1);

namespace Tpb\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tpb\Core\Db;
use Tpb\Domain\Catalog\PlacementRepo;
use Tpb\Domain\Catalog\ProductRepo;
use Tpb\Domain\Catalog\TechniqueRepo;
use Tpb\Domain\Catalog\VariantRepo;
use Tpb\Domain\Pricing\CostVersionRepo;
use Tpb\Domain\Pricing\PriceBookRepo;
use Tpb\Domain\Pricing\PriceEngine;
use Tpb\Domain\Pricing\PricingRepo;

/**
 * Sichert den DB→Value-Object-Pfad: veröffentlichte Preisbücher/Kostenversionen
 * werden korrekt geladen und von der Engine verarbeitet (M2-Live-Preis).
 */
final class PricingRepoTest extends TestCase
{
    protected function setUp(): void
    {
        foreach (['price_tiers', 'price_params', 'price_books', 'cost_items', 'cost_versions', 'placements', 'product_variants', 'products', 'techniques'] as $t) {
            Db::run("DELETE FROM {$t}");
        }
    }

    public function testLoadsPublishedBookAndComputesPrice(): void
    {
        // Technik + Produkt + Variante
        TechniqueRepo::create('FLEX', 'Flexfolie');
        $flexId = (int) TechniqueRepo::idByCode('FLEX');
        $pub = ProductRepo::create('configurable', 'TEST', 'Testobjekt', 'testobjekt', null, null);
        $productId = (int) ProductRepo::findByPublicId($pub)['id'];
        $variantId = VariantRepo::create($productId, 'TEST-BLK-M', 'BLK', 'Schwarz', 'M');
        PlacementRepo::create($productId, 'front', 'brust', 'Brust', [], true);

        // Preisbuch veröffentlicht
        $bv = PriceBookRepo::nextVersion();
        $bookId = PriceBookRepo::create($bv, 'EUR');
        PriceBookRepo::addTier($bookId, $productId, 1, 9, 2000);
        PriceBookRepo::addTier($bookId, $productId, 10, 49, 1800);
        PriceBookRepo::setParam($bookId, 'TECH_SURCHARGE_FLEX_CENTS', 100, null);
        PriceBookRepo::setParam($bookId, 'MIN_ORDER_CENTS', 3000, null);
        PriceBookRepo::publish($bv, null);

        // Kostenversion veröffentlicht
        $cvv = CostVersionRepo::nextVersion();
        $cvId = CostVersionRepo::create($cvv, 3500, 1200, 300, 5000);
        CostVersionRepo::addItem($cvId, 'variant', $variantId, 'BLANK_CENTS', 800);
        CostVersionRepo::publish($cvv, null);

        $pb = PricingRepo::activePriceBook();
        $cv = PricingRepo::activeCostVersion();
        self::assertNotNull($pb);
        self::assertNotNull($cv);
        self::assertSame($bv, $pb->version);

        // 10 Stück FLEX, 1 Position: 10 * (1800 + 100) = 19000
        $config = ['express' => false, 'fileprep' => false, 'items' => [[
            'product_id' => $productId, 'type' => 'configured',
            'technique_code' => 'FLEX', 'technique_id' => $flexId,
            'positions' => 1, 'extra_colors' => 0,
            'sizes' => [['variant_id' => $variantId, 'qty' => 10]],
            'units' => [], 'motifs' => [],
        ]]];
        $b = PriceEngine::calculate($config, $pb, $cv);
        self::assertSame(19000, $b->items[0]['line_cents']);
        self::assertSame(19000, $b->totalCents);
        self::assertFalse($b->belowMinOrder);
    }

    public function testNoPublishedBookReturnsNull(): void
    {
        // Nur ein Entwurf, nicht veröffentlicht
        PriceBookRepo::create(PriceBookRepo::nextVersion(), 'EUR');
        self::assertNull(PricingRepo::activePriceBook());
    }
}
