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
use Tpb\Domain\Proof\ProofService;
use Tpb\Domain\Quote\QuoteService;

/**
 * M9-DoD (Nachträge): Nachtragsannahme erzeugt KEINE neue Order (Idempotenz), hängt
 * Positionen additiv an, lässt bestehende Snapshots binär unverändert (Hash-Vergleich),
 * und setzt bei konfiguriertem Nachtrag das gesperrte Artwork zurück (neuer Proof).
 */
final class AmendFlowTest extends TestCase
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
        $now = Clock::nowUtcSeconds();
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
        Db::run("INSERT INTO legal_document_versions (doc_type, language, version, content, content_hash, status, valid_from, created_at) VALUES ('agb','de','v0','x',?,'published',?,?)", [str_repeat('a', 64), $now, $now]);
    }

    private function savedConfig(int $qty): int
    {
        $payload = ['express' => false, 'fileprep' => false, 'items' => [[
            'product' => $this->productPublic, 'type' => 'configured', 'technique_code' => 'FLEX',
            'sizes' => [['variant_sku' => 'TEST-BLK-M', 'qty' => $qty]],
            'layers' => [['placement_code' => 'brust', 'layer_type' => 'text', 'text_content' => 'X', 'width_mm' => '90', 'height_mm' => '20']],
            'units' => [],
        ]]];
        $ids = PricingRepo::activeIds();
        return (int) ConfigurationRepo::save(ConfigMapper::resolve($payload), ['price_book_id' => $ids['price_book_id'], 'cost_version_id' => $ids['cost_version_id']])['id'];
    }

    private function pngUpload(): array
    {
        $img = imagecreatetruecolor(1, 1);
        $tmp = tempnam(sys_get_temp_dir(), 'art');
        imagepng($img, $tmp);
        imagedestroy($img);
        return ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)];
    }

    /** @return array{order_id:int,order_public:string,quote_hash:string} */
    private function acceptedLockedOrder(): array
    {
        $cfgId = $this->savedConfig(10);
        $cust = CustomerRepo::upsert(['type' => 'business', 'company_name' => 'FC', 'first_name' => 'M', 'last_name' => 'M', 'email' => 'm@example.com']);
        Db::run("UPDATE configurations SET customer_id = ?, status = 'submitted' WHERE id = ?", [$cust['id'], $cfgId]);
        $quote = QuoteService::createFromConfiguration($cfgId, 1);
        $hash = (string) Db::run('SELECT snapshot_sha256 FROM quotes WHERE id = ?', [(int) $quote['id']])->fetchColumn();
        $sent = QuoteService::send((int) $quote['id'], 1);
        $orderId = (int) QuoteService::accept($quote['public_id'], $sent['token'])['order_id'];
        $order = Db::run('SELECT public_id FROM orders WHERE id = ?', [$orderId])->fetch();
        // Artwork sperren.
        ProofService::addArtwork($orderId, $this->pngUpload(), 1);
        $p = ProofService::createAndSend($orderId, 1);
        ProofService::approve((string) $order['public_id'], $p['token']);
        return ['order_id' => $orderId, 'order_public' => (string) $order['public_id'], 'quote_hash' => $hash];
    }

    public function testAmendAppendsWithoutNewOrderAndKeepsSnapshot(): void
    {
        $o = $this->acceptedLockedOrder();
        self::assertSame('LOCKED', Db::run('SELECT cur_artwork FROM orders WHERE id = ?', [$o['order_id']])->fetchColumn());
        self::assertSame(19000, (int) Db::run('SELECT total_cents FROM orders WHERE id = ?', [$o['order_id']])->fetchColumn());

        // Nachtrag: +5 Stück.
        $amendCfg = $this->savedConfig(5);
        $amendQuote = QuoteService::createFromConfiguration($amendCfg, 1, $o['order_id']);
        $sent = QuoteService::send((int) $amendQuote['id'], 1);
        $res = QuoteService::accept($amendQuote['public_id'], $sent['token']);

        // Keine neue Order.
        self::assertSame(1, (int) Db::run('SELECT COUNT(*) FROM orders')->fetchColumn());
        self::assertSame($o['order_id'], $res['order_id']);
        // Positionen angehängt, Summe additiv (19000 + 5*1900 = 28500).
        self::assertSame(2, (int) Db::run('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$o['order_id']])->fetchColumn());
        self::assertSame(28500, (int) Db::run('SELECT total_cents FROM orders WHERE id = ?', [$o['order_id']])->fetchColumn());
        // Ursprungs-Angebot-Snapshot binär unverändert.
        $hashAfter = (string) Db::run("SELECT snapshot_sha256 FROM quotes WHERE amends_order_id IS NULL")->fetchColumn();
        self::assertSame($o['quote_hash'], $hashAfter);
        // Konfigurierter Nachtrag ⇒ Artwork zurück auf MISSING (neuer Proof-Zyklus).
        self::assertSame('MISSING', Db::run('SELECT cur_artwork FROM orders WHERE id = ?', [$o['order_id']])->fetchColumn());
    }

    public function testAmendAcceptIsIdempotent(): void
    {
        $o = $this->acceptedLockedOrder();
        $amendQuote = QuoteService::createFromConfiguration($this->savedConfig(5), 1, $o['order_id']);
        $sent = QuoteService::send((int) $amendQuote['id'], 1);

        QuoteService::accept($amendQuote['public_id'], $sent['token']);
        $r2 = QuoteService::accept($amendQuote['public_id'], $sent['token']); // zweite Annahme

        self::assertTrue($r2['already']);
        self::assertSame(2, (int) Db::run('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$o['order_id']])->fetchColumn());
        self::assertSame(28500, (int) Db::run('SELECT total_cents FROM orders WHERE id = ?', [$o['order_id']])->fetchColumn());
    }
}
