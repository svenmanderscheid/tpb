<?php
declare(strict_types=1);

namespace Tpb\Domain\Shipping;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\File\AssetService;
use Tpb\Domain\Label\Barcode;
use Tpb\Domain\Pdf\PdfService;
use Tpb\Domain\Status\Status;

/**
 * Versand & Fulfillment (DECISIONS #28). Versandart + Lieferadresse + Kosten je Sendung;
 * Fulfillment-Statusfluss (§7) über die Order-Achse; Hausetikett (Eigenlieferung/Standard
 * bis Carrier-Anbindung) mit Empfänger + Auftrags-Barcode/QR.
 */
final class ShippingService
{
    public const METHODS = ['pickup', 'self_premium', 'carrier_standard'];
    private const W_MM = 100.0;
    private const H_MM = 150.0;

    /** Aktion → [Von-Zustand(e), Ziel je nach Versandart|fix]. */
    public static function ensure(int $orderId, ?int $actorUserId): array
    {
        return ShipmentRepo::ensureForOrder($orderId, $actorUserId);
    }

    /**
     * Setzt Versandart, Carrier (nur Standardversand) und Kosten. Bei Standardversand
     * ohne expliziten Carrier wird der günstigste Anbieter der Registry gewählt.
     *
     * @param array<string,mixed> $data method, carrier?, shipping_cost_cents?, address fields?
     */
    public static function configure(int $orderId, array $data, ?int $actorUserId): void
    {
        Db::tx(function () use ($orderId, $data, $actorUserId): void {
            $shipment = ShipmentRepo::ensureForOrder($orderId, $actorUserId);
            $method = in_array($data['method'] ?? '', self::METHODS, true) ? (string) $data['method'] : (string) $shipment['method'];

            $cost = isset($data['shipping_cost_cents']) ? max(0, (int) $data['shipping_cost_cents']) : (int) $shipment['shipping_cost_cents'];
            $carrier = null;
            if ($method === 'carrier_standard') {
                $carrier = trim((string) ($data['carrier'] ?? ''));
                if ($carrier === '') {
                    // „Günstigster Anbieter": bis echte Carrier existieren, Eigenlieferung/Haus.
                    $carrier = CarrierRegistry::cheapest(array_merge($shipment, ['shipping_cost_cents' => $cost]))->name();
                }
            } elseif ($method === 'pickup') {
                $cost = 0;
            }

            $fields = ['method' => $method, 'carrier' => $carrier, 'shipping_cost_cents' => $cost];
            foreach (['recipient_name', 'recipient_company', 'street', 'zip', 'city', 'country'] as $f) {
                if (array_key_exists($f, $data)) {
                    $fields[$f] = $data[$f] !== '' ? $data[$f] : null;
                }
            }
            ShipmentRepo::update((int) $shipment['id'], $fields);

            Audit::log('shipment', (string) $shipment['public_id'], 'shipment.configured', [
                'actor_user_id' => $actorUserId,
                'metadata'      => ['method' => $method, 'carrier' => $carrier, 'cost' => $cost],
            ]);
        });
    }

    /** Fulfillment-Statusfluss (§7). Aktion abhängig von der Versandart. */
    public static function advance(int $orderId, string $action, ?int $actorUserId): void
    {
        Db::tx(function () use ($orderId, $action, $actorUserId): void {
            $order = Db::run('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId])->fetch();
            if ($order === false) {
                throw new \InvalidArgumentException('Auftrag nicht gefunden.');
            }
            $shipment = ShipmentRepo::ensureForOrder($orderId, $actorUserId);
            $cur = (string) $order['cur_fulfillment'];
            $method = (string) $shipment['method'];
            $isPickup = $method === 'pickup';

            [$from, $to] = self::resolveTransition($action, $cur, $isPickup);

            Status::transition('order', $orderId, 'fulfillment', $from, $to, ['actor_user_id' => $actorUserId]);
            $now = Clock::nowUtcSeconds();
            Db::run('UPDATE orders SET cur_fulfillment = ?, updated_at = ? WHERE id = ?', [$to, $now, $orderId]);

            $stamp = match ($to) {
                'PACKING'          => 'packed_at',
                'SHIPPED'          => 'shipped_at',
                'DELIVERED', 'COLLECTED' => 'delivered_at',
                default            => null,
            };
            if ($stamp !== null) {
                ShipmentRepo::update((int) $shipment['id'], [$stamp => $now]);
            }

            Audit::log('order', (string) $order['public_id'], 'fulfillment.' . strtolower($to), [
                'actor_user_id' => $actorUserId, 'from_state' => $from, 'to_state' => $to,
            ]);
        });
    }

    /**
     * Rendert das Hausetikett (100×150 mm) und protokolliert print_jobs. Neudruck nur
     * mit Grund. Für echte Carrier mit eigenem Label würde hier deren PDF genutzt.
     *
     * @return array{print_job_id:int,asset_public_id:string}
     */
    public static function renderLabel(int $orderId, ?int $actorUserId, ?int $reprintOfId = null, ?string $reprintReason = null): array
    {
        return Db::tx(function () use ($orderId, $actorUserId, $reprintOfId, $reprintReason): array {
            if ($reprintOfId !== null && ($reprintReason === null || trim($reprintReason) === '')) {
                throw new \InvalidArgumentException('Ein Neudruck erfordert einen Grund.');
            }
            $order = Db::run('SELECT order_number FROM orders WHERE id = ? LIMIT 1', [$orderId])->fetch();
            if ($order === false) {
                throw new \InvalidArgumentException('Auftrag nicht gefunden.');
            }
            $shipment = ShipmentRepo::ensureForOrder($orderId, $actorUserId);

            $label = self::buildLabel($order, $shipment);
            $pdf = PdfService::renderLabel('label_shipment', ['label' => $label], self::W_MM, self::H_MM);

            $asset = AssetService::storeGenerated(
                $pdf, 'Versandetikett_' . (string) $order['order_number'] . '.pdf', 'application/pdf',
                'label_pdf', 'temp_30d', $actorUserId, 'shipment', (int) $shipment['id']
            );

            $n = (int) Db::run("SELECT COUNT(*) FROM print_jobs WHERE entity_type = 'shipment' AND entity_id = ?", [(int) $shipment['id']])->fetchColumn();
            Db::run(
                'INSERT INTO print_jobs
                    (label_type, entity_type, entity_id, template_version, copies, idempotency_key, payload_json,
                     render_format, rendered_asset_id, rendered_sha256, status, requested_by, requested_at, reprint_of_id, reprint_reason)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    'shipment', 'shipment', (int) $shipment['id'], 'v1', 1, 'shipment_label:' . (int) $shipment['id'] . ':' . $n,
                    json_encode($label, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'pdf', $asset['id'], $asset['sha256'], 'rendered', $actorUserId, Clock::nowUtcSeconds(),
                    $reprintOfId, $reprintReason !== null && trim($reprintReason) !== '' ? mb_substr($reprintReason, 0, 255) : null,
                ]
            );
            $printJobId = (int) Db::pdo()->lastInsertId();
            ShipmentRepo::update((int) $shipment['id'], ['label_asset_id' => $asset['id']]);

            Audit::log('shipment', (string) $shipment['public_id'], $reprintOfId !== null ? 'shipment.label.reprinted' : 'shipment.label.rendered', [
                'actor_user_id' => $actorUserId, 'metadata' => ['print_job_id' => $printJobId, 'sha256' => $asset['sha256']],
            ]);

            return ['print_job_id' => $printJobId, 'asset_public_id' => (string) $asset['public_id']];
        });
    }

    /**
     * @return array{0:?string,1:string} [Von-Zustand, Ziel-Zustand]
     * @throws ShippingStateException
     */
    private static function resolveTransition(string $action, string $cur, bool $isPickup): array
    {
        return match ($action) {
            'pack'    => $cur === 'UNFULFILLED' ? ['UNFULFILLED', 'PACKING'] : self::illegal($action, $cur),
            'ready'   => $cur === 'PACKING' ? ['PACKING', $isPickup ? 'READY_FOR_PICKUP' : 'READY_TO_SHIP'] : self::illegal($action, $cur),
            'collect' => $cur === 'READY_FOR_PICKUP' ? ['READY_FOR_PICKUP', 'COLLECTED'] : self::illegal($action, $cur),
            'ship'    => $cur === 'READY_TO_SHIP' ? ['READY_TO_SHIP', 'SHIPPED'] : self::illegal($action, $cur),
            'deliver' => $cur === 'SHIPPED' ? ['SHIPPED', 'DELIVERED'] : self::illegal($action, $cur),
            default   => self::illegal($action, $cur),
        };
    }

    /** @return never */
    private static function illegal(string $action, string $cur): array
    {
        throw new ShippingStateException("Aktion '{$action}' im Zustand {$cur} nicht erlaubt.");
    }

    /**
     * @param array<string,mixed> $order
     * @param array<string,mixed> $shipment
     * @return array<string,mixed>
     */
    private static function buildLabel(array $order, array $shipment): array
    {
        $methodLabels = ['pickup' => 'Abholung', 'self_premium' => 'Eigenlieferung (Premium)', 'carrier_standard' => 'Versand'];
        $orderNumber = (string) $order['order_number'];
        return [
            'order_number'      => $orderNumber,
            'method'            => $methodLabels[(string) $shipment['method']] ?? (string) $shipment['method'],
            'carrier'           => $shipment['carrier'] ?? null,
            'recipient_name'    => (string) $shipment['recipient_name'],
            'recipient_company' => $shipment['recipient_company'] ?? null,
            'street'            => $shipment['street'] ?? null,
            'zip'               => $shipment['zip'] ?? null,
            'city'              => $shipment['city'] ?? null,
            'country'           => $shipment['country'] ?? null,
            'tracking_ref'      => $shipment['tracking_ref'] ?? null,
            'barcode'           => Barcode::code128($orderNumber),
            'qr'                => Barcode::qrPng('TPB:ORD:' . $orderNumber, 4),
        ];
    }
}
