<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\HttpException;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Domain\Order\OrderRepo;
use Tpb\Domain\Shipping\ShippingService;
use Tpb\Domain\Shipping\ShippingStateException;

/**
 * Backoffice: Versand & Fulfillment je Auftrag (DECISIONS #28).
 * Recht: tpb_manage_production (Werkstatt packt und versendet).
 */
final class ShippingController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function configure(array $params): void
    {
        $order = $this->requireOrder($params['publicId'] ?? '');
        try {
            ShippingService::configure((int) $order['id'], [
                'method'             => (string) Request::post('method', 'pickup'),
                'carrier'            => (string) Request::post('carrier', ''),
                'shipping_cost_cents' => $this->eurosToCents((string) Request::post('shipping_cost', '')),
                'recipient_name'     => trim((string) Request::post('recipient_name', '')),
                'recipient_company'  => trim((string) Request::post('recipient_company', '')),
                'street'             => trim((string) Request::post('street', '')),
                'zip'                => trim((string) Request::post('zip', '')),
                'city'               => trim((string) Request::post('city', '')),
                'country'            => strtoupper(trim((string) Request::post('country', ''))),
            ], Auth::id());
            $this->flash('ok', 'Versanddaten gespeichert.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Versanddaten konnten nicht gespeichert werden: ' . $e->getMessage());
        }
        Response::redirect('/admin/auftrag/' . $order['public_id']);
    }

    /** @param array<string,string> $params */
    public function advance(array $params): void
    {
        $order = $this->requireOrder($params['publicId'] ?? '');
        try {
            ShippingService::advance((int) $order['id'], (string) Request::post('action', ''), Auth::id());
            $this->flash('ok', 'Versandstatus aktualisiert.');
        } catch (ShippingStateException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/auftrag/' . $order['public_id']);
    }

    /** @param array<string,string> $params */
    public function label(array $params): void
    {
        $order = $this->requireOrder($params['publicId'] ?? '');
        try {
            $res = ShippingService::renderLabel((int) $order['id'], Auth::id());
            $this->flash('ok', 'Versandetikett gerendert. PDF: /files/' . $res['asset_public_id']);
        } catch (\Throwable $e) {
            $this->flash('error', 'Etikett fehlgeschlagen: ' . $e->getMessage());
        }
        Response::redirect('/admin/auftrag/' . $order['public_id']);
    }

    /** @param array<string,string> $params */
    public function reprint(array $params): void
    {
        $order = $this->requireOrder($params['publicId'] ?? '');
        $reason = trim((string) Request::post('reason', ''));
        $reprintOf = (int) Request::post('reprint_of', '0');
        try {
            if ($reason === '') {
                throw new \InvalidArgumentException('Bitte einen Grund für den Neudruck angeben.');
            }
            ShippingService::renderLabel((int) $order['id'], Auth::id(), $reprintOf > 0 ? $reprintOf : null, $reason);
            $this->flash('ok', 'Neudruck des Versandetiketts erstellt.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Neudruck fehlgeschlagen: ' . $e->getMessage());
        }
        Response::redirect('/admin/auftrag/' . $order['public_id']);
    }

    /** Parst eine Euro-Eingabe ("7,50" / "7.50") zu int Cents. */
    private function eurosToCents(string $raw): int
    {
        $t = str_replace([' ', "\u{00a0}"], '', trim($raw));
        $t = str_replace(',', '.', $t);
        if ($t === '' || !is_numeric($t)) {
            return 0;
        }
        return (int) round((float) $t * 100);
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
