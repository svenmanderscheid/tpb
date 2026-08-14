<?php
declare(strict_types=1);

namespace Tpb\Domain\Finance;

use Tpb\Core\Money;

/**
 * Stellt die Finanz-Auswertung eines Jahres zusammen (§DECISIONS #17):
 * Einnahmen/Ausgaben/Gewinn, Monatsreihen, Kategorien, Kapitaleinlagen und
 * Gewinnanteil je Gründer. Gewinn = Einnahmen − Ausgaben; Einlagen sind
 * Eigenkapital, nicht Einnahme.
 */
final class FinanceReport
{
    /**
     * @return array<string,mixed>
     */
    public static function forYear(int $year): array
    {
        $sums = EntryRepo::sumByDirectionYear($year);
        $income = $sums['income'];
        $expense = $sums['expense'];
        $profit = $income - $expense;

        $months = array_map(
            static fn (array $m): array => [
                'month'   => $m['month'],
                'income'  => $m['income'],
                'expense' => $m['expense'],
                'profit'  => $m['income'] - $m['expense'],
            ],
            EntryRepo::monthlyByYear($year)
        );

        $partners = self::partnerDistribution($profit);

        return [
            'year'                     => $year,
            'income_cents'             => $income,
            'expense_cents'            => $expense,
            'profit_cents'             => $profit,
            'months'                   => $months,
            'expense_by_category'      => self::normalizeCategories(EntryRepo::byCategoryYear($year, 'expense')),
            'income_by_category'       => self::normalizeCategories(EntryRepo::byCategoryYear($year, 'income')),
            'partners'                 => $partners,
            'contributions_total_cents' => ContributionRepo::grandTotal(),
            'share_bps_total'          => PartnerRepo::totalShareBps(),
        ];
    }

    /**
     * Gewinnanteil je Gründer = Gewinn × Anteil(bps). Kapitaleinlagen all-time.
     * @return array<int,array<string,mixed>>
     */
    private static function partnerDistribution(int $profit): array
    {
        $contributions = ContributionRepo::totalsByPartner();
        $out = [];
        foreach (PartnerRepo::allActive() as $p) {
            $id = (int) $p['id'];
            $shareBps = (int) $p['profit_share_bps'];
            $out[] = [
                'public_id'          => $p['public_id'],
                'name'               => $p['name'],
                'share_bps'          => $shareBps,
                'contributed_cents'  => $contributions[$id] ?? 0,
                'profit_share_cents' => Money::bp($profit, $shareBps),
            ];
        }
        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array{category:string,total:int}>
     */
    private static function normalizeCategories(array $rows): array
    {
        return array_map(
            static fn (array $r): array => [
                'category' => (string) $r['category'],
                'total'    => (int) $r['total'],
            ],
            $rows
        );
    }
}
