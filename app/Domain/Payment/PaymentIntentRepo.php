<?php
declare(strict_types=1);

namespace Tpb\Domain\Payment;

use Tpb\Core\Db;

/**
 * Zahlungsabsichten (§5.7). Eine Absicht je Checkout-Order; UNIQUE(provider, provider_ref).
 */
final class PaymentIntentRepo
{
    /** @return array<string,mixed>|null */
    public static function findByPublicId(string $publicId): ?array
    {
        $row = Db::run('SELECT * FROM payment_intents WHERE public_id = ? LIMIT 1', [$publicId])->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findByProviderRef(string $provider, string $providerRef): ?array
    {
        $row = Db::run('SELECT * FROM payment_intents WHERE provider = ? AND provider_ref = ? LIMIT 1', [$provider, $providerRef])->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findByOrderId(int $orderId): ?array
    {
        $row = Db::run('SELECT * FROM payment_intents WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$orderId])->fetch();
        return $row === false ? null : $row;
    }

    public static function setStatus(int $intentId, string $status, ?string $failureReason = null): void
    {
        Db::run(
            'UPDATE payment_intents SET status = ?, failure_reason = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$status, $failureReason, $intentId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function listForAdmin(): array
    {
        return Db::run(
            'SELECT pi.*, o.order_number, o.public_id AS order_public_id
             FROM payment_intents pi JOIN orders o ON o.id = pi.order_id
             ORDER BY pi.id DESC LIMIT 300'
        )->fetchAll();
    }
}
