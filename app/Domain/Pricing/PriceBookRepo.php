<?php
declare(strict_types=1);

namespace Tpb\Domain\Pricing;

use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * Preisbücher inkl. Staffeln (tiers) und Parameter (§5.3/§6.2).
 * Draft→Published-Workflow; nach published sind Tiers/Params unveränderlich
 * (App-seitig erzwingen, siehe PriceBookController).
 */
final class PriceBookRepo
{
    public static function nextVersion(): int
    {
        return (int) Db::run('SELECT COALESCE(MAX(version), 0) + 1 FROM price_books')->fetchColumn();
    }

    public static function create(int $version, string $currency): int
    {
        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO price_books (version, currency, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$version, $currency, 'draft', $now, $now]
        );
        return (int) Db::pdo()->lastInsertId();
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Db::run(
            'SELECT id, version, currency, status, valid_from, valid_until, published_at FROM price_books ORDER BY version DESC'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function findByVersion(int $version): ?array
    {
        $row = Db::run(
            'SELECT id, version, currency, status, valid_from, valid_until, published_by, published_at FROM price_books WHERE version = ? LIMIT 1',
            [$version]
        )->fetch();
        return $row ?: null;
    }

    public static function publish(int $version, ?int $userId): void
    {
        Db::run(
            "UPDATE price_books SET status = 'published', published_by = ?, published_at = ?, updated_at = ? WHERE version = ? AND status = 'draft'",
            [$userId, Clock::nowUtcSeconds(), Clock::nowUtcSeconds(), $version]
        );
    }

    public static function retire(int $version): void
    {
        Db::run(
            "UPDATE price_books SET status = 'retired', updated_at = ? WHERE version = ? AND status = 'published'",
            [Clock::nowUtcSeconds(), $version]
        );
    }

    // -- Tiers -------------------------------------------------------------

    public static function addTier(int $bookId, int $productId, int $qtyFrom, ?int $qtyTo, int $unitCents): void
    {
        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO price_tiers (price_book_id, product_id, qty_from, qty_to, unit_cents, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$bookId, $productId, $qtyFrom, $qtyTo, $unitCents, $now, $now]
        );
    }

    public static function deleteTier(int $id, int $bookId): void
    {
        Db::run('DELETE FROM price_tiers WHERE id = ? AND price_book_id = ?', [$id, $bookId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function listTiers(int $bookId): array
    {
        return Db::run(
            'SELECT t.id, t.product_id, p.name AS product_name, t.qty_from, t.qty_to, t.unit_cents
             FROM price_tiers t JOIN products p ON p.id = t.product_id
             WHERE t.price_book_id = ? ORDER BY p.name ASC, t.qty_from ASC',
            [$bookId]
        )->fetchAll();
    }

    public static function tierStartExists(int $bookId, int $productId, int $qtyFrom): bool
    {
        return Db::run(
            'SELECT id FROM price_tiers WHERE price_book_id = ? AND product_id = ? AND qty_from = ? LIMIT 1',
            [$bookId, $productId, $qtyFrom]
        )->fetch() !== false;
    }

    // -- Params ------------------------------------------------------------

    public static function setParam(int $bookId, string $key, int $value, ?string $note): void
    {
        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO price_params (price_book_id, param_key, value_int, note, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value_int = VALUES(value_int), note = VALUES(note), updated_at = VALUES(updated_at)',
            [$bookId, $key, $value, $note, $now, $now]
        );
    }

    public static function deleteParam(int $id, int $bookId): void
    {
        Db::run('DELETE FROM price_params WHERE id = ? AND price_book_id = ?', [$id, $bookId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function listParams(int $bookId): array
    {
        return Db::run(
            'SELECT id, param_key, value_int, note FROM price_params WHERE price_book_id = ? ORDER BY param_key ASC',
            [$bookId]
        )->fetchAll();
    }
}
