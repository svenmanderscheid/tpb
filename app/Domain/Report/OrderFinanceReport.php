<?php
declare(strict_types=1);

namespace Tpb\Domain\Report;

use Tpb\Core\Db;
use Tpb\Domain\Expense\ExpenseRepo;
use Tpb\Domain\Payment\PaymentRepo;

/**
 * Plan/Ist-Deckungsbeitrag je Auftrag (§11.7 vereinfacht, M6): Umsatz ≠ Zahlungseingang;
 * Ist-Zeit aus production_events, Ausschuss, eingefrorene Sätze (Kostenversion des Auftrags).
 */
final class OrderFinanceReport
{
    /** @return array<string,int|bool> */
    public static function forOrder(int $orderId): array
    {
        $order = Db::run('SELECT total_cents FROM orders WHERE id = ? LIMIT 1', [$orderId])->fetch();
        $revenuePlan = $order !== false ? (int) $order['total_cents'] : 0;

        // Geplante Selbstkosten (Untergrenze) + eingefrorener Stundensatz aus der Konfiguration des Auftrags.
        $calc = Db::run(
            'SELECT pc.floor_cents, cv.labor_rate_cents_h
             FROM orders o
             JOIN quotes q ON q.id = o.quote_id
             JOIN configurations cfg ON cfg.id = q.configuration_id
             JOIN price_calculations pc ON pc.configuration_id = cfg.id
             LEFT JOIN cost_versions cv ON cv.id = cfg.cost_version_id
             WHERE o.id = ? ORDER BY pc.id DESC LIMIT 1',
            [$orderId]
        )->fetch();
        $plannedSelfCost = $calc !== false ? (int) $calc['floor_cents'] : 0;
        $laborRate = $calc !== false && $calc['labor_rate_cents_h'] !== null ? (int) $calc['labor_rate_cents_h'] : 0;

        // Ist aus der Produktion.
        $agg = Db::run(
            "SELECT COALESCE(SUM(minutes),0) AS mins,
                    COALESCE(SUM(CASE WHEN event_type='scrap' THEN qty ELSE 0 END),0) AS scrap
             FROM production_events pe
             JOIN production_jobs pj ON pj.id = pe.job_id
             WHERE pj.order_id = ?",
            [$orderId]
        )->fetch();
        $actualMinutes = (int) ($agg['mins'] ?? 0);
        $scrapUnits = (int) ($agg['scrap'] ?? 0);
        $actualLaborCost = (int) round($actualMinutes * $laborRate / 60);

        $invoiced = PaymentRepo::netInvoiced($orderId);
        $received = PaymentRepo::receivedForOrder($orderId);
        $expenses = ExpenseRepo::totalForOrder($orderId);

        return [
            'revenue_plan'      => $revenuePlan,
            'invoiced'          => $invoiced,
            'received'          => $received,
            'planned_self_cost' => $plannedSelfCost,
            'db_plan'           => $revenuePlan - $plannedSelfCost,
            'actual_minutes'    => $actualMinutes,
            'actual_labor_cost' => $actualLaborCost,
            'scrap_units'       => $scrapUnits,
            'expenses'          => $expenses,
            'db_actual'         => ($invoiced > 0 ? $invoiced : $revenuePlan) - $plannedSelfCost - $expenses,
        ];
    }
}
