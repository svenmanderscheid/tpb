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
use Tpb\Domain\Proof\ProofAccessException;
use Tpb\Domain\Proof\ProofRepo;
use Tpb\Domain\Proof\ProofService;
use Tpb\Domain\Proof\ProofStateException;
use Tpb\Domain\Quote\QuoteService;

/**
 * M4-DoD: Proof-Zyklus. Freigabe bindet an genau eine Proof-Version; eine neue
 * Version löst die alte ab (superseded) und macht den alten Link ungültig; die
 * Freigabe wird mit Zeit/Actor/Token protokolliert und sperrt das Artwork (LOCKED).
 */
final class ProofFlowTest extends TestCase
{
    private string $productPublic;

    private function cleanAll(): void
    {
        foreach ([
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

    /** Führt den kompletten Weg bis zu einer angenommenen Order und gibt deren public_id/id. */
    private function acceptedOrder(): array
    {
        $payload = [
            'express' => false, 'fileprep' => false,
            'items' => [[
                'product' => $this->productPublic, 'type' => 'configured', 'technique_code' => 'FLEX',
                'sizes' => [['variant_sku' => 'TEST-BLK-M', 'qty' => 10]],
                'layers' => [['placement_code' => 'brust', 'layer_type' => 'text', 'text_content' => 'TPB', 'width_mm' => '90', 'height_mm' => '20', 'offset_x_mm' => '0', 'offset_y_mm' => '0']],
                'units' => [],
            ]],
        ];
        $ids = PricingRepo::activeIds();
        $saved = ConfigurationRepo::save(ConfigMapper::resolve($payload), ['price_book_id' => $ids['price_book_id'], 'cost_version_id' => $ids['cost_version_id']]);
        $cust = CustomerRepo::upsert(['type' => 'business', 'company_name' => 'FC Test', 'first_name' => 'Max', 'last_name' => 'Muster', 'email' => 'max@example.com']);
        Db::run("UPDATE configurations SET customer_id = ?, status = 'submitted' WHERE id = ?", [$cust['id'], $saved['id']]);

        $quote = QuoteService::createFromConfiguration((int) $saved['id'], 1);
        $sent = QuoteService::send((int) $quote['id'], 1);
        $acc = QuoteService::accept($quote['public_id'], $sent['token']);
        $order = Db::run('SELECT id, public_id, cur_artwork FROM orders WHERE id = ?', [$acc['order_id']])->fetch();
        return $order;
    }

    /** Erzeugt eine gültige 1x1-PNG-Uploadstruktur (frische Temp-Datei je Aufruf). */
    private function pngUpload(): array
    {
        $img = imagecreatetruecolor(1, 1);
        $tmp = tempnam(sys_get_temp_dir(), 'tpbart');
        imagepng($img, $tmp);
        imagedestroy($img);
        return ['name' => 'art.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)];
    }

    public function testApproveLocksArtworkAndRecordsApproval(): void
    {
        $order = $this->acceptedOrder();
        self::assertSame('MISSING', $order['cur_artwork']);
        $orderId = (int) $order['id'];

        ProofService::addArtwork($orderId, $this->pngUpload(), 1);
        self::assertSame('UPLOADED', Db::run('SELECT cur_artwork FROM orders WHERE id = ?', [$orderId])->fetchColumn());

        $proof = ProofService::createAndSend($orderId, 1);
        self::assertSame(1, $proof['version_no']);
        self::assertSame('PROOF_SENT', Db::run('SELECT cur_artwork FROM orders WHERE id = ?', [$orderId])->fetchColumn());

        ProofService::approve((string) $order['public_id'], $proof['token']);

        self::assertSame('LOCKED', Db::run('SELECT cur_artwork FROM orders WHERE id = ?', [$orderId])->fetchColumn());
        $active = ProofRepo::activeForOrder($orderId);
        self::assertSame('approved', $active['status']);
        // Freigabe referenziert genau diese Proof-Version, mit Actor + Token.
        $appr = Db::run('SELECT proof_id, decision, actor_label, access_token_id, decided_at FROM proof_approvals WHERE proof_id = ?', [$proof['proof_id']])->fetch();
        self::assertSame($proof['proof_id'], (int) $appr['proof_id']);
        self::assertSame('approved', $appr['decision']);
        self::assertSame('customer', $appr['actor_label']);
        self::assertNotNull($appr['access_token_id']);
        self::assertNotNull($appr['decided_at']);
    }

    public function testNewVersionSupersedesAndOldLinkRejected(): void
    {
        $order = $this->acceptedOrder();
        $orderId = (int) $order['id'];

        ProofService::addArtwork($orderId, $this->pngUpload(), 1);
        $v1 = ProofService::createAndSend($orderId, 1);

        // Kunde fordert Änderung an (mit v1-Token).
        ProofService::requestChanges((string) $order['public_id'], $v1['token'], 'Logo größer');
        self::assertSame('CHANGES_REQUESTED', Db::run('SELECT cur_artwork FROM orders WHERE id = ?', [$orderId])->fetchColumn());

        // Neue Artwork-Version + neuer Proof v2.
        ProofService::addArtwork($orderId, $this->pngUpload(), 1);
        $v2 = ProofService::createAndSend($orderId, 1);
        self::assertSame(2, $v2['version_no']);

        // v1-Proof ist abgelöst.
        self::assertSame('superseded', Db::run('SELECT status FROM proofs WHERE id = ?', [$v1['proof_id']])->fetchColumn());

        // Alter Link (v1-Token) wird abgewiesen – erfordert neue Freigabe.
        try {
            ProofService::approve((string) $order['public_id'], $v1['token']);
            self::fail('Erwartete ProofAccessException für den abgelösten v1-Link.');
        } catch (ProofAccessException) {
            // erwartet
        }
        self::assertSame('PROOF_SENT', Db::run('SELECT cur_artwork FROM orders WHERE id = ?', [$orderId])->fetchColumn());

        // Neuer Link (v2) gibt frei → LOCKED.
        ProofService::approve((string) $order['public_id'], $v2['token']);
        self::assertSame('LOCKED', Db::run('SELECT cur_artwork FROM orders WHERE id = ?', [$orderId])->fetchColumn());
        self::assertSame('approved', Db::run('SELECT status FROM proofs WHERE id = ?', [$v2['proof_id']])->fetchColumn());
    }

    public function testCannotProofLockedOrder(): void
    {
        $order = $this->acceptedOrder();
        $orderId = (int) $order['id'];
        ProofService::addArtwork($orderId, $this->pngUpload(), 1);
        $v1 = ProofService::createAndSend($orderId, 1);
        ProofService::approve((string) $order['public_id'], $v1['token']);

        $this->expectException(ProofStateException::class);
        ProofService::createAndSend($orderId, 1);
    }

    public function testInvalidTokenRejected(): void
    {
        $order = $this->acceptedOrder();
        $orderId = (int) $order['id'];
        ProofService::addArtwork($orderId, $this->pngUpload(), 1);
        ProofService::createAndSend($orderId, 1);

        $this->expectException(ProofAccessException::class);
        ProofService::approve((string) $order['public_id'], 'falsches-token');
    }
}
