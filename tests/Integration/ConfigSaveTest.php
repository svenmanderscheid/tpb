<?php
declare(strict_types=1);

namespace Tpb\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;
use Tpb\Domain\Catalog\PlacementRepo;
use Tpb\Domain\Catalog\ProductRepo;
use Tpb\Domain\Catalog\TechniqueRepo;
use Tpb\Domain\Catalog\VariantRepo;
use Tpb\Domain\Config\ConfigMapper;
use Tpb\Domain\Config\ConfigurationRepo;
use Tpb\Domain\Pricing\CostVersionRepo;
use Tpb\Domain\Pricing\PriceBookRepo;
use Tpb\Domain\Pricing\PriceEngine;
use Tpb\Domain\Pricing\PricingRepo;

/**
 * Round-trip: Konfiguration auflösen → Preis rechnen → speichern → laden.
 * Positionen/Motive werden serverseitig aus den Layern abgeleitet (M2).
 */
final class ConfigSaveTest extends TestCase
{
    private string $productPublic;
    private string $assetPublic;

    private function cleanAll(): void
    {
        foreach ([
            'price_calculations', 'configuration_units', 'configuration_layers', 'configuration_item_sizes',
            'configuration_items', 'configurations', 'price_tiers', 'price_params', 'price_books',
            'cost_items', 'cost_versions', 'placements', 'product_variants', 'products', 'techniques', 'assets',
        ] as $t) {
            Db::run("DELETE FROM {$t}");
        }
    }

    protected function tearDown(): void
    {
        $this->cleanAll();
    }

    protected function setUp(): void
    {
        $this->cleanAll();

        TechniqueRepo::create('FLEX', 'Flexfolie');
        $this->productPublic = ProductRepo::create('configurable', 'TEST', 'Testobjekt', 'testobjekt', null, null);
        $productId = (int) ProductRepo::findByPublicId($this->productPublic)['id'];
        VariantRepo::create($productId, 'TEST-BLK-M', 'BLK', 'Schwarz', 'M');
        PlacementRepo::create($productId, 'front', 'brust', 'Brust', [], true);

        $bv = PriceBookRepo::nextVersion();
        $bookId = PriceBookRepo::create($bv, 'EUR');
        PriceBookRepo::addTier($bookId, $productId, 1, 9, 2000);
        PriceBookRepo::addTier($bookId, $productId, 10, 49, 1800);
        PriceBookRepo::setParam($bookId, 'TECH_SURCHARGE_FLEX_CENTS', 100, null);
        PriceBookRepo::setParam($bookId, 'NAME_NUMBER_CENTS', 250, null);
        PriceBookRepo::setParam($bookId, 'SETUP_FEE_CENTS_PER_MOTIF', 5000, null);
        PriceBookRepo::setParam($bookId, 'SETUP_FEE_WAIVER_QTY', 50, null);
        PriceBookRepo::publish($bv, null);

        $cvv = CostVersionRepo::nextVersion();
        CostVersionRepo::create($cvv, 3500, 1200, 300, 5000);
        CostVersionRepo::publish($cvv, null);

        // sauberes Asset (Logo-Motiv)
        $this->assetPublic = Ulid::generate();
        Db::run(
            'INSERT INTO assets (public_id, kind, original_name, mime, size_bytes, sha256, storage_key, security_status, retention_class, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$this->assetPublic, 'artwork', 'logo.png', 'image/png', 1234, str_repeat('a', 64), 'artwork/user-0/' . $this->assetPublic . '/original', 'clean', 'artwork_short', Clock::nowUtcSeconds()]
        );
    }

    private function payload(): array
    {
        return [
            'express' => false, 'fileprep' => false,
            'guest_email' => 'gast@example.com', 'guest_name' => 'Gast',
            'items' => [[
                'product' => $this->productPublic, 'type' => 'configured', 'technique_code' => 'FLEX',
                'sizes' => [['variant_sku' => 'TEST-BLK-M', 'qty' => 10]],
                'layers' => [[
                    'placement_code' => 'brust', 'layer_type' => 'logo', 'asset_public_id' => $this->assetPublic,
                    'width_mm' => '90', 'height_mm' => '90', 'offset_x_mm' => '0', 'offset_y_mm' => '0',
                ]],
                'units' => [['variant_sku' => 'TEST-BLK-M', 'name' => 'Max']],
            ]],
        ];
    }

    public function testResolveDerivesPositionsAndMotifs(): void
    {
        $engine = ConfigMapper::toEngineConfig(ConfigMapper::resolve($this->payload()));
        self::assertSame(1, $engine['items'][0]['positions']);
        self::assertSame([str_repeat('a', 64)], $engine['items'][0]['motifs']);
        self::assertCount(1, $engine['items'][0]['units']);
    }

    public function testPriceThenSaveThenReload(): void
    {
        $resolved = ConfigMapper::resolve($this->payload());
        $pb = PricingRepo::activePriceBook();
        $cv = PricingRepo::activeCostVersion();
        $ids = PricingRepo::activeIds();
        $b = PriceEngine::calculate(ConfigMapper::toEngineConfig($resolved), $pb, $cv);

        // 10 * (1800 + 100) + 250 (1 personalisiert) = 19250; Setup 1 Motiv = 5000; total 24250
        self::assertSame(24250, $b->totalCents);

        $saved = ConfigurationRepo::save($resolved, [
            'price_book_id' => $ids['price_book_id'], 'cost_version_id' => $ids['cost_version_id'],
            'guest_email' => 'gast@example.com', 'guest_name' => 'Gast', 'note' => null, 'public_id' => null,
        ]);
        ConfigurationRepo::persistCalculation(
            $saved['id'], $ids['price_book_id'], $ids['cost_version_id'],
            'inputhash', json_encode($b->toArray()), $b->totalCents, $b->floorCents, $b->belowFloor, $b->calcHash
        );

        $loaded = ConfigurationRepo::loadByPublicId($saved['public_id']);
        self::assertNotNull($loaded);
        self::assertSame($this->productPublic, $loaded['items'][0]['product']);
        self::assertSame('FLEX', $loaded['items'][0]['technique_code']);
        self::assertSame(10, $loaded['items'][0]['sizes'][0]['qty']);
        self::assertSame('brust', $loaded['items'][0]['layers'][0]['placement_code']);
        self::assertSame($this->assetPublic, $loaded['items'][0]['layers'][0]['asset_public_id']);
        self::assertSame('Max', $loaded['items'][0]['units'][0]['name']);
        self::assertSame(24250, $loaded['calculation']['total_cents']);
    }

    public function testUpdateExistingDraftReplacesItems(): void
    {
        $ids = PricingRepo::activeIds();
        $resolved = ConfigMapper::resolve($this->payload());
        $saved = ConfigurationRepo::save($resolved, ['price_book_id' => $ids['price_book_id'], 'cost_version_id' => $ids['cost_version_id']]);

        // Update: Menge auf 5 ändern
        $p2 = $this->payload();
        $p2['items'][0]['sizes'][0]['qty'] = 5;
        $p2['public_id'] = $saved['public_id'];
        $saved2 = ConfigurationRepo::save(ConfigMapper::resolve($p2), [
            'price_book_id' => $ids['price_book_id'], 'cost_version_id' => $ids['cost_version_id'], 'public_id' => $saved['public_id'],
        ]);

        self::assertSame($saved['public_id'], $saved2['public_id']);
        $loaded = ConfigurationRepo::loadByPublicId($saved['public_id']);
        self::assertCount(1, $loaded['items']);
        self::assertSame(5, $loaded['items'][0]['sizes'][0]['qty']);
    }
}
