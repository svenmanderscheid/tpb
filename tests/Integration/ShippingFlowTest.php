<?php
declare(strict_types=1);

namespace Tpb\Tests\Integration;

use PHPUnit\Framework\TestCase;
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
use Tpb\Domain\Quote\QuoteService;
use Tpb\Domain\Shipping\CarrierRegistry;
use Tpb\Domain\Shipping\ShipmentRepo;
use Tpb\Domain\Shipping\ShippingService;
use Tpb\Domain\Shipping\ShippingStateException;

/**
 * Versand & Fulfillment (DECISIONS #28): Versandart + Kosten, Fulfillment-Statusfluss
 * (Abholung vs. Versand), Hausetikett + Neudruck-Grund, günstigster Carrier.
 */
final class ShippingFlowTest extends TestCase
{
    private string $productPublic;

    private function cleanAll(): void
    {
        Db::run('UPDATE print_jobs SET reprint_of_id = NULL');
        foreach ([
            'print_jobs', 'shipments', 'production_events', 'production_jobs',
            'proof_approvals', 'proofs', 'artwork_versions',
            'order_terms_acceptance', 'order_item_units', 'order_items', 'orders',
            'quote_items', 'quotes', 'price_calculations',
            'configuration_units', 'configuration_layers', 'configuration_item_sizes', 'configuration_items', 'configurations',
            'access_tokens', 'customer_consents', 'customers',
            'price_tiers', 'price_params', 'price_books', 'cost_items', 'cost_versions',
            'placements', 'product_variants', 'products', 'techniques', 'assets',
            'legal_document_versions', 'status_events', 'outbox_events', 'number_sequences',
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
        PriceBookRepo::addTier($bookId, $productId, 1, 49, 1800);
        PriceBookRepo::setParam($bookId, 'TECH_SURCHARGE_FLEX_CENTS', 100, null);
        PriceBookRepo::setParam($bookId, 'SETUP_FEE_WAIVER_QTY', 50, null);
        PriceBookRepo::publish($bv, null);
        $cvv = CostVersionRepo::nextVersion();
        CostVersionRepo::create($cvv, 3500, 1200, 300, 5000);
        CostVersionRepo::publish($cvv, null);
    }

    private function order(): array
    {
        $payload = ['express' => false, 'fileprep' => false, 'items' => [[
            'product' => $this->productPublic, 'type' => 'configured', 'technique_code' => 'FLEX',
            'sizes' => [['variant_sku' => 'TEST-BLK-M', 'qty' => 10]],
            'layers' => [['placement_code' => 'brust', 'layer_type' => 'text', 'text_content' => 'TPB', 'width_mm' => '90', 'height_mm' => '20']],
            'units' => [],
        ]]];
        $ids = PricingRepo::activeIds();
        $saved = ConfigurationRepo::save(ConfigMapper::resolve($payload), ['price_book_id' => $ids['price_book_id'], 'cost_version_id' => $ids['cost_version_id']]);
        $cust = CustomerRepo::upsert([
            'type' => 'business', 'company_name' => 'FC Test', 'first_name' => 'Max', 'last_name' => 'Muster', 'email' => 'max@example.com',
            'delivery_street' => 'Rue 1', 'delivery_zip' => 'L-1000', 'delivery_city' => 'Luxembourg', 'delivery_country' => 'LU',
        ]);
        Db::run("UPDATE configurations SET customer_id = ?, status = 'submitted' WHERE id = ?", [$cust['id'], $saved['id']]);
        $quote = QuoteService::createFromConfiguration((int) $saved['id'], 1);
        $sent = QuoteService::send((int) $quote['id'], 1);
        $acc = QuoteService::accept($quote['public_id'], $sent['token']);
        return Db::run('SELECT * FROM orders WHERE id = ?', [$acc['order_id']])->fetch();
    }

    public function testCheapestCarrierIsHouseForNow(): void
    {
        self::assertSame('house', CarrierRegistry::cheapest(['shipping_cost_cents' => 0])->code());
    }

    public function testConfigureSeedsAddressAndCost(): void
    {
        $order = $this->order();
        $sh = ShippingService::ensure((int) $order['id'], 1);
        // Lieferadresse aus dem Kundenstamm übernommen.
        self::assertSame('Rue 1', $sh['street']);
        self::assertSame('LU', $sh['country']);

        ShippingService::configure((int) $order['id'], ['method' => 'carrier_standard', 'carrier' => 'Günstig-Express', 'shipping_cost_cents' => 750], 1);
        $sh = ShipmentRepo::findByOrderId((int) $order['id']);
        self::assertSame('carrier_standard', $sh['method']);
        self::assertSame('Günstig-Express', $sh['carrier']);
        self::assertSame(750, (int) $sh['shipping_cost_cents']);

        // Standardversand ohne Anbieter → günstigster (House) als Fallback.
        ShippingService::configure((int) $order['id'], ['method' => 'carrier_standard', 'carrier' => '', 'shipping_cost_cents' => 500], 1);
        self::assertSame('Eigenlieferung', ShipmentRepo::findByOrderId((int) $order['id'])['carrier']);
        // Abholung setzt Kosten auf 0.
        ShippingService::configure((int) $order['id'], ['method' => 'pickup', 'shipping_cost_cents' => 999], 1);
        self::assertSame(0, (int) ShipmentRepo::findByOrderId((int) $order['id'])['shipping_cost_cents']);
    }

    public function testShipPathWithTimestampsAndLabel(): void
    {
        $order = $this->order();
        ShippingService::configure((int) $order['id'], ['method' => 'carrier_standard', 'carrier' => 'Günstig-Express', 'shipping_cost_cents' => 750], 1);

        ShippingService::advance((int) $order['id'], 'pack', 1);
        ShippingService::advance((int) $order['id'], 'ready', 1);
        self::assertSame('READY_TO_SHIP', Db::run('SELECT cur_fulfillment FROM orders WHERE id = ?', [(int) $order['id']])->fetchColumn());
        ShippingService::advance((int) $order['id'], 'ship', 1);
        ShippingService::advance((int) $order['id'], 'deliver', 1);
        self::assertSame('DELIVERED', Db::run('SELECT cur_fulfillment FROM orders WHERE id = ?', [(int) $order['id']])->fetchColumn());

        $sh = ShipmentRepo::findByOrderId((int) $order['id']);
        self::assertNotNull($sh['packed_at']);
        self::assertNotNull($sh['shipped_at']);
        self::assertNotNull($sh['delivered_at']);

        // Hausetikett rendern → print_jobs (entity shipment), label_asset_id gesetzt.
        $r1 = ShippingService::renderLabel((int) $order['id'], 1);
        self::assertSame(1, (int) Db::run("SELECT COUNT(*) FROM print_jobs WHERE entity_type='shipment' AND entity_id=?", [(int) $sh['id']])->fetchColumn());
        self::assertNotNull(ShipmentRepo::findByOrderId((int) $order['id'])['label_asset_id']);

        // Neudruck ohne Grund → Ausnahme; mit Grund → reprint_of + Grund.
        try {
            ShippingService::renderLabel((int) $order['id'], 1, $r1['print_job_id'], '');
            self::fail('Neudruck ohne Grund muss fehlschlagen.');
        } catch (\InvalidArgumentException) {
        }
        $r2 = ShippingService::renderLabel((int) $order['id'], 1, $r1['print_job_id'], 'Adresse korrigiert');
        $row = Db::run('SELECT reprint_of_id, reprint_reason FROM print_jobs WHERE id = ?', [$r2['print_job_id']])->fetch();
        self::assertSame($r1['print_job_id'], (int) $row['reprint_of_id']);
        self::assertSame('Adresse korrigiert', $row['reprint_reason']);
    }

    public function testPickupPath(): void
    {
        $order = $this->order();
        ShippingService::configure((int) $order['id'], ['method' => 'pickup'], 1);
        ShippingService::advance((int) $order['id'], 'pack', 1);
        ShippingService::advance((int) $order['id'], 'ready', 1);
        self::assertSame('READY_FOR_PICKUP', Db::run('SELECT cur_fulfillment FROM orders WHERE id = ?', [(int) $order['id']])->fetchColumn());
        ShippingService::advance((int) $order['id'], 'collect', 1);
        self::assertSame('COLLECTED', Db::run('SELECT cur_fulfillment FROM orders WHERE id = ?', [(int) $order['id']])->fetchColumn());
    }

    public function testIllegalTransitionRejected(): void
    {
        $order = $this->order();
        ShippingService::configure((int) $order['id'], ['method' => 'carrier_standard', 'carrier' => 'X'], 1);
        $this->expectException(ShippingStateException::class);
        ShippingService::advance((int) $order['id'], 'ship', 1); // aus UNFULFILLED nicht erlaubt
    }
}
