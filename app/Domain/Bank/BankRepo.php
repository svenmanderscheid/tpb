<?php
declare(strict_types=1);

namespace Tpb\Domain\Bank;

use Tpb\Core\Db;

/** Lesezugriffe auf Bankimporte und -zeilen (§5.6). */
final class BankRepo
{
    public static function importByFileHash(string $fileSha): ?array
    {
        $row = Db::run('SELECT * FROM bank_imports WHERE file_sha256 = ? LIMIT 1', [$fileSha])->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public static function listImports(int $limit = 100): array
    {
        return Db::run(
            'SELECT bi.*, (SELECT COUNT(*) FROM bank_lines bl WHERE bl.bank_import_id = bi.id) AS line_count
             FROM bank_imports bi ORDER BY bi.id DESC LIMIT ' . (int) $limit
        )->fetchAll();
    }

    public static function lineById(int $id): ?array
    {
        $row = Db::run('SELECT * FROM bank_lines WHERE id = ? LIMIT 1', [$id])->fetch();
        return $row === false ? null : $row;
    }

    /** Offene Bankzeilen (noch nicht bestätigt/ignoriert), neueste zuerst. @return array<int,array<string,mixed>> */
    public static function openLines(int $limit = 200): array
    {
        return Db::run(
            "SELECT * FROM bank_lines WHERE match_status IN ('open','suggested') ORDER BY booking_date DESC, id DESC LIMIT " . (int) $limit
        )->fetchAll();
    }

    public static function openCount(): int
    {
        return (int) Db::run("SELECT COUNT(*) FROM bank_lines WHERE match_status IN ('open','suggested')")->fetchColumn();
    }
}
