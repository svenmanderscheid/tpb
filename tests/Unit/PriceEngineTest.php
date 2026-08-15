<?php
declare(strict_types=1);

namespace Tpb\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tpb\Core\Money;
use Tpb\Domain\Pricing\CostVersion;
use Tpb\Domain\Pricing\PriceBook;
use Tpb\Domain\Pricing\PriceEngine;
use Tpb\Domain\Pricing\PricingException;

final class PriceEngineTest extends TestCase
{
    private function book(): PriceBook
    {
        return new PriceBook(
            version: 1,
            currency: 'EUR',
            tiers: [
                // Produkt 1 (configurable)
                ['product_id' => 1, 'qty_from' => 1,  'qty_to' => 4,    'unit_cents' => 2000],
                ['product_id' => 1, 'qty_from' => 5,  'qty_to' => 9,    'unit_cents' => 1800],
                ['product_id' => 1, 'qty_from' => 10, 'qty_to' => 24,   'unit_cents' => 1600],
                ['product_id' => 1, 'qty_from' => 25, 'qty_to' => 49,   'unit_cents' => 1400],
                ['product_id' => 1, 'qty_from' => 50, 'qty_to' => null, 'unit_cents' => 1200],
                // Produkt 2 (standard)
                ['product_id' => 2, 'qty_from' => 1,  'qty_to' => 9,    'unit_cents' => 1000],
                ['product_id' => 2, 'qty_from' => 10, 'qty_to' => null, 'unit_cents' => 800],
            ],
            params: [
                'SETUP_FEE_CENTS_PER_MOTIF' => 5000,
                'SETUP_FEE_WAIVER_QTY'      => 50,
                'EXTRA_POSITION_CENTS'      => 300,
                'EXTRA_COLOR_CENTS'         => 200,
                'NAME_NUMBER_CENTS'         => 250,
                'FILEPREP_CENTS'            => 1500,
                'EXPRESS_BPS'               => 2500,
                'MIN_ORDER_CENTS'           => 3000,
                'TECH_SURCHARGE_FLEX_CENTS' => 100,
            ],
        );
    }

    private function cost(): CostVersion
    {
        return new CostVersion(
            version: 1,
            laborRateCentsH: 6000,
            machineRateCentsH: 3000,
            scrapBps: 0,
            targetMarginBps: 5000,
            items: [
                'variant:5:BLANK_CENTS'    => 1000,
                'variant:5:MATERIAL_CENTS' => 500,
                'technique:7:SETUP_MIN'    => 10,
                'technique:7:UNIT_MIN'     => 5,
                'technique:7:MACHINE_MIN'  => 2,
            ],
        );
    }

    /** @return array<string,mixed> */
    private function configuredItem(int $qty, array $overrides = []): array
    {
        return array_merge([
            'product_id'     => 1,
            'type'           => 'configured',
            'technique_code' => null,
            'technique_id'   => 7,
            'positions'      => 1,
            'extra_colors'   => 0,
            'sizes'          => [['variant_id' => 5, 'qty' => $qty]],
            'units'          => [],
            'motifs'         => [],
        ], $overrides);
    }

    // -- Staffelgrenzen (DoD) ---------------------------------------------

    #[DataProvider('tierBoundaries')]
    public function testTierBoundaries(int $qty, int $expectedUnit): void
    {
        self::assertSame($expectedUnit, $this->book()->unitTier(1, $qty));
    }

    /** @return array<string,array{0:int,1:int}> */
    public static function tierBoundaries(): array
    {
        return [
            'qty 4'  => [4, 2000], 'qty 5'  => [5, 1800],
            'qty 9'  => [9, 1800], 'qty 10' => [10, 1600],
            'qty 24' => [24, 1600], 'qty 25' => [25, 1400],
            'qty 49' => [49, 1400], 'qty 50' => [50, 1200],
        ];
    }

    public function testMissingTierThrows(): void
    {
        $pb = new PriceBook(1, 'EUR', [['product_id' => 1, 'qty_from' => 1, 'qty_to' => 4, 'unit_cents' => 2000]], []);
        $this->expectException(PricingException::class);
        $pb->unitTier(1, 5);
    }

    // -- Zuschläge & Personalisierung -------------------------------------

    public function testConfiguredLineWithSurchargesAndPersonalisation(): void
    {
        $config = ['express' => false, 'fileprep' => false, 'items' => [
            $this->configuredItem(10, [
                'technique_code' => 'FLEX',
                'positions'      => 2,
                'extra_colors'   => 1,
                'units'          => [['name' => 'Max'], ['number' => '10'], ['name' => 'Ida'], []],
                'motifs'         => ['aaa', 'bbb'],
            ]),
        ]];
        $b = PriceEngine::calculate($config, $this->book(), $this->cost());

        // unit 1600 + (FLEX 100 + extraPos 300 + extraColor 200) = 2200; perso 3*250=750
        // line = 10*2200 + 750 = 22750; setups 2*5000=10000 (waiver ab 50, hier 10)
        self::assertSame(22750, $b->items[0]['line_cents']);
        self::assertSame(10000, $b->setupsCents);
        self::assertSame(32750, $b->subtotalCents);
        self::assertSame(32750, $b->totalCents);
        self::assertFalse($b->belowMinOrder);
    }

    public function testSetupWaiverAboveThreshold(): void
    {
        $config = ['express' => false, 'fileprep' => false, 'items' => [
            $this->configuredItem(50, ['motifs' => ['aaa', 'bbb']]),
        ]];
        $b = PriceEngine::calculate($config, $this->book(), $this->cost());
        self::assertSame(0, $b->setupsCents, 'Setup entfällt ab SETUP_FEE_WAIVER_QTY');
    }

    public function testExpressRoundingHalfUp(): void
    {
        $config = ['express' => true, 'fileprep' => false, 'items' => [
            $this->configuredItem(10, ['motifs' => ['aaa']]),
        ]];
        $b = PriceEngine::calculate($config, $this->book(), $this->cost());
        // subtotal = 10*1600 + 5000 (1 Motiv) = 21000; express 25% = 5250
        self::assertSame(21000, $b->subtotalCents);
        self::assertSame(Money::bp(21000, 2500), $b->expressCents);
        self::assertSame(5250, $b->expressCents);
        self::assertSame(26250, $b->totalCents);
    }

    public function testBelowMinOrderFlag(): void
    {
        $config = ['express' => false, 'fileprep' => false, 'items' => [
            $this->configuredItem(1), // 1*2000 = 2000, kein Motiv -> kein Setup
        ]];
        $b = PriceEngine::calculate($config, $this->book(), $this->cost());
        self::assertSame(2000, $b->totalCents);
        self::assertTrue($b->belowMinOrder);
    }

    // -- Interne Untergrenze (DoD) ----------------------------------------

    public function testBelowFloorComputation(): void
    {
        $config = ['express' => false, 'fileprep' => false, 'items' => [
            $this->configuredItem(10), // total = 10*1600 = 16000
        ]];
        $b = PriceEngine::calculate($config, $this->book(), $this->cost());

        // selbst/Stück = BLANK 1000 + MATERIAL 500 + Labor 600 + Maschine 100 = 2200
        // Labor = round(6000*(10 + 5*10)/(60*10)) = 600; Maschine = round(3000*2/60) = 100
        // selbst_gesamt = 2200*10 = 22000; floor = ceil(22000*10000 / 5000) = 44000
        self::assertSame(44000, $b->floorCents);
        self::assertTrue($b->belowFloor);
        self::assertSame(16000, $b->totalCents);
    }

    // -- Standardartikel (v1.4) -------------------------------------------

    public function testStandardArticleHasNoSurchargesOrSetup(): void
    {
        $config = ['express' => false, 'fileprep' => false, 'items' => [
            [
                'product_id' => 2, 'type' => 'standard',
                'technique_code' => 'FLEX', 'technique_id' => 7, // müssen ignoriert werden
                'positions' => 3, 'extra_colors' => 2,
                'sizes' => [['variant_id' => 9, 'qty' => 5]],
                'units' => [['name' => 'X']], 'motifs' => ['zzz'],
            ],
        ]];
        $b = PriceEngine::calculate($config, $this->book(), $this->cost());
        self::assertSame(5000, $b->items[0]['line_cents']); // 5 * 1000, keine Zuschläge
        self::assertSame(0, $b->items[0]['piece_surcharge_cents']);
        self::assertSame(0, $b->setupsCents); // Standardartikel liefern kein Motiv
    }

    public function testMixedDraftTotalsAcrossItemTypes(): void
    {
        $config = ['express' => false, 'fileprep' => false, 'items' => [
            $this->configuredItem(10, ['motifs' => ['aaa']]),                  // 16000 + Setup 5000
            ['product_id' => 2, 'type' => 'standard', 'technique_id' => null,
             'positions' => 0, 'extra_colors' => 0,
             'sizes' => [['variant_id' => 9, 'qty' => 5]], 'units' => [], 'motifs' => []], // 5000
        ]];
        $b = PriceEngine::calculate($config, $this->book(), $this->cost());
        self::assertSame(16000 + 5000, $b->items[0]['line_cents'] + $b->setupsCents);
        self::assertSame(5000, $b->items[1]['line_cents']);
        self::assertSame(16000 + 5000 + 5000, $b->subtotalCents);
    }

    // -- Determinismus (DoD) ----------------------------------------------

    public function testCalcHashIsDeterministicRegardlessOfKeyOrder(): void
    {
        $a = ['express' => false, 'fileprep' => false, 'items' => [$this->configuredItem(10, ['motifs' => ['aaa']])]];
        $b = ['items' => [$this->configuredItem(10, ['motifs' => ['aaa']])], 'fileprep' => false, 'express' => false];
        self::assertSame(
            PriceEngine::calculate($a, $this->book(), $this->cost())->calcHash,
            PriceEngine::calculate($b, $this->book(), $this->cost())->calcHash
        );
    }

    public function testCalcHashChangesWithInput(): void
    {
        $a = ['express' => false, 'fileprep' => false, 'items' => [$this->configuredItem(10, ['motifs' => ['aaa']])]];
        $b = ['express' => false, 'fileprep' => false, 'items' => [$this->configuredItem(11, ['motifs' => ['aaa']])]];
        self::assertNotSame(
            PriceEngine::calculate($a, $this->book(), $this->cost())->calcHash,
            PriceEngine::calculate($b, $this->book(), $this->cost())->calcHash
        );
    }
}
