<?php
declare(strict_types=1);

namespace Tpb\Domain\Shop;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Invoice\InvoiceService;
use Tpb\Domain\Outbox\MailTemplates;
use Tpb\Domain\Outbox\Outbox;
use Tpb\Domain\Payment\Gateway\GatewayFactory;
use Tpb\Domain\Payment\PaymentIntentRepo;
use Tpb\Domain\Payment\PaymentService;
use Tpb\Domain\Status\Status;

/**
 * Verarbeitung eingehender Zahlungs-Webhooks (§5.7). Reihenfolge: Signatur verifizieren
 * → Rohevent als Beleg persistieren → Betrag/Währung gegen die Order abgleichen → in
 * einer Transaktion buchen. Idempotenz doppelt: UNIQUE(provider, event_ref) UND
 * Idempotency-Key shop_paid_<order_id>. Bei Betrags-/Währungsabweichung: Alarm-Audit,
 * KEINE Buchung. Erstattungen im ersten Ausbau manuell + Gutschrift (kein Auto-Refund).
 *
 * Rückgabe: ['status' => ..., 'http' => int].
 */
final class ShopWebhookService
{
    /** @return array{status:string,http:int} */
    public static function handle(string $rawBody, string $signature): array
    {
        $gateway = GatewayFactory::active();
        $provider = $gateway->provider();
        $sigValid = $gateway->verifySignature($rawBody, $signature);

        $event = json_decode($rawBody, true);
        if (!is_array($event) || empty($event['event_ref'])) {
            return ['status' => 'bad_request', 'http' => 400];
        }
        $eventRef = (string) $event['event_ref'];
        $eventType = (string) ($event['event_type'] ?? '');
        $now = Clock::nowUtcSeconds();

        // Rohevent als Beleg persistieren; UNIQUE(provider, event_ref) ⇒ Duplikat = idempotent.
        try {
            Db::run(
                'INSERT INTO payment_webhook_events (provider, event_ref, event_type, payload_json, signature_valid, received_at, process_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$provider, $eventRef, $eventType, $rawBody, $sigValid ? 1 : 0, $now, 'pending']
            );
        } catch (\PDOException) {
            return ['status' => 'duplicate', 'http' => 200];
        }
        $eventId = (int) Db::pdo()->lastInsertId();

        if (!$sigValid) {
            self::mark($eventId, 'ignored', 'invalid_signature');
            return ['status' => 'invalid_signature', 'http' => 400];
        }
        if ($eventType !== 'payment.succeeded') {
            self::mark($eventId, 'ignored', null);
            return ['status' => 'ignored', 'http' => 200];
        }

        $intent = PaymentIntentRepo::findByProviderRef($provider, (string) ($event['provider_ref'] ?? ''));
        if ($intent === null) {
            self::mark($eventId, 'error', 'unknown_intent');
            return ['status' => 'unknown_intent', 'http' => 400];
        }
        $order = Db::run('SELECT * FROM orders WHERE id = ? LIMIT 1', [(int) $intent['order_id']])->fetch();
        if ($order === false) {
            self::mark($eventId, 'error', 'unknown_order');
            return ['status' => 'unknown_order', 'http' => 400];
        }

        // Pflicht: Betrag + Währung gegen die Order abgleichen.
        $amount = (int) ($event['amount_cents'] ?? -1);
        $currency = (string) ($event['currency'] ?? '');
        if ($amount !== (int) $order['total_cents'] || $currency !== (string) $order['currency']) {
            Audit::log('order', (string) $order['public_id'], 'shop.amount_mismatch', [
                'actor_label' => 'system', 'reason_code' => 'mismatch',
                'metadata'    => ['expected' => (int) $order['total_cents'], 'got' => $amount, 'currency' => $currency],
            ]);
            self::mark($eventId, 'error', 'amount_mismatch');
            return ['status' => 'mismatch', 'http' => 200];
        }

        // Zweite Idempotenz-Schranke: shop_paid_<order_id>.
        $idemKey = 'shop_paid_' . (int) $order['id'];
        $ins = Db::run(
            'INSERT IGNORE INTO idempotency_keys (idem_key, scope, created_at, expires_at) VALUES (?, ?, ?, ?)',
            [$idemKey, 'shop_paid', $now, Clock::nowUtc()->modify('+30 days')->format('Y-m-d H:i:s')]
        );
        if ($ins->rowCount() === 0) {
            self::mark($eventId, 'done', 'already_processed');
            return ['status' => 'already_processed', 'http' => 200];
        }

        // Rechnung ausstellen (Issue-Flow §11.2), Zahlung buchen + zuordnen, Order bestätigen.
        $draft = InvoiceService::createDraft((int) $order['id'], null);
        InvoiceService::issue((int) $draft['id'], null);
        PaymentService::record((int) $order['id'], 'other', (int) $order['total_cents'], substr($now, 0, 10), (string) ($event['provider_ref'] ?? ''), null);

        Db::tx(function () use ($order, $intent): void {
            Status::transition('order', (int) $order['id'], 'order', 'PENDING_PAYMENT', 'CONFIRMED', ['actor_label' => 'system', 'reason' => 'shop_paid']);
            PaymentIntentRepo::setStatus((int) $intent['id'], 'succeeded');

            $snapshot = json_decode((string) $order['customer_snapshot_json'], true) ?: [];
            $omail = [
                'order_id'        => (int) $order['id'],
                'order_public_id' => (string) $order['public_id'],
                'order_number'    => (string) $order['order_number'],
                'to_email'        => $snapshot['email'] ?? null,
                'to_name'         => trim((string) ($snapshot['first_name'] ?? '') . ' ' . (string) ($snapshot['last_name'] ?? '')),
                'total_cents'     => (int) $order['total_cents'],
                'currency'        => (string) $order['currency'],
            ];
            if (!empty($omail['to_email'])) {
                Outbox::enqueue('mail.order_confirmed', array_merge($omail, MailTemplates::orderConfirmed($omail)), 'order_confirmed_' . (int) $order['id']);
            }
            Audit::log('order', (string) $order['public_id'], 'shop.paid', ['actor_label' => 'system', 'from_state' => 'PENDING_PAYMENT', 'to_state' => 'CONFIRMED']);
        });

        // Bezahlt ⇒ reservierten Bestand abbuchen (eigene Transaktion, idempotent).
        \Tpb\Domain\Stock\StockService::consumeForOrder((int) $order['id'], null);

        self::mark($eventId, 'done', null);
        return ['status' => 'ok', 'http' => 200];
    }

    private static function mark(int $eventId, string $status, ?string $error): void
    {
        Db::run(
            'UPDATE payment_webhook_events SET process_status = ?, processed_at = ?, error_message = ? WHERE id = ?',
            [$status, Clock::nowUtcSeconds(), $error, $eventId]
        );
    }
}
