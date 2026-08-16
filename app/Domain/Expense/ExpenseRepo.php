<?php
declare(strict_types=1);

namespace Tpb\Domain\Expense;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;
use Tpb\Domain\Audit\Audit;

/**
 * Ausgabenerfassung (§5.6, M6) mit optionalem Beleg (Asset) und optionaler
 * Auftragszuordnung. Beträge als int Cents.
 */
final class ExpenseRepo
{
    /**
     * @param array<string,mixed> $d expense_date, vendor, category, description?, amount_cents, order_id?, receipt_asset_id?
     * @return array{id:int,public_id:string}
     */
    public static function create(array $d, ?int $actorUserId): array
    {
        $publicId = Ulid::generate();
        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO expenses (public_id, expense_date, vendor, category, description, amount_cents, currency, receipt_asset_id, order_id, recorded_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $publicId, (string) $d['expense_date'], mb_substr((string) $d['vendor'], 0, 120), mb_substr((string) $d['category'], 0, 48),
                isset($d['description']) && $d['description'] !== '' ? mb_substr((string) $d['description'], 0, 255) : null,
                (int) $d['amount_cents'], 'EUR',
                $d['receipt_asset_id'] ?? null, $d['order_id'] ?? null, $actorUserId, $now, $now,
            ]
        );
        $id = (int) Db::pdo()->lastInsertId();
        Audit::log('expense', $publicId, 'expense.created', ['actor_user_id' => $actorUserId, 'metadata' => ['amount' => (int) $d['amount_cents'], 'vendor' => $d['vendor']]]);
        return ['id' => $id, 'public_id' => $publicId];
    }

    /** @return array<int,array<string,mixed>> */
    public static function listRecent(int $limit = 200): array
    {
        return Db::run(
            'SELECT e.*, o.order_number, a.public_id AS receipt_public_id
             FROM expenses e
             LEFT JOIN orders o ON o.id = e.order_id
             LEFT JOIN assets a ON a.id = e.receipt_asset_id
             ORDER BY e.expense_date DESC, e.id DESC LIMIT ' . (int) $limit
        )->fetchAll();
    }

    public static function totalForOrder(int $orderId): int
    {
        return (int) Db::run('SELECT COALESCE(SUM(amount_cents), 0) FROM expenses WHERE order_id = ?', [$orderId])->fetchColumn();
    }
}
