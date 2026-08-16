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
use Tpb\Domain\Order\OrderRepo;
use Tpb\Domain\Payment\Gateway\TestGateway;
use Tpb\Domain\Payment\PaymentIntentRepo;
use Tpb\Domain\Pricing\CostVersionRepo;
use Tpb\Domain\Pricing\PriceBookRepo;
use Tpb\Domain\Pricing\PricingRepo;
use Tpb\Domain\Shop\CheckoutException;
use Tpb\Domain\Shop\CheckoutService;
use Tpb\Domain\Shop\ShopWebhookService;
use Tpb\Domain\Stock\StockRepo;
use Tpb\Domain\Stock\StockService;

/**
 * Lagermodul (DECISIONS #31): Verfügbarkeit = Bestand − Reserve − Reservierungen;
 * Reservierung beim Checkout (kein Oversell), Abbuchung erst bei Zahlung, Freigabe bei
 * Abbruch; Meldebestand-Alarme.
 */
final class StockFlowTest extends TestCase
{
    private string $productPublic;
    private int $variantId;

    private function cleanAll(): void
    {
        Db::run('UPDATE print_jobs SET reprint_of_id = NULL');
        Db::run('UPDATE invoices SET credited_invoice_id = NULL');
        foreach ([
            'stock_alerts', 'stock_reservations', 'stock_movements',
            'payment_webhook_events', 'payment_intents', 'idempotency_keys',
            'payment_allocations', 'payments', 'invoice_lines', 'invoices', 'expenses',
            'print_jobs', 'shipments', 'production_events', 'production_jobs',
            'proof_approvals', 'proofs', 'artwork_versions',
            'order_terms_acceptance', 'order_item_units', 'order_items', 'orders',
            'quote_items', 'quotes', 'price_calculations',
            'configuration_units', 'configuration_layers', 'configuration_item_sizes', 'configuration_items', 'configurations',
            'access_tokens', 'customer_consents', 'customers',
            'price_tiers', 'price_params', 'price_books', 'cost_items', 'cost_versions',
            'placements', 'product_variants', 'products', 'techniques', 'assets',
            'legal_document_versions', 'tax_regime_versions', 'business_settings', 'status_events', 'outbox_events', 'number_sequences',
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
        $today = Clock::nowUtc()->format('Y-m-d');

        TechniqueRepo::create('FLEX', 'Flexfolie');
        $this->productPublic = ProductRepo::create('configurable', 'TEST', 'Testobjekt', 'testobjekt', null, null);
        $productId = (int) ProductRepo::findByPublicId($this->productPublic)['id'];
        $this->variantId = VariantRepo::create($productId, 'TEST-BLK-M', 'BLK', 'Schwarz', 'M');
        PlacementRepo::create($productId, 'front', 'brust', 'Brust', [], true);
        $bv = PriceBookRepo::nextVersion();
        $bookId = PriceBookRepo::create($bv, 'EUR');
        PriceBookRepo::addTier($bookId, $productId, 1, 49, 1800);
        PriceBookRepo::setParam($bookId, 'TECH_SURCHARGE_FLEX_CENTS', 100, null);
        PriceBookRepo::setParam($bookId, 'SETUP_FEE_WAIVER_QTY', 50, null);
        PriceBookRepo::setParam($bookId, 'MIN_ORDER_CENTS', 1000, null);
        PriceBookRepo::publish($bv, null);
        $cvv = CostVersionRepo::nextVersion();
        CostVersionRepo::create($cvv, 3500, 1200, 300, 5000);
        CostVersionRepo::publish($cvv, null);

        foreach (['agb', 'widerruf'] as $t) {
            Db::run("INSERT INTO legal_document_versions (doc_type, language, version, content, content_hash, status, valid_from, created_at)
                     VALUES (?, 'de', 'v0', 'x', ?, 'published', ?, ?)", [$t, str_repeat('d', 64), $now, $now]);
        }
        Db::run("INSERT INTO tax_regime_versions (regime_code, legend_text, valid_from, created_at) VALUES ('FRANCHISE_57BIS', 'L', ?, ?)", [$today, $now]);
        foreach (['seller.snapshot' => '{"name":"TPB"}', 'invoice.due_days' => '30', 'tax.active_regime' => '"FRANCHISE_57BIS"'] as $k => $v) {
            Db::run('INSERT INTO business_settings (setting_key, value_json, updated_at) VALUES (?, ?, ?)', [$k, $v, $now]);
        }
    }

    private function setStock(int $qty, int $reserve = 3, int $threshold = 5): void
    {
        Db::run('UPDATE product_variants SET stock_qty = ?, reserve_qty = ?, reorder_threshold = ? WHERE id = ?', [$qty, $reserve, $threshold, $this->variantId]);
    }

    private function savedConfig(int $qty): string
    {
        $payload = ['express' => false, 'fileprep' => false, 'items' => [[
            'product' => $this->productPublic, 'type' => 'configured', 'technique_code' => 'FLEX',
            'sizes' => [['variant_sku' => 'TEST-BLK-M', 'qty' => $qty]],
            'layers' => [['placement_code' => 'brust', 'layer_type' => 'text', 'text_content' => 'X', 'width_mm' => '90', 'height_mm' => '20']],
            'units' => [],
        ]]];
        $ids = PricingRepo::activeIds();
        return (string) ConfigurationRepo::save(ConfigMapper::resolve($payload), ['price_book_id' => $ids['price_book_id'], 'cost_version_id' => $ids['cost_version_id']])['public_id'];
    }

    private function customer(): array
    {
        return ['type' => 'private', 'first_name' => 'M', 'last_name' => 'M', 'email' => 'm@example.com',
                'billing_street' => 'S', 'billing_zip' => 'Z', 'billing_city' => 'C', 'billing_country' => 'LU'];
    }

    public function testAvailabilityExcludesReserve(): void
    {
        $this->setStock(20, 3);
        self::assertSame(17, StockRepo::availability($this->variantId));
    }

    public function testCheckoutReservesAndBlocksOversell(): void
    {
        $this->setStock(20, 3); // verfügbar 17
        CheckoutService::start($this->savedConfig(10), $this->customer(), ['agb', 'widerruf']);
        self::assertSame(7, StockRepo::availability($this->variantId)); // 20-3-10

        $this->expectException(CheckoutException::class);
        CheckoutService::start($this->savedConfig(10), $this->customer(), ['agb', 'widerruf']); // nur 7 verfügbar
    }

    public function testPaidConsumesStock(): void
    {
        $this->setStock(20, 3);
        $res = CheckoutService::start($this->savedConfig(10), $this->customer(), ['agb', 'widerruf']);
        $intent = PaymentIntentRepo::findByPublicId($res['intent_public_id']);
        $payload = json_encode([
            'event_ref' => 'evt_' . $intent['provider_ref'], 'event_type' => 'payment.succeeded',
            'provider_ref' => $intent['provider_ref'], 'amount_cents' => (int) $intent['amount_cents'], 'currency' => 'EUR',
        ], JSON_UNESCAPED_SLASHES);
        ShopWebhookService::handle($payload, TestGateway::sign($payload));

        self::assertSame(10, (int) Db::run('SELECT stock_qty FROM product_variants WHERE id = ?', [$this->variantId])->fetchColumn());
        self::assertSame(1, (int) Db::run("SELECT COUNT(*) FROM stock_movements WHERE variant_id = ? AND reason = 'sale'", [$this->variantId])->fetchColumn());
        self::assertSame(0, StockRepo::reservedActive($this->variantId));
    }

    public function testReleaseRestoresAvailability(): void
    {
        $this->setStock(20, 3);
        $res = CheckoutService::start($this->savedConfig(10), $this->customer(), ['agb', 'widerruf']);
        self::assertSame(7, StockRepo::availability($this->variantId));

        StockService::releaseForOrder((int) OrderRepo::findByPublicId($res['order_public_id'])['id']);
        self::assertSame(17, StockRepo::availability($this->variantId)); // Reservierung frei
        self::assertSame(20, (int) Db::run('SELECT stock_qty FROM product_variants WHERE id = ?', [$this->variantId])->fetchColumn());
    }

    public function testLowStockAlertsFireOncePerLevel(): void
    {
        $this->setStock(20, 0, 5);
        StockService::adjustTo($this->variantId, 4, 1); // <= Meldebestand 5
        self::assertSame(1, (int) Db::run("SELECT COUNT(*) FROM stock_alerts WHERE level = 'reorder'")->fetchColumn());
        self::assertSame(1, (int) Db::run("SELECT COUNT(*) FROM outbox_events WHERE event_type = 'mail.stock_low'")->fetchColumn());

        StockService::adjustTo($this->variantId, 0, 1); // kritisch + leer
        self::assertSame(1, (int) Db::run("SELECT COUNT(*) FROM stock_alerts WHERE level = 'critical'")->fetchColumn());
        self::assertSame(1, (int) Db::run("SELECT COUNT(*) FROM stock_alerts WHERE level = 'empty'")->fetchColumn());

        StockService::adjustTo($this->variantId, 10, 1); // wieder aufgefüllt → Stufen zurückgesetzt
        self::assertSame(0, (int) Db::run('SELECT COUNT(*) FROM stock_alerts')->fetchColumn());
    }
}
