<?php
declare(strict_types=1);

namespace Tpb\Domain\Catalog;

use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * Veredelungstechniken (FLEX, FLOCK, GLITTER, REFLEX …) (§5.3).
 */
final class TechniqueRepo
{
    public static function create(string $code, string $name): int
    {
        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO techniques (code, name, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$code, $name, 'active', $now, $now]
        );
        return (int) Db::pdo()->lastInsertId();
    }

    public static function update(string $code, string $name, string $status): void
    {
        Db::run(
            'UPDATE techniques SET name = ?, status = ?, updated_at = ? WHERE code = ?',
            [$name, $status, Clock::nowUtcSeconds(), $code]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Db::run('SELECT id, code, name, status FROM techniques ORDER BY code ASC')->fetchAll();
    }

    public static function codeExists(string $code): bool
    {
        return Db::run('SELECT id FROM techniques WHERE code = ? LIMIT 1', [$code])->fetch() !== false;
    }

    public static function idByCode(string $code): ?int
    {
        $row = Db::run('SELECT id FROM techniques WHERE code = ? LIMIT 1', [$code])->fetch();
        return $row ? (int) $row['id'] : null;
    }
}
