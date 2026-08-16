<?php
declare(strict_types=1);

namespace Tpb\Domain\Ops;

use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * Betriebszustand (§ M7): DB-Erreichbarkeit, letztes Backup, fehlgeschlagene Outbox,
 * Speicherplatz, Quarantäne-Stau. `liveness()` liefert die minimale, unsensible Ansicht
 * für den öffentlichen /health-Endpunkt; `full()` die Details für CLI/Cron und Admin.
 */
final class HealthCheck
{
    private const BACKUP_MAX_AGE_HOURS = 26;
    private const DISK_MIN_FREE_BYTES = 500 * 1024 * 1024; // 500 MB
    private const QUARANTINE_STUCK_HOURS = 2;

    /** Minimal + unsensibel (öffentlich): nur DB-Erreichbarkeit. @return array{status:string} */
    public static function liveness(): array
    {
        try {
            Db::pdo()->query('SELECT 1');
            return ['status' => 'ok'];
        } catch (\Throwable) {
            return ['status' => 'degraded'];
        }
    }

    /**
     * Detailprüfung. @return array{ok:bool,checks:array<int,array<string,mixed>>}
     */
    public static function full(): array
    {
        $checks = [
            self::db(),
            self::backup(),
            self::failedOutbox(),
            self::diskSpace(),
            self::quarantine(),
        ];
        $ok = true;
        foreach ($checks as $c) {
            if (!$c['ok']) {
                $ok = false;
            }
        }
        return ['ok' => $ok, 'checks' => $checks];
    }

    /** @return array<string,mixed> */
    private static function db(): array
    {
        try {
            Db::pdo()->query('SELECT 1');
            return ['name' => 'db', 'ok' => true, 'detail' => 'erreichbar'];
        } catch (\Throwable $e) {
            return ['name' => 'db', 'ok' => false, 'detail' => 'nicht erreichbar'];
        }
    }

    /** @return array<string,mixed> */
    private static function backup(): array
    {
        $dir = TPB_ROOT . '/private/tpb/backups';
        $files = is_dir($dir) ? (glob($dir . '/*.sql.gz') ?: []) : [];
        if ($files === []) {
            return ['name' => 'backup', 'ok' => false, 'detail' => 'kein Backup gefunden'];
        }
        $newest = max(array_map(static fn ($f) => (int) filemtime($f), $files));
        $ageHours = (time() - $newest) / 3600;
        $ok = $ageHours <= self::BACKUP_MAX_AGE_HOURS;
        return ['name' => 'backup', 'ok' => $ok, 'detail' => sprintf('letztes Backup vor %.1f h', $ageHours)];
    }

    /** @return array<string,mixed> */
    private static function failedOutbox(): array
    {
        $n = (int) Db::run("SELECT COUNT(*) FROM outbox_events WHERE status = 'failed'")->fetchColumn();
        return ['name' => 'outbox', 'ok' => $n === 0, 'detail' => "{$n} endgültig fehlgeschlagen"];
    }

    /** @return array<string,mixed> */
    private static function diskSpace(): array
    {
        $free = @disk_free_space(TPB_ROOT);
        $free = $free === false ? 0 : (int) $free;
        $ok = $free >= self::DISK_MIN_FREE_BYTES;
        return ['name' => 'disk', 'ok' => $ok, 'detail' => sprintf('%.0f MB frei', $free / 1048576)];
    }

    /** @return array<string,mixed> */
    private static function quarantine(): array
    {
        $cutoff = Clock::nowUtc()->modify('-' . self::QUARANTINE_STUCK_HOURS . ' hours')->format('Y-m-d H:i:s');
        $n = (int) Db::run("SELECT COUNT(*) FROM assets WHERE security_status = 'quarantine' AND created_at < ?", [$cutoff])->fetchColumn();
        return ['name' => 'quarantine', 'ok' => $n === 0, 'detail' => "{$n} länger als " . self::QUARANTINE_STUCK_HOURS . ' h in Quarantäne'];
    }
}
