<?php
declare(strict_types=1);

namespace Tpb\Domain\Payment;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Invoice\InvoiceRepo;

/**
 * Zahlungserfassung + Zuordnung (§5.5). Die Zahlungsachse wird ABGELEITET (§7) aus
 * Forderung (ausgestellte Rechnungen) und Zahlungseingang – nie manuell gesetzt.
 */
final class PaymentService
{
    /**
     * Erfasst eine Zahlung, ordnet sie automatisch der Primärrechnung des Auftrags zu
     * (bis zu deren offenem Betrag) und leitet die Zahlungsachse neu ab.
     */
    public static function record(int $orderId, string $method, int $amountCents, string $receivedAt, ?string $reference, ?int $actorUserId): void
    {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Betrag muss größer als 0 sein.');
        }
        $method = in_array($method, ['bank_transfer', 'cash', 'other'], true) ? $method : 'other';

        Db::tx(function () use ($orderId, $method, $amountCents, $receivedAt, $reference, $actorUserId): void {
            $order = Db::run('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId])->fetch();
            if ($order === false) {
                throw new \InvalidArgumentException('Auftrag nicht gefunden.');
            }

            Db::run(
                'INSERT INTO payments (order_id, method, amount_cents, currency, received_at, reference, recorded_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$orderId, $method, $amountCents, (string) $order['currency'], $receivedAt, $reference, $actorUserId, Clock::nowUtcSeconds()]
            );
            $paymentId = (int) Db::pdo()->lastInsertId();

            // Automatische Zuordnung zur Primärrechnung (bis zum offenen Betrag).
            $inv = InvoiceRepo::primaryForOrder($orderId);
            if ($inv !== null && in_array((string) $inv['status'], ['ISSUED', 'SENT'], true)) {
                $open = (int) $inv['gross_cents'] - PaymentRepo::allocatedToInvoice((int) $inv['id']);
                $alloc = max(0, min($amountCents, $open));
                if ($alloc > 0) {
                    Db::run(
                        'INSERT INTO payment_allocations (payment_id, invoice_id, amount_cents, created_at) VALUES (?, ?, ?, ?)',
                        [$paymentId, (int) $inv['id'], $alloc, Clock::nowUtcSeconds()]
                    );
                }
            }

            self::deriveForOrder($orderId, $actorUserId);

            Audit::log('order', (string) $order['public_id'], 'payment.recorded', [
                'actor_user_id' => $actorUserId,
                'metadata'      => ['payment_id' => $paymentId, 'amount' => $amountCents, 'method' => $method],
            ]);
        });
    }

    /**
     * Leitet cur_payment aus Forderung/Eingang ab und protokolliert den Wechsel (§7).
     * Innerhalb einer Transaktion aufrufen (auch aus dem Rechnungs-Issue-Flow).
     */
    public static function deriveForOrder(int $orderId, ?int $actorUserId): void
    {
        $current = (string) Db::run('SELECT cur_payment FROM orders WHERE id = ? LIMIT 1', [$orderId])->fetchColumn();
        $invoiced = PaymentRepo::netInvoiced($orderId);
        $received = PaymentRepo::receivedForOrder($orderId);

        if ($invoiced <= 0) {
            $state = 'NOT_DUE';
        } elseif ($received <= 0) {
            $state = 'UNPAID';
        } elseif ($received < $invoiced) {
            $state = 'PARTIALLY_PAID';
        } else {
            $state = 'PAID';
        }

        if ($state === $current) {
            return;
        }
        Db::run('UPDATE orders SET cur_payment = ?, updated_at = ? WHERE id = ?', [$state, Clock::nowUtcSeconds(), $orderId]);
        // Abgeleitete Achse: status_events direkt (keine States-Validierung, §7).
        Db::run(
            'INSERT INTO status_events (aggregate_type, aggregate_id, axis, from_state, to_state, actor_user_id, actor_label, reason, occurred_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ['order', $orderId, 'payment', $current, $state, $actorUserId, 'system', 'derived', Clock::nowUtcMs()]
        );
    }
}
