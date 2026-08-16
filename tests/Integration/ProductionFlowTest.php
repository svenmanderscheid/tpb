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
use Tpb\Domain\Label\LabelService;
use Tpb\Domain\Pricing\CostVersionRepo;
use Tpb\Domain\Pricing\PriceBookRepo;
use Tpb\Domain\Pricing\PricingRepo;
use Tpb\Domain\Production\JobRepo;
use Tpb\Domain\Production\ProductionGateException;
use Tpb\Domain\Production\ProductionService;
use Tpb\Domain\Proof\ProofService;
use Tpb\Domain\Quote\QuoteService;

/**
 * M5-DoD: Job ohne Gate bleibt BLOCKED; doppelter Event-POST (gleicher idem_key)
 * bucht nicht doppelt; Etikett rendert (203/300 dpi PNG + PDF/print_jobs); Neudruck
 * nur mit Grund.
 */
final class ProductionFlowTest extends TestCase
{
    private string $productPublic;

    private function cleanAll(): void
    {
        // Self-FK reprint_of_id auflösen, damit DELETE FROM print_jobs nicht blockiert.
        Db::run('UPDATE print_jobs SET reprint_of_id = NULL');
        foreach ([
            'print_jobs', 'production_events', 'production_jobs',
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

    /** @return array<string,mixed> Order row (nach Annahme). */
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
        return Db::run('SELECT * FROM orders WHERE id = ?', [$acc['order_id']])->fetch();
    }

    private function pngUpload(): array
    {
        $img = imagecreatetruecolor(1, 1);
        $tmp = tempnam(sys_get_temp_dir(), 'art');
        imagepng($img, $tmp);
        imagedestroy($img);
        return ['name' => 'art.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)];
    }

    private function lockArtwork(array $order): void
    {
        ProofService::addArtwork((int) $order['id'], $this->pngUpload(), 1);
        $p = ProofService::createAndSend((int) $order['id'], 1);
        ProofService::approve((string) $order['public_id'], $p['token']);
    }

    public function testGateKeepsJobBlockedUntilArtworkLocked(): void
    {
        $order = $this->acceptedOrder();
        $jobs = ProductionService::createJobs((int) $order['id'], 1);
        self::assertCount(1, $jobs);
        $jobPublic = $jobs[0]['public_id'];
        self::assertSame('BLOCKED', JobRepo::findByPublicId($jobPublic)['status']);

        // Artwork noch nicht LOCKED → Gate verweigert, Job bleibt BLOCKED.
        try {
            ProductionService::release($jobPublic, 1);
            self::fail('Erwartete ProductionGateException.');
        } catch (ProductionGateException) {
            // erwartet
        }
        self::assertSame('BLOCKED', JobRepo::findByPublicId($jobPublic)['status']);

        // Nach Freigabe des Artworks (LOCKED) greift das Gate.
        $this->lockArtwork($order);
        ProductionService::release($jobPublic, 1);
        self::assertSame('READY', JobRepo::findByPublicId($jobPublic)['status']);
    }

    public function testFullFlowWithIdempotentQuantity(): void
    {
        $order = $this->acceptedOrder();
        $this->lockArtwork($order);
        $jobs = ProductionService::createJobs((int) $order['id'], 1);
        $jobPublic = $jobs[0]['public_id'];
        $jobId = (int) $jobs[0]['id'];

        ProductionService::release($jobPublic, 1);
        ProductionService::advance($jobPublic, 'start', 1);
        self::assertSame('IN_PROGRESS', JobRepo::findByPublicId($jobPublic)['status']);

        // Gleicher idem_key zweimal → nur eine Buchung.
        ProductionService::recordQuantity($jobPublic, 'qty_good', 8, 'IDEM-1', 1);
        ProductionService::recordQuantity($jobPublic, 'qty_good', 8, 'IDEM-1', 1);
        $count = (int) Db::run("SELECT COUNT(*) FROM production_events WHERE job_id = ? AND event_type = 'qty_good'", [$jobId])->fetchColumn();
        self::assertSame(1, $count);

        ProductionService::advance($jobPublic, 'qc', 1);
        ProductionService::advance($jobPublic, 'done', 1);
        self::assertSame('DONE', JobRepo::findByPublicId($jobPublic)['status']);
        self::assertSame('DONE', Db::run('SELECT cur_production FROM orders WHERE id = ?', [(int) $order['id']])->fetchColumn());
    }

    public function testLabelRenderReprintAndPngDpi(): void
    {
        $order = $this->acceptedOrder();
        $this->lockArtwork($order);
        $jobs = ProductionService::createJobs((int) $order['id'], 1);
        $jobPublic = $jobs[0]['public_id'];
        $jobId = (int) $jobs[0]['id'];

        $r1 = LabelService::render($jobPublic, 1);
        self::assertGreaterThan(0, $r1['print_job_id']);
        self::assertSame(1, (int) Db::run("SELECT COUNT(*) FROM print_jobs WHERE entity_id = ? AND entity_type='production_job'", [$jobId])->fetchColumn());

        // Neudruck ohne Grund → Ausnahme.
        try {
            LabelService::render($jobPublic, 1, $r1['print_job_id'], '');
            self::fail('Neudruck ohne Grund muss fehlschlagen.');
        } catch (\InvalidArgumentException) {
            // erwartet
        }

        // Neudruck mit Grund → zweite print_jobs-Zeile mit reprint_of + Grund.
        $r2 = LabelService::render($jobPublic, 1, $r1['print_job_id'], 'Etikett verschmiert');
        $row = Db::run('SELECT reprint_of_id, reprint_reason FROM print_jobs WHERE id = ?', [$r2['print_job_id']])->fetch();
        self::assertSame($r1['print_job_id'], (int) $row['reprint_of_id']);
        self::assertSame('Etikett verschmiert', $row['reprint_reason']);

        // PNG-Testrender in 203/300 dpi mit erwarteten Pixelmaßen (62×100 mm).
        foreach ([203 => [496, 799], 300 => [732, 1181]] as $dpi => [$ew, $eh]) {
            $png = LabelService::renderPng($jobPublic, $dpi);
            $info = getimagesizefromstring($png);
            self::assertSame($ew, $info[0], "Breite bei {$dpi} dpi");
            self::assertSame($eh, $info[1], "Höhe bei {$dpi} dpi");
        }
    }
}
