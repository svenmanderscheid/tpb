<?php
declare(strict_types=1);

/**
 * Ablauf-Job (§7, Pfad B). Setzt nicht bezahlte Shop-Orders (PENDING_PAYMENT) nach
 * Ablauf der Checkout-Frist (CHECKOUT_TTL_HOURS, Default 24) auf EXPIRED – ohne
 * Rechnung, ohne Lagerbezug – und dieselbe Ausführung lässt abgelaufene Angebote
 * (SENT über valid_until) auf EXPIRED laufen. Lokal per geplantem Task / Cron aufrufen.
 *
 * Aufruf: php cli/expire.php
 */

require __DIR__ . '/_bootstrap.php';

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Env;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Order\OrderRepo;
use Tpb\Domain\Payment\PaymentIntentRepo;
use Tpb\Domain\Status\Status;

$ttlHours = Env::int('CHECKOUT_TTL_HOURS', 24);
$cutoff = Clock::nowUtc()->modify("-{$ttlHours} hours")->format('Y-m-d H:i:s');

$orders = 0;
foreach (OrderRepo::pendingPaymentOlderThan($cutoff) as $o) {
    Db::tx(function () use ($o): void {
        Status::transition('order', (int) $o['id'], 'order', 'PENDING_PAYMENT', 'EXPIRED', ['actor_label' => 'system', 'reason' => 'checkout_ttl']);
        $intent = PaymentIntentRepo::findByOrderId((int) $o['id']);
        if ($intent !== null) {
            PaymentIntentRepo::setStatus((int) $intent['id'], 'expired');
        }
        Audit::log('order', (string) $o['public_id'], 'checkout.expired', ['actor_label' => 'system', 'to_state' => 'EXPIRED']);
    });
    $orders++;
}

$today = Clock::nowUtc()->format('Y-m-d');
$quotes = 0;
foreach (Db::run("SELECT id, public_id FROM quotes WHERE status = 'SENT' AND valid_until IS NOT NULL AND valid_until < ?", [$today])->fetchAll() as $q) {
    Db::tx(function () use ($q): void {
        Db::run("UPDATE quotes SET status = 'EXPIRED', updated_at = ? WHERE id = ?", [Clock::nowUtcSeconds(), (int) $q['id']]);
        Status::transition('quote', (int) $q['id'], 'quote', 'SENT', 'EXPIRED', ['actor_label' => 'system', 'reason' => 'valid_until_passed']);
        Audit::log('quote', (string) $q['public_id'], 'quote.expired', ['actor_label' => 'system', 'to_state' => 'EXPIRED']);
    });
    $quotes++;
}

echo "Ablauf-Job fertig: {$orders} Order(s) EXPIRED, {$quotes} Angebot(e) EXPIRED.\n";
