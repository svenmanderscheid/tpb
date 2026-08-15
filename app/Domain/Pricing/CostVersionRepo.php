<?php
declare(strict_types=1);

namespace Tpb\Domain\Pricing;

use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * Kostenversionen inkl. Kostenpositionen (§5.3). Draft→Published; nach published
 * unveränderlich (App-seitig erzwingen, siehe CostVersionController).
 */
final class CostVersionRepo
{
    public const REF_TYPES = ['variant', 'product', 'technique'];
    public const PARAM_KEYS = ['BLANK_CENTS', 'MATERIAL_CENTS', 'SETUP_MIN', 'UNIT_MIN', 'MACHINE_MIN'];

    public static function nextVersion(): int
    {
        return (int) Db::run('SELECT COALESCE(MAX(version), 0) + 1 FROM cost_versions')->fetchColumn();
    }

    public static function create(int $version, int $laborRate, int $machineRate, int $scrapBps, int $targetMarginBps): int
    {
        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO cost_versions (version, status, labor_rate_cents_h, machine_rate_cents_h, scrap_bps, target_margin_bps, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$version, 'draft', $laborRate, $machineRate, $scrapBps, $targetMarginBps, $now, $now]
        );
        return (int) Db::pdo()->lastInsertId();
    }

    public static function updateRates(int $version, int $laborRate, int $machineRate, int $scrapBps, int $targetMarginBps): void
    {
        Db::run(
            "UPDATE cost_versions SET labor_rate_cents_h = ?, machine_rate_cents_h = ?, scrap_bps = ?, target_margin_bps = ?, updated_at = ?
             WHERE version = ? AND status = 'draft'",
            [$laborRate, $machineRate, $scrapBps, $targetMarginBps, Clock::nowUtcSeconds(), $version]
        );
    }

    public static function publish(int $version, ?int $userId): void
    {
        Db::run(
            "UPDATE cost_versions SET status = 'published', published_by = ?, published_at = ?, updated_at = ? WHERE version = ? AND status = 'draft'",
            [$userId, Clock::nowUtcSeconds(), Clock::nowUtcSeconds(), $version]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Db::run(
            'SELECT id, version, status, labor_rate_cents_h, machine_rate_cents_h, scrap_bps, target_margin_bps, published_at FROM cost_versions ORDER BY version DESC'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function findByVersion(int $version): ?array
    {
        $row = Db::run(
            'SELECT id, version, status, labor_rate_cents_h, machine_rate_cents_h, scrap_bps, target_margin_bps, published_by, published_at FROM cost_versions WHERE version = ? LIMIT 1',
            [$version]
        )->fetch();
        return $row ?: null;
    }

    // -- Items -------------------------------------------------------------

    public static function addItem(int $versionId, string $refType, int $refId, string $paramKey, int $value): void
    {
        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO cost_items (cost_version_id, ref_type, ref_id, param_key, value_int, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value_int = VALUES(value_int), updated_at = VALUES(updated_at)',
            [$versionId, $refType, $refId, $paramKey, $value, $now, $now]
        );
    }

    public static function deleteItem(int $id, int $versionId): void
    {
        Db::run('DELETE FROM cost_items WHERE id = ? AND cost_version_id = ?', [$id, $versionId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function listItems(int $versionId): array
    {
        return Db::run(
            'SELECT id, ref_type, ref_id, param_key, value_int FROM cost_items WHERE cost_version_id = ? ORDER BY ref_type ASC, ref_id ASC, param_key ASC',
            [$versionId]
        )->fetchAll();
    }
}
