<?php
declare(strict_types=1);

namespace Tpb\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Env;
use Tpb\Domain\Catalog\PlacementRepo;
use Tpb\Domain\Catalog\ProductRepo;
use Tpb\Domain\Catalog\TechniqueRepo;
use Tpb\Domain\Catalog\VariantRepo;
use Tpb\Domain\Config\ConfigMapper;
use Tpb\Domain\Config\ConfigurationRepo;
use Tpb\Domain\Customer\CustomerRepo;
use Tpb\Domain\Invoice\InvoiceRepo;
use Tpb\Domain\Invoice\InvoiceService;
use Tpb\Domain\Invoice\InvoiceStateException;
use Tpb\Domain\Payment\PaymentRepo;
use Tpb\Domain\Payment\PaymentService;
use Tpb\Domain\Pricing\CostVersionRepo;
use Tpb\Domain\Pricing\PriceBookRepo;
use Tpb\Domain\Pricing\PricingRepo;
use Tpb\Domain\Quote\QuoteService;

/**
 * M6-DoD: Issue-Flow. Keine Doppelnummer (auch mit zweiter PDO-Verbindung), ISSUED
 * unumkehrbar, PDF/JSON-Summen identisch (Snapshot = Quelle), Umsatz ≠ Zahlungseingang
 * getrennt, Gutschrift referenziert korrekt.
 */
final class InvoiceFlowTest extends TestCase
{
    private string $productPublic;

    private function cleanAll(): void
    {
        Db::run('UPDATE print_jobs SET reprint_of_id = NULL');
        Db::run('UPDATE invoices SET credited_invoice_id = NULL');
        foreach ([
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

        Db::run("INSERT INTO tax_regime_versions (regime_code, legend_text, valid_from, created_at) VALUES ('FRANCHISE_57BIS', 'Platzhalter Legende', ?, ?)", [$today, $now]);
        foreach (['seller.snapshot' => '{"placeholder":true,"name":"TPB"}', 'invoice.due_days' => '30', 'tax.active_regime' => '"FRANCHISE_57BIS"'] as $k => $v) {
            Db::run('INSERT INTO business_settings (setting_key, value_json, updated_at) VALUES (?, ?, ?)', [$k, $v, $now]);
        }
    }

    private function acceptedOrderId(): int
    {
        $payload = ['express' => false, 'fileprep' => false, 'items' => [[
            'product' => $this->productPublic, 'type' => 'configured', 'technique_code' => 'FLEX',
            'sizes' => [['variant_sku' => 'TEST-BLK-M', 'qty' => 10]],
            'layers' => [['placement_code' => 'brust', 'layer_type' => 'text', 'text_content' => 'TPB', 'width_mm' => '90', 'height_mm' => '20']],
            'units' => [],
        ]]];
        $ids = PricingRepo::activeIds();
        $saved = ConfigurationRepo::save(ConfigMapper::resolve($payload), ['price_book_id' => $ids['price_book_id'], 'cost_version_id' => $ids['cost_version_id']]);
        $cust = CustomerRepo::upsert(['type' => 'business', 'company_name' => 'FC Test', 'first_name' => 'Max', 'last_name' => 'Muster', 'email' => 'max@example.com']);
        Db::run("UPDATE configurations SET customer_id = ?, status = 'submitted' WHERE id = ?", [$cust['id'], $saved['id']]);
        $quote = QuoteService::createFromConfiguration((int) $saved['id'], 1);
        $sent = QuoteService::send((int) $quote['id'], 1);
        return (int) QuoteService::accept($quote['public_id'], $sent['token'])['order_id'];
    }

    public function testIssueFreezesSnapshotAndSumsMatch(): void
    {
        $orderId = $this->acceptedOrderId();
        $draft = InvoiceService::createDraft($orderId, 1);
        $res = InvoiceService::issue($draft['id'], 1);
        self::assertMatchesRegularExpression('/^\d{4}-\d{6}$/', $res['invoice_number']);

        $inv = InvoiceRepo::findById($draft['id']);
        self::assertSame('ISSUED', $inv['status']);
        // 10 * (1800+100) = 19000 netto, 0 USt, 19000 brutto.
        self::assertSame(19000, (int) $inv['net_cents']);
        self::assertSame(0, (int) $inv['tax_cents']);
        self::assertSame(19000, (int) $inv['gross_cents']);

        // Snapshot (Quelle für PDF UND JSON) stimmt mit den Rechnungsspalten überein.
        $snap = json_decode((string) $inv['snapshot_json'], true);
        self::assertSame(19000, (int) $snap['totals']['net_cents']);
        self::assertSame(19000, (int) $snap['totals']['gross_cents']);
        self::assertSame((int) $snap['totals']['net_cents'] + (int) $snap['totals']['tax_cents'], (int) $snap['totals']['gross_cents']);
        self::assertNotNull($inv['pdf_asset_id']);
        self::assertNotNull($inv['json_asset_id']);
        self::assertSame($inv['snapshot_sha256'], hash('sha256', (string) $inv['snapshot_json']));
    }

    public function testIssuedIsImmutable(): void
    {
        $orderId = $this->acceptedOrderId();
        $draft = InvoiceService::createDraft($orderId, 1);
        InvoiceService::issue($draft['id'], 1);
        $this->expectException(InvoiceStateException::class);
        InvoiceService::issue($draft['id'], 1);
    }

    public function testSequentialUniqueNumbers(): void
    {
        $a = InvoiceService::issue(InvoiceService::createDraft($this->acceptedOrderId(), 1)['id'], 1)['invoice_number'];
        $b = InvoiceService::issue(InvoiceService::createDraft($this->acceptedOrderId(), 1)['id'], 1)['invoice_number'];
        self::assertNotSame($a, $b);
        [, $na] = explode('-', $a);
        [, $nb] = explode('-', $b);
        self::assertSame((int) $na + 1, (int) $nb);
    }

    public function testSecondPdoConnectionGetsDistinctNumber(): void
    {
        // Erste Nummer über eine ZWEITE PDO-Verbindung ziehen (Referenz-SQL, eigene Transaktion).
        $pdo2 = new \PDO(
            'mysql:host=' . (Env::get('DB_HOST', '127.0.0.1') ?? '127.0.0.1') . ';dbname=tpb_test;charset=utf8mb4',
            Env::get('DB_USER', 'root') ?? 'root',
            Env::get('DB_PASS', '') ?? '',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $year = (int) Clock::nowUtc()->format('Y');
        $pdo2->beginTransaction();
        $pdo2->prepare('INSERT IGNORE INTO number_sequences (seq_type, fiscal_year, next_value, updated_at) VALUES (?, ?, 1, UTC_TIMESTAMP())')->execute(['invoice', $year]);
        $st = $pdo2->prepare('SELECT next_value FROM number_sequences WHERE seq_type = ? AND fiscal_year = ? FOR UPDATE');
        $st->execute(['invoice', $year]);
        $n1 = (int) $st->fetchColumn();
        $pdo2->prepare('UPDATE number_sequences SET next_value = next_value + 1 WHERE seq_type = ? AND fiscal_year = ?')->execute(['invoice', $year]);
        $pdo2->commit();

        // Danach über die App ausstellen – muss eine ANDERE (nächste) Nummer erhalten.
        $num = InvoiceService::issue(InvoiceService::createDraft($this->acceptedOrderId(), 1)['id'], 1)['invoice_number'];
        [, $n2] = explode('-', $num);
        self::assertGreaterThan($n1, (int) $n2, 'Zweite Verbindung darf keine doppelte Nummer erzeugen.');
    }

    public function testRevenueSeparateFromPayment(): void
    {
        $orderId = $this->acceptedOrderId();
        InvoiceService::issue(InvoiceService::createDraft($orderId, 1)['id'], 1);

        self::assertSame(19000, PaymentRepo::netInvoiced($orderId)); // Umsatz
        self::assertSame(0, PaymentRepo::receivedForOrder($orderId)); // Zahlungseingang
        self::assertSame('UNPAID', Db::run('SELECT cur_payment FROM orders WHERE id = ?', [$orderId])->fetchColumn());

        PaymentService::record($orderId, 'bank_transfer', 5000, Clock::nowUtc()->format('Y-m-d'), 'A1', 1);
        self::assertSame(5000, PaymentRepo::receivedForOrder($orderId));
        self::assertSame('PARTIALLY_PAID', Db::run('SELECT cur_payment FROM orders WHERE id = ?', [$orderId])->fetchColumn());

        PaymentService::record($orderId, 'bank_transfer', 14000, Clock::nowUtc()->format('Y-m-d'), 'A2', 1);
        self::assertSame(19000, PaymentRepo::receivedForOrder($orderId));
        self::assertSame('PAID', Db::run('SELECT cur_payment FROM orders WHERE id = ?', [$orderId])->fetchColumn());
    }

    public function testCreditNoteReferencesOriginal(): void
    {
        $orderId = $this->acceptedOrderId();
        $draft = InvoiceService::createDraft($orderId, 1);
        InvoiceService::issue($draft['id'], 1);

        $res = InvoiceService::creditNote($draft['id'], 1);
        $credit = InvoiceRepo::findByPublicId($res['credit_public_id']);
        self::assertSame('credit_note', $credit['doc_type']);
        self::assertSame($draft['id'], (int) $credit['credited_invoice_id']);
        self::assertSame(-19000, (int) $credit['gross_cents']);
        self::assertSame('ISSUED', $credit['status']);
        self::assertSame('FULLY_CREDITED', InvoiceRepo::findById($draft['id'])['status']);
    }
}
