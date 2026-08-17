<?php
declare(strict_types=1);

namespace Tpb\Domain\Report;

use Tpb\Core\Db;

/**
 * Kapazitäts-Wochenansicht (§11.7/§12, M9): Σ planned_min je Fälligkeitswoche gegen
 * capacity.week_minutes (business_settings). Ampel + Auslastung – kein Auto-Block.
 */
final class CapacityReport
{
    private const DEFAULT_WEEK_MINUTES = 2400; // 40 h Platzhalter

    public static function capacityMinutes(): int
    {
        $row = Db::run("SELECT value_json FROM business_settings WHERE setting_key = 'capacity.week_minutes' LIMIT 1")->fetch();
        $v = $row !== false ? (int) json_decode((string) $row['value_json'], true) : self::DEFAULT_WEEK_MINUTES;
        return $v > 0 ? $v : self::DEFAULT_WEEK_MINUTES;
    }

    /**
     * @return array<int,array<string,mixed>> je Woche: week_start, planned_min, jobs, capacity, ratio_bp, light
     */
    public static function weeks(): array
    {
        $cap = self::capacityMinutes();
        $rows = Db::run(
            "SELECT DATE_SUB(due_date, INTERVAL WEEKDAY(due_date) DAY) AS week_start,
                    SUM(planned_min) AS planned, COUNT(*) AS jobs
             FROM production_jobs
             WHERE due_date IS NOT NULL AND planned_min IS NOT NULL AND status NOT IN ('DONE','SCRAPPED')
             GROUP BY week_start ORDER BY week_start ASC"
        )->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $planned = (int) $r['planned'];
            $ratioBp = $cap > 0 ? (int) round($planned * 10000 / $cap) : 0;
            $out[] = [
                'week_start'  => (string) $r['week_start'],
                'planned_min' => $planned,
                'jobs'        => (int) $r['jobs'],
                'capacity'    => $cap,
                'ratio_bp'    => $ratioBp,
                'light'       => $ratioBp > 10000 ? 'red' : ($ratioBp >= 8000 ? 'yellow' : 'green'),
            ];
        }
        return $out;
    }

    /** Geplante Minuten der Woche, in die $date fällt (Auslastungshinweis bei Termineingabe). */
    public static function loadForWeekOf(string $date): int
    {
        return (int) Db::run(
            "SELECT COALESCE(SUM(planned_min),0) FROM production_jobs
             WHERE due_date IS NOT NULL AND planned_min IS NOT NULL AND status NOT IN ('DONE','SCRAPPED')
               AND DATE_SUB(due_date, INTERVAL WEEKDAY(due_date) DAY) = DATE_SUB(?, INTERVAL WEEKDAY(?) DAY)",
            [$date, $date]
        )->fetchColumn();
    }
}
