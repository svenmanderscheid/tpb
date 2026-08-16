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

/**
 * M6b-DoD (Pfad B): derselbe Webhook zweimal ⇒ genau eine Zahlung + eine Rechnung;
 * ungültige Signatur ⇒ 4xx ohne Buchung; Betrags-/Währungs-Mismatch ⇒ Alarm-Audit,
 * keine Buchung; Checkout ohne Zustimmungen nicht absendbar; Client-Preis wirkungslos;
 * Expiry räumt PENDING_PAYMENT; kompletter Kauf im Testmodus ⇒ Rechnung + CONFIRMED.
 */
final class ShopCheckoutTest extends TestCase
{
    private string $productPublic;

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
        VariantRepo::create($productId, 'TEST-BLK-M', 'BLK', 'Schwarz', 'M');
        Db::run("UPDATE product_variants SET stock_qty = 100, reserve_qty = 0 WHERE sku = 'TEST-BLK-M'");
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

        foreach (['agb', 'widerruf', 'datenschutz'] as $t) {
            Db::run("INSERT INTO legal_document_versions (doc_type, language, version, content, content_hash, status, valid_from, created_at)
                     VALUES (?, 'de', 'v0', 'Platzhalter', ?, 'published', ?, ?)", [$t, str_repeat('c', 64), $now, $now]);
        }
        Db::run("INSERT INTO tax_regime_versions (regime_code, legend_text, valid_from, created_at) VALUES ('FRANCHISE_57BIS', 'Legende', ?, ?)", [$today, $now]);
        foreach (['seller.snapshot' => '{"placeholder":true,"name":"TPB"}', 'invoice.due_days' => '30', 'tax.active_regime' => '"FRANCHISE_57BIS"'] as $k => $v) {
            Db::run('INSERT INTO business_settings (setting_key, value_json, updated_at) VALUES (?, ?, ?)', [$k, $v, $now]);
        }
    }

    private function savedConfig(): string
    {
        $payload = ['express' => false, 'fileprep' => false, 'items' => [[
            'product' => $this->productPublic, 'type' => 'configured', 'technique_code' => 'FLEX',
            'sizes' => [['variant_sku' => 'TEST-BLK-M', 'qty' => 10]],
            'layers' => [['placement_code' => 'brust', 'layer_type' => 'text', 'text_content' => 'TPB', 'width_mm' => '90', 'height_mm' => '20']],
            'units' => [],
        ]]];
        $ids = PricingRepo::activeIds();
        $saved = ConfigurationRepo::save(ConfigMapper::resolve($payload), ['price_book_id' => $ids['price_book_id'], 'cost_version_id' => $ids['cost_version_id']]);
        return (string) $saved['public_id'];
    }

    /** @return array<string,mixed> */
    private function customerData(): array
    {
        return ['type' => 'private', 'first_name' => 'Max', 'last_name' => 'Muster', 'email' => 'max@example.com',
                'billing_street' => '1 Rue', 'billing_zip' => 'L-1', 'billing_city' => 'Lux', 'billing_country' => 'LU'];
    }

    /** @return array{0:string,1:string} [payload, signature] */
    private function signedEvent(array $intent, ?int $amount = null, ?string $currency = null, string $eventRef = null): array
    {
        $payload = json_encode([
            'event_ref'    => $eventRef ?? ('evt_' . (string) $intent['provider_ref']),
            'event_type'   => 'payment.succeeded',
            'provider_ref' => (string) $intent['provider_ref'],
            'amount_cents' => $amount ?? (int) $intent['amount_cents'],
            'currency'     => $currency ?? (string) $intent['currency'],
        ], JSON_UNESCAPED_SLASHES);
        return [$payload, TestGateway::sign($payload)];
    }

    public function testCheckoutCreatesPendingOrderWithServerPrice(): void
    {
        $res = CheckoutService::start($this->savedConfig(), $this->customerData(), ['agb', 'widerruf']);
        // 10*(1800+100) = 19000 – serverseitig, unabhängig von jeglicher Client-Angabe.
        self::assertSame(19000, $res['total_cents']);
        $order = OrderRepo::findByPublicId($res['order_public_id']);
        self::assertSame(19000, (int) $order['total_cents']);
        self::assertSame('PENDING_PAYMENT', OrderRepo::orderState((int) $order['id']));
        $intent = PaymentIntentRepo::findByPublicId($res['intent_public_id']);
        self::assertSame('created', $intent['status']);
    }

    public function testRequiredConsentsEnforced(): void
    {
        $this->expectException(CheckoutException::class);
        CheckoutService::start($this->savedConfig(), $this->customerData(), ['datenschutz']); // agb/widerruf fehlen
    }

    public function testPaidWebhookConfirmsOrderAndIssuesInvoice(): void
    {
        $res = CheckoutService::start($this->savedConfig(), $this->customerData(), ['agb', 'widerruf']);
        $intent = PaymentIntentRepo::findByPublicId($res['intent_public_id']);
        [$body, $sig] = $this->signedEvent($intent);

        $out = ShopWebhookService::handle($body, $sig);
        self::assertSame('ok', $out['status']);
        self::assertSame(200, $out['http']);

        $orderId = (int) OrderRepo::findByPublicId($res['order_public_id'])['id'];
        self::assertSame('CONFIRMED', OrderRepo::orderState($orderId));
        self::assertSame('PAID', Db::run('SELECT cur_payment FROM orders WHERE id = ?', [$orderId])->fetchColumn());
        self::assertSame(1, (int) Db::run("SELECT COUNT(*) FROM invoices WHERE order_id = ? AND status = 'ISSUED'", [$orderId])->fetchColumn());
        self::assertSame(1, (int) Db::run('SELECT COUNT(*) FROM payments WHERE order_id = ?', [$orderId])->fetchColumn());
        self::assertSame('succeeded', PaymentIntentRepo::findByOrderId($orderId)['status']);
    }

    public function testWebhookIsIdempotent(): void
    {
        $res = CheckoutService::start($this->savedConfig(), $this->customerData(), ['agb', 'widerruf']);
        $intent = PaymentIntentRepo::findByPublicId($res['intent_public_id']);
        [$body, $sig] = $this->signedEvent($intent);

        ShopWebhookService::handle($body, $sig);
        $second = ShopWebhookService::handle($body, $sig); // gleiches event_ref
        self::assertSame(200, $second['http']);

        $orderId = (int) OrderRepo::findByPublicId($res['order_public_id'])['id'];
        self::assertSame(1, (int) Db::run('SELECT COUNT(*) FROM payments WHERE order_id = ?', [$orderId])->fetchColumn());
        self::assertSame(1, (int) Db::run('SELECT COUNT(*) FROM invoices WHERE order_id = ?', [$orderId])->fetchColumn());
    }

    public function testInvalidSignatureHasNoEffect(): void
    {
        $res = CheckoutService::start($this->savedConfig(), $this->customerData(), ['agb', 'widerruf']);
        $intent = PaymentIntentRepo::findByPublicId($res['intent_public_id']);
        [$body] = $this->signedEvent($intent);

        $out = ShopWebhookService::handle($body, 'falsche-signatur');
        self::assertSame(400, $out['http']);

        $orderId = (int) OrderRepo::findByPublicId($res['order_public_id'])['id'];
        self::assertSame('PENDING_PAYMENT', OrderRepo::orderState($orderId));
        self::assertSame(0, (int) Db::run('SELECT COUNT(*) FROM payments WHERE order_id = ?', [$orderId])->fetchColumn());
        self::assertSame(0, (int) Db::run('SELECT COUNT(*) FROM invoices WHERE order_id = ?', [$orderId])->fetchColumn());
    }

    public function testAmountMismatchRaisesAlarmAndDoesNotBook(): void
    {
        $res = CheckoutService::start($this->savedConfig(), $this->customerData(), ['agb', 'widerruf']);
        $intent = PaymentIntentRepo::findByPublicId($res['intent_public_id']);
        [$body, $sig] = $this->signedEvent($intent, 999); // falscher Betrag

        $out = ShopWebhookService::handle($body, $sig);
        self::assertSame('mismatch', $out['status']);

        $orderId = (int) OrderRepo::findByPublicId($res['order_public_id'])['id'];
        self::assertSame('PENDING_PAYMENT', OrderRepo::orderState($orderId));
        self::assertSame(0, (int) Db::run('SELECT COUNT(*) FROM payments WHERE order_id = ?', [$orderId])->fetchColumn());
        self::assertSame(1, (int) Db::run("SELECT COUNT(*) FROM audit_events WHERE event_type = 'shop.amount_mismatch'")->fetchColumn());
    }

    public function testExpiryFindsStalePendingOrders(): void
    {
        $res = CheckoutService::start($this->savedConfig(), $this->customerData(), ['agb', 'widerruf']);
        $orderId = (int) OrderRepo::findByPublicId($res['order_public_id'])['id'];

        // Frisch → nicht abgelaufen.
        self::assertCount(0, OrderRepo::pendingPaymentOlderThan(Clock::nowUtc()->modify('-1 hour')->format('Y-m-d H:i:s')));

        // ordered_at in die Vergangenheit → wird als abgelaufen erkannt.
        Db::run("UPDATE orders SET ordered_at = '2000-01-01 00:00:00' WHERE id = ?", [$orderId]);
        $stale = OrderRepo::pendingPaymentOlderThan(Clock::nowUtc()->modify('-24 hours')->format('Y-m-d H:i:s'));
        self::assertCount(1, $stale);
        self::assertSame($orderId, (int) $stale[0]['id']);
    }
}
