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
use Tpb\Domain\Dunning\DunningService;
use Tpb\Domain\Invoice\InvoiceService;
use Tpb\Domain\Pricing\CostVersionRepo;
use Tpb\Domain\Pricing\PriceBookRepo;
use Tpb\Domain\Pricing\PricingRepo;
use Tpb\Domain\Quote\QuoteService;

/**
 * M8-DoD (Mahnwesen): überfällige Rechnung ⇒ Erinnerung; mehrfacher Lauf versendet
 * keine Stufe doppelt (reminders_sent UNIQUE). Wiedervorlage für ablaufende Angebote.
 */
final class DunningTest extends TestCase
{
    private string $productPublic;

    private function cleanAll(): void
    {
        Db::run('UPDATE invoices SET credited_invoice_id = NULL');
        foreach ([
            'reminders_sent', 'payment_allocations', 'payments', 'invoice_lines', 'invoices',
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
        Db::run("DELETE FROM users WHERE email = 'ownerdun@example.com'");
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
        Db::run("INSERT INTO users (email, pass_hash, display_name, role, status, created_at, updated_at) VALUES ('ownerdun@example.com','x','Owner','owner','active',?,?)", [$now, $now]);
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
        Db::run("INSERT INTO tax_regime_versions (regime_code, legend_text, valid_from, created_at) VALUES ('FRANCHISE_57BIS', 'L', ?, ?)", [$today, $now]);
        foreach (['seller.snapshot' => '{"name":"TPB"}', 'invoice.due_days' => '30', 'tax.active_regime' => '"FRANCHISE_57BIS"'] as $k => $v) {
            Db::run('INSERT INTO business_settings (setting_key, value_json, updated_at) VALUES (?, ?, ?)', [$k, $v, $now]);
        }
    }

    private function submittedConfig(): int
    {
        $payload = ['express' => false, 'fileprep' => false, 'items' => [[
            'product' => $this->productPublic, 'type' => 'configured', 'technique_code' => 'FLEX',
            'sizes' => [['variant_sku' => 'TEST-BLK-M', 'qty' => 10]],
            'layers' => [['placement_code' => 'brust', 'layer_type' => 'text', 'text_content' => 'X', 'width_mm' => '90', 'height_mm' => '20']],
            'units' => [],
        ]]];
        $ids = PricingRepo::activeIds();
        $saved = ConfigurationRepo::save(ConfigMapper::resolve($payload), ['price_book_id' => $ids['price_book_id'], 'cost_version_id' => $ids['cost_version_id']]);
        $cust = CustomerRepo::upsert(['type' => 'business', 'company_name' => 'FC', 'first_name' => 'M', 'last_name' => 'M', 'email' => 'kunde@example.com']);
        Db::run("UPDATE configurations SET customer_id = ?, status = 'submitted' WHERE id = ?", [$cust['id'], $saved['id']]);
        return (int) $saved['id'];
    }

    public function testOverdueInvoiceReminderSentOnce(): void
    {
        $quote = QuoteService::createFromConfiguration($this->submittedConfig(), 1);
        $sent = QuoteService::send((int) $quote['id'], 1);
        $orderId = (int) QuoteService::accept($quote['public_id'], $sent['token'])['order_id'];
        $draft = InvoiceService::createDraft($orderId, 1);
        InvoiceService::issue((int) $draft['id'], 1);
        Db::run("UPDATE invoices SET due_date = '2000-01-01' WHERE id = ?", [(int) $draft['id']]); // überfällig

        $r1 = DunningService::run();
        self::assertSame(1, $r1['payment_reminders']);
        self::assertSame(1, (int) Db::run("SELECT COUNT(*) FROM outbox_events WHERE event_type = 'mail.payment_reminder'")->fetchColumn());

        $r2 = DunningService::run(); // zweiter Lauf: keine Dublette
        self::assertSame(0, $r2['payment_reminders']);
        self::assertSame(1, (int) Db::run("SELECT COUNT(*) FROM reminders_sent WHERE reminder_type = 'payment'")->fetchColumn());
    }

    public function testExpiringQuoteFollowupSentOnce(): void
    {
        $quote = QuoteService::createFromConfiguration($this->submittedConfig(), 1);
        QuoteService::send((int) $quote['id'], 1);
        Db::run("UPDATE quotes SET valid_until = ? WHERE id = ?", [Clock::nowUtc()->modify('+1 day')->format('Y-m-d'), (int) $quote['id']]);

        self::assertSame(1, DunningService::run()['quote_followups']);
        self::assertSame(0, DunningService::run()['quote_followups']); // keine Dublette
    }
}
