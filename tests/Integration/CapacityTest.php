<?php
declare(strict_types=1);

namespace Tpb\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Catalog\PlacementRepo;
use Tpb\Domain\Catalog\ProductRepo;
use Tpb\Domain\Catalog\TechniqueRepo;
use Tpb\Domain\Catalog\VariantRepo;
use Tpb\Domain\Config\ConfigMapper;
use Tpb\Domain\Config\ConfigurationRepo;
use Tpb\Domain\Customer\CustomerRepo;
use Tpb\Domain\Pricing\CostVersionRepo;
use Tpb\Domain\Pricing\PriceBookRepo;
use Tpb\Domain\Pricing\PricingRepo;
use Tpb\Domain\Production\JobRepo;
use Tpb\Domain\Production\ProductionService;
use Tpb\Domain\Quote\QuoteService;
use Tpb\Domain\Report\CapacityReport;

/**
 * M9-DoD (Kapazität): planned_min je Job aus der Kostenversion; Wochenaggregation gegen
 * capacity.week_minutes (Ampel). Rechnung stimmt gegen die Seeds.
 */
final class CapacityTest extends TestCase
{
    private string $productPublic;

    private function cleanAll(): void
    {
        Db::run('UPDATE print_jobs SET reprint_of_id = NULL');
        foreach ([
            'print_jobs', 'production_events', 'production_jobs', 'proof_approvals', 'proofs', 'artwork_versions',
            'order_terms_acceptance', 'order_item_units', 'order_items', 'orders',
            'quote_items', 'quotes', 'price_calculations',
            'configuration_units', 'configuration_layers', 'configuration_item_sizes', 'configuration_items', 'configurations',
            'access_tokens', 'customer_consents', 'customers',
            'price_tiers', 'price_params', 'price_books', 'cost_items', 'cost_versions',
            'placements', 'product_variants', 'products', 'techniques', 'assets',
            'legal_document_versions', 'business_settings', 'status_events', 'outbox_events', 'number_sequences',
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
        $now = Clock::nowUtcSeconds();
        TechniqueRepo::create('FLEX', 'Flexfolie');
        $flexId = (int) TechniqueRepo::idByCode('FLEX');
        $this->productPublic = ProductRepo::create('configurable', 'TEST', 'Testobjekt', 'testobjekt', null, null);
        $productId = (int) ProductRepo::findByPublicId($this->productPublic)['id'];
        VariantRepo::create($productId, 'TEST-BLK-M', 'BLK', 'Schwarz', 'M');
        PlacementRepo::create($productId, 'front', 'brust', 'Brust', [], true);
        $bv = PriceBookRepo::nextVersion();
        $bookId = PriceBookRepo::create($bv, 'EUR');
        PriceBookRepo::addTier($bookId, $productId, 1, 49, 1800);
        PriceBookRepo::setParam($bookId, 'TECH_SURCHARGE_FLEX_CENTS', 100, null);
        PriceBookRepo::setParam($bookId, 'SETUP_FEE_WAIVER_QTY', 50, null);
        PriceBookRepo::publish($bv, null);
        $cvv = CostVersionRepo::nextVersion();
        $cvId = CostVersionRepo::create($cvv, 3500, 1200, 300, 5000);
        CostVersionRepo::addItem($cvId, 'technique', $flexId, 'SETUP_MIN', 10);
        CostVersionRepo::addItem($cvId, 'technique', $flexId, 'UNIT_MIN', 3);
        CostVersionRepo::addItem($cvId, 'technique', $flexId, 'MACHINE_MIN', 1);
        CostVersionRepo::publish($cvv, null);
        Db::run("INSERT INTO business_settings (setting_key, value_json, updated_at) VALUES ('capacity.week_minutes','2400',?)", [$now]);
        Db::run("INSERT INTO legal_document_versions (doc_type, language, version, content, content_hash, status, valid_from, created_at) VALUES ('agb','de','v0','x',?,'published',?,?)", [str_repeat('a', 64), $now, $now]);
    }

    private function acceptedOrderId(): int
    {
        $payload = ['express' => false, 'fileprep' => false, 'items' => [[
            'product' => $this->productPublic, 'type' => 'configured', 'technique_code' => 'FLEX',
            'sizes' => [['variant_sku' => 'TEST-BLK-M', 'qty' => 10]],
            'layers' => [['placement_code' => 'brust', 'layer_type' => 'text', 'text_content' => 'X', 'width_mm' => '90', 'height_mm' => '20']],
            'units' => [],
        ]]];
        $ids = PricingRepo::activeIds();
        $saved = ConfigurationRepo::save(ConfigMapper::resolve($payload), ['price_book_id' => $ids['price_book_id'], 'cost_version_id' => $ids['cost_version_id']]);
        $cust = CustomerRepo::upsert(['type' => 'business', 'company_name' => 'FC', 'first_name' => 'M', 'last_name' => 'M', 'email' => 'm@example.com']);
        Db::run("UPDATE configurations SET customer_id = ?, status = 'submitted' WHERE id = ?", [$cust['id'], $saved['id']]);
        $quote = QuoteService::createFromConfiguration((int) $saved['id'], 1);
        $sent = QuoteService::send((int) $quote['id'], 1);
        return (int) QuoteService::accept($quote['public_id'], $sent['token'])['order_id'];
    }

    public function testPlannedMinutesFromCostVersion(): void
    {
        $orderId = $this->acceptedOrderId();
        $jobs = ProductionService::createJobs($orderId, 1);
        // SETUP_MIN 10 + (UNIT_MIN 3 + MACHINE_MIN 1) * 10 = 50
        self::assertSame(50, (int) JobRepo::findByPublicId($jobs[0]['public_id'])['planned_min']);
    }

    public function testWeeklyCapacityAggregation(): void
    {
        $orderId = $this->acceptedOrderId();
        $jobs = ProductionService::createJobs($orderId, 1);
        $monday = Clock::nowUtc()->modify('monday this week')->format('Y-m-d');
        ProductionService::setDueDate($jobs[0]['public_id'], $monday, 1);

        $weeks = CapacityReport::weeks();
        self::assertCount(1, $weeks);
        self::assertSame(50, (int) $weeks[0]['planned_min']);
        self::assertSame(2400, (int) $weeks[0]['capacity']);
        self::assertSame('green', $weeks[0]['light']);
        self::assertSame(50, CapacityReport::loadForWeekOf($monday));
    }
}
