<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\Db;
use Tpb\Core\HttpException;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\File\UploadRejected;
use Tpb\Domain\Order\OrderRepo;
use Tpb\Domain\Proof\ArtworkRepo;
use Tpb\Domain\Proof\ProofRepo;
use Tpb\Domain\Proof\ProofService;
use Tpb\Domain\Proof\ProofStateException;

/**
 * Backoffice: Aufträge und Proof-Zyklus (M4). Recht: tpb_manage_artwork
 * (owner/admin/sales). Artwork hochladen, Proof erstellen/versenden.
 */
final class OrderController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function index(array $params): void
    {
        Response::html(View::render('admin/orders/index', [
            'title'  => 'Aufträge',
            'nav'    => 'orders',
            'orders' => OrderRepo::listForAdmin(),
            'flash'  => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function show(array $params): void
    {
        $order = $this->requireOrder($params['publicId'] ?? '');
        $orderId = (int) $order['id'];
        $customer = Db::run('SELECT * FROM customers WHERE id = ? LIMIT 1', [(int) $order['customer_id']])->fetch();

        $shipment = \Tpb\Domain\Shipping\ShipmentRepo::findByOrderId($orderId);
        $lastShipmentPrint = null;
        if ($shipment !== null) {
            $lp = Db::run("SELECT id FROM print_jobs WHERE entity_type='shipment' AND entity_id=? ORDER BY id DESC LIMIT 1", [(int) $shipment['id']])->fetchColumn();
            $lastShipmentPrint = $lp !== false ? (int) $lp : null;
        }

        Response::html(View::render('admin/orders/show', [
            'title'    => 'Auftrag ' . $order['order_number'],
            'nav'      => 'orders',
            'order'    => $order,
            'customer' => $customer,
            'items'    => OrderRepo::items($orderId),
            'artworks' => ArtworkRepo::listForOrder($orderId),
            'proofs'   => ProofRepo::listForOrder($orderId),
            'shipment' => $shipment,
            'last_shipment_print' => $lastShipmentPrint,
            'flash'    => $this->takeFlash(),
        ]));
    }

    /** @param array<string,string> $params */
    public function uploadArtwork(array $params): void
    {
        $order = $this->requireOrder($params['publicId'] ?? '');
        try {
            $file = $_FILES['file'] ?? null;
            if (!is_array($file)) {
                throw new UploadRejected('Es wurde keine Datei ausgewählt.');
            }
            /** @var array{name:string,type?:string,tmp_name:string,error:int,size:int} $file */
            $res = ProofService::addArtwork((int) $order['id'], $file, Auth::id());
            $this->flash('ok', 'Artwork-Version v' . $res['version_no'] . ' hinterlegt.');
        } catch (UploadRejected | ProofStateException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/auftrag/' . $order['public_id']);
    }

    /** @param array<string,string> $params */
    public function createProof(array $params): void
    {
        $order = $this->requireOrder($params['publicId'] ?? '');
        try {
            $res = ProofService::createAndSend((int) $order['id'], Auth::id());
            $base = rtrim((string) (\Tpb\Core\Env::get('APP_URL', 'http://tpb.local') ?? 'http://tpb.local'), '/');
            $link = $base . '/proof/' . $order['public_id'] . '?t=' . $res['token'];
            $this->flash('ok', 'Proof v' . $res['version_no'] . ' versendet (Mail in der Outbox). Kundenlink: ' . $link);
        } catch (\Throwable $e) {
            $this->flash('error', 'Proof konnte nicht versendet werden: ' . $e->getMessage());
        }
        Response::redirect('/admin/auftrag/' . $order['public_id']);
    }

    /**
     * @param string $publicId
     * @return array<string,mixed>
     */
    private function requireOrder(string $publicId): array
    {
        $order = OrderRepo::findByPublicId($publicId);
        if ($order === null) {
            throw new HttpException(404, 'Auftrag nicht gefunden.');
        }
        return $order;
    }
}
