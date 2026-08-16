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
use Tpb\Domain\Quote\QuoteAccessException;
use Tpb\Domain\Quote\QuoteService;
use Tpb\Domain\Quote\QuoteStateException;

/**
 * M3-DoD: Angebot erstellen → versenden → annehmen. Prüft idempotente Annahme
 * (genau eine Order), Snapshot-Unveränderlichkeit gegen Preisbuchänderung sowie
 * Abweisung bei ungültigem Token und abgelaufenem Angebot (§7, §12 M3).
 */
final class QuoteFlowTest extends TestCase
{
    private string $productPublic;

    private function cleanAll(): void
    {
        foreach ([
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

        // Platzhalter-Rechtstext (für order_terms_acceptance).
        $now = Clock::nowUtcSeconds();
        Db::run(
            "INSERT INTO legal_document_versions (doc_type, language, version, content, content_hash, status, valid_from, created_at)
             VALUES ('agb', 'de', 'v0-PLATZHALTER', 'Platzhalter', ?, 'published', ?, ?)",
            [str_repeat('b', 64), $now, $now]
        );
    }

    /** @return array{config_id:int,customer_id:int} */
    private function submittedConfiguration(): array
    {
        $payload = [
            'express' => false, 'fileprep' => false,
            'items' => [[
                'product' => $this->productPublic, 'type' => 'configured', 'technique_code' => 'FLEX',
                'sizes' => [['variant_sku' => 'TEST-BLK-M', 'qty' => 10]],
                'layers' => [['placement_code' => 'brust', 'layer_type' => 'text', 'text_content' => 'TPB', 'width_mm' => '90', 'height_mm' => '20']],
                'units' => [['variant_sku' => 'TEST-BLK-M', 'name' => 'Max']],
            ]],
        ];
        $ids = PricingRepo::activeIds();
        $saved = ConfigurationRepo::save(ConfigMapper::resolve($payload), [
            'price_book_id' => $ids['price_book_id'], 'cost_version_id' => $ids['cost_version_id'],
        ]);
        $customer = CustomerRepo::upsert([
            'type' => 'business', 'company_name' => 'FC Beispiel', 'first_name' => 'Max', 'last_name' => 'Muster',
            'email' => 'max@example.com', 'phone' => '+352 000',
        ]);
        Db::run("UPDATE configurations SET customer_id = ?, status = 'submitted' WHERE id = ?", [$customer['id'], $saved['id']]);
        return ['config_id' => (int) $saved['id'], 'customer_id' => (int) $customer['id']];
    }

    public function testCreateSendAcceptCreatesExactlyOneOrderIdempotently(): void
    {
        $cfg = $this->submittedConfiguration();
        $quote = QuoteService::createFromConfiguration($cfg['config_id'], 1);

        $row = Db::run('SELECT * FROM quotes WHERE id = ?', [$quote['id']])->fetch();
        self::assertSame('DRAFT', $row['status']);
        self::assertNull($row['quote_number']);
        // 10*(1800+100)+250 (1 personalisiert), Textlayer => kein Motiv => kein Setup = 19250
        self::assertSame(19250, (int) $row['total_cents']);

        $sent = QuoteService::send($quote['id'], 1);
        self::assertStringStartsWith('Q-', $sent['quote_number']);
        self::assertNotSame('', $sent['token']);

        $row = Db::run('SELECT * FROM quotes WHERE id = ?', [$quote['id']])->fetch();
        self::assertSame('SENT', $row['status']);
        self::assertNotNull($row['pdf_asset_id']);

        // Annahme + Doppelklick => genau eine Order.
        $a1 = QuoteService::accept($quote['public_id'], $sent['token']);
        self::assertFalse($a1['already']);
        self::assertStringStartsWith('ORD-', $a1['order_number']);

        $a2 = QuoteService::accept($quote['public_id'], $sent['token']);
        self::assertTrue($a2['already']);
        self::assertSame($a1['order_public_id'], $a2['order_public_id']);

        $count = (int) Db::run('SELECT COUNT(*) FROM orders WHERE quote_id = ?', [$quote['id']])->fetchColumn();
        self::assertSame(1, $count);

        // Order-Inhalt aus dem Snapshot.
        $orderId = $a1['order_id'];
        self::assertSame(19250, (int) Db::run('SELECT total_cents FROM orders WHERE id = ?', [$orderId])->fetchColumn());
        self::assertSame(1, (int) Db::run('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$orderId])->fetchColumn());
        self::assertSame(1, (int) Db::run('SELECT COUNT(*) FROM order_item_units WHERE order_item_id IN (SELECT id FROM order_items WHERE order_id = ?)', [$orderId])->fetchColumn());
        self::assertSame('ACCEPTED', Db::run('SELECT status FROM quotes WHERE id = ?', [$quote['id']])->fetchColumn());
        self::assertSame(1, (int) Db::run('SELECT COUNT(*) FROM order_terms_acceptance WHERE order_id = ?', [$orderId])->fetchColumn());
    }

    public function testSnapshotImmutableAgainstPriceBookChange(): void
    {
        $cfg = $this->submittedConfiguration();
        $quote = QuoteService::createFromConfiguration($cfg['config_id'], 1);
        QuoteService::send($quote['id'], 1);

        $before = (int) Db::run('SELECT total_cents FROM quotes WHERE id = ?', [$quote['id']])->fetchColumn();

        // Preisbuch nachträglich verändern (simuliert – im Betrieb wäre das eine neue Version).
        Db::run('UPDATE price_tiers SET unit_cents = 9999');

        $after = (int) Db::run('SELECT total_cents FROM quotes WHERE id = ?', [$quote['id']])->fetchColumn();
        self::assertSame($before, $after, 'Der Angebots-Snapshot darf sich durch Preisbuchänderungen nicht ändern.');

        $snap = json_decode((string) Db::run('SELECT snapshot_json FROM quotes WHERE id = ?', [$quote['id']])->fetchColumn(), true);
        self::assertSame(19250, (int) $snap['totals']['total_cents']);
    }

    public function testAcceptWithInvalidTokenRejected(): void
    {
        $cfg = $this->submittedConfiguration();
        $quote = QuoteService::createFromConfiguration($cfg['config_id'], 1);
        QuoteService::send($quote['id'], 1);

        $this->expectException(QuoteAccessException::class);
        QuoteService::accept($quote['public_id'], 'falsches-token');
    }

    public function testAcceptWithExpiredTokenRejected(): void
    {
        $cfg = $this->submittedConfiguration();
        $quote = QuoteService::createFromConfiguration($cfg['config_id'], 1);
        $sent = QuoteService::send($quote['id'], 1);

        // Token in die Vergangenheit setzen.
        Db::run("UPDATE access_tokens SET expires_at = '2000-01-01 00:00:00' WHERE ref_type = 'quote' AND ref_id = ?", [$quote['id']]);

        $this->expectException(QuoteAccessException::class);
        QuoteService::accept($quote['public_id'], $sent['token']);
    }

    public function testAcceptExpiredQuoteRejectedAndMarkedExpired(): void
    {
        $cfg = $this->submittedConfiguration();
        $quote = QuoteService::createFromConfiguration($cfg['config_id'], 1);
        $sent = QuoteService::send($quote['id'], 1);

        Db::run("UPDATE quotes SET valid_until = '2000-01-01' WHERE id = ?", [$quote['id']]);

        try {
            QuoteService::accept($quote['public_id'], $sent['token']);
            self::fail('Erwartete QuoteStateException für abgelaufenes Angebot.');
        } catch (QuoteStateException) {
            // erwartet
        }
        self::assertSame('EXPIRED', Db::run('SELECT status FROM quotes WHERE id = ?', [$quote['id']])->fetchColumn());
        self::assertSame(0, (int) Db::run('SELECT COUNT(*) FROM orders WHERE quote_id = ?', [$quote['id']])->fetchColumn());
    }
}
