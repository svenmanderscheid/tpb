<?php
declare(strict_types=1);

namespace Tpb\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Bank\BankImporter;
use Tpb\Domain\Bank\BankReconciliation;
use Tpb\Domain\Bank\BankRepo;
use Tpb\Domain\Bank\Camt053Parser;
use Tpb\Domain\Bank\Matcher;
use Tpb\Domain\Catalog\PlacementRepo;
use Tpb\Domain\Catalog\ProductRepo;
use Tpb\Domain\Catalog\TechniqueRepo;
use Tpb\Domain\Catalog\VariantRepo;
use Tpb\Domain\Config\ConfigMapper;
use Tpb\Domain\Config\ConfigurationRepo;
use Tpb\Domain\Customer\CustomerRepo;
use Tpb\Domain\Invoice\InvoiceService;
use Tpb\Domain\Pricing\CostVersionRepo;
use Tpb\Domain\Pricing\PriceBookRepo;
use Tpb\Domain\Pricing\PricingRepo;
use Tpb\Domain\Quote\QuoteService;

/**
 * M8-DoD (Bankabgleich): identische Datei ⇒ 0 neue Zeilen; Zeile mit Rechnungsreferenz
 * ⇒ korrekter Vorschlag; Bestätigung erzeugt Payment + Allocation + Achsen-Update in
 * einer Transaktion; keine Buchung ohne Bestätigung; CSV- und CAMT-Fixtures laufen.
 */
final class BankFlowTest extends TestCase
{
    private string $productPublic;

    private function cleanAll(): void
    {
        Db::run('UPDATE print_jobs SET reprint_of_id = NULL');
        Db::run('UPDATE invoices SET credited_invoice_id = NULL');
        foreach ([
            'bank_lines', 'bank_imports', 'reminders_sent',
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

    /** @return array{order_id:int,invoice_id:int,invoice_number:string} */
    private function issuedInvoice(): array
    {
        $payload = ['express' => false, 'fileprep' => false, 'items' => [[
            'product' => $this->productPublic, 'type' => 'configured', 'technique_code' => 'FLEX',
            'sizes' => [['variant_sku' => 'TEST-BLK-M', 'qty' => 10]],
            'layers' => [['placement_code' => 'brust', 'layer_type' => 'text', 'text_content' => 'X', 'width_mm' => '90', 'height_mm' => '20']],
            'units' => [],
        ]]];
        $ids = PricingRepo::activeIds();
        $saved = ConfigurationRepo::save(ConfigMapper::resolve($payload), ['price_book_id' => $ids['price_book_id'], 'cost_version_id' => $ids['cost_version_id']]);
        $cust = CustomerRepo::upsert(['type' => 'business', 'company_name' => 'FC Beispiel', 'first_name' => 'Max', 'last_name' => 'Muster', 'email' => 'max@example.com']);
        Db::run("UPDATE configurations SET customer_id = ?, status = 'submitted' WHERE id = ?", [$cust['id'], $saved['id']]);
        $quote = QuoteService::createFromConfiguration((int) $saved['id'], 1);
        $sent = QuoteService::send((int) $quote['id'], 1);
        $orderId = (int) QuoteService::accept($quote['public_id'], $sent['token'])['order_id'];
        $draft = InvoiceService::createDraft($orderId, 1);
        $res = InvoiceService::issue((int) $draft['id'], 1);
        return ['order_id' => $orderId, 'invoice_id' => (int) $draft['id'], 'invoice_number' => $res['invoice_number']];
    }

    private function csv(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/fixtures/bank/statement.csv');
    }

    public function testDuplicateFileImportsNoNewLines(): void
    {
        $first = BankImporter::import('Hausbank', 'csv', $this->csv(), 1);
        self::assertSame(2, $first['parsed']);
        self::assertSame(2, $first['new']);
        self::assertFalse($first['duplicate_file']);

        $second = BankImporter::import('Hausbank', 'csv', $this->csv(), 1);
        self::assertTrue($second['duplicate_file']);
        self::assertSame(0, $second['new']);
        self::assertSame(2, (int) Db::run('SELECT COUNT(*) FROM bank_lines')->fetchColumn());
    }

    public function testReferenceMatchSuggestsInvoice(): void
    {
        $inv = $this->issuedInvoice(); // 2026-000001, 19000
        BankImporter::import('Hausbank', 'csv', $this->csv(), 1);

        $incoming = Db::run('SELECT * FROM bank_lines WHERE amount_cents > 0 LIMIT 1')->fetch();
        $s = Matcher::suggest($incoming);
        self::assertNotNull($s);
        self::assertSame('high', $s['confidence']);
        self::assertSame($inv['invoice_number'], $s['invoice_number']);
    }

    public function testConfirmPaymentBooksInOneStep(): void
    {
        $inv = $this->issuedInvoice();
        BankImporter::import('Hausbank', 'csv', $this->csv(), 1);
        $incoming = Db::run('SELECT * FROM bank_lines WHERE amount_cents > 0 LIMIT 1')->fetch();

        BankReconciliation::confirmPayment((int) $incoming['id'], $inv['invoice_id'], 1);

        self::assertSame(1, (int) Db::run('SELECT COUNT(*) FROM payments WHERE order_id = ?', [$inv['order_id']])->fetchColumn());
        self::assertSame(19000, (int) Db::run('SELECT COALESCE(SUM(amount_cents),0) FROM payment_allocations WHERE invoice_id = ?', [$inv['invoice_id']])->fetchColumn());
        self::assertSame('PAID', Db::run('SELECT cur_payment FROM orders WHERE id = ?', [$inv['order_id']])->fetchColumn());
        self::assertSame('confirmed', Db::run('SELECT match_status FROM bank_lines WHERE id = ?', [(int) $incoming['id']])->fetchColumn());
    }

    public function testConfirmExpenseFromOutgoingLine(): void
    {
        BankImporter::import('Hausbank', 'csv', $this->csv(), 1);
        $outgoing = Db::run('SELECT * FROM bank_lines WHERE amount_cents < 0 LIMIT 1')->fetch();

        BankReconciliation::confirmExpense((int) $outgoing['id'], 'Material', 1);
        $exp = Db::run('SELECT amount_cents, bank_line_id FROM expenses ORDER BY id DESC LIMIT 1')->fetch();
        self::assertSame(4550, (int) $exp['amount_cents']);
        self::assertSame((int) $outgoing['id'], (int) $exp['bank_line_id']);
        self::assertSame('confirmed', Db::run('SELECT match_status FROM bank_lines WHERE id = ?', [(int) $outgoing['id']])->fetchColumn());
    }

    public function testCamt053ParsesEntries(): void
    {
        $xml = (string) file_get_contents(dirname(__DIR__) . '/fixtures/bank/statement.camt053.xml');
        $lines = Camt053Parser::parse($xml);
        self::assertCount(2, $lines);
        self::assertSame(19000, $lines[0]['amount_cents']);   // CRDT +
        self::assertSame(-4550, $lines[1]['amount_cents']);   // DBIT −
        self::assertSame('FC Beispiel', $lines[0]['counterparty_name']);
    }
}
