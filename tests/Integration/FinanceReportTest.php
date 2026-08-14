<?php
declare(strict_types=1);

namespace Tpb\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tpb\Core\Db;
use Tpb\Domain\Finance\ContributionRepo;
use Tpb\Domain\Finance\EntryRepo;
use Tpb\Domain\Finance\FinanceReport;
use Tpb\Domain\Finance\PartnerRepo;

final class FinanceReportTest extends TestCase
{
    private const YEAR = 2099;

    protected function setUp(): void
    {
        Db::run('DELETE FROM finance_entries');
        Db::run('DELETE FROM capital_contributions');
        Db::run('DELETE FROM finance_partners');
    }

    public function testAggregatesAndProfitDistribution(): void
    {
        $aId = $this->partnerId(PartnerRepo::create('Alpha', 6000, null)); // 60 %
        $bId = $this->partnerId(PartnerRepo::create('Beta', 4000, null));  // 40 %

        EntryRepo::create(self::YEAR . '-01-10', 'income', 'Verkauf', 100000, null, null, null);
        EntryRepo::create(self::YEAR . '-02-05', 'income', 'Verkauf', 50000, null, null, null);
        EntryRepo::create(self::YEAR . '-01-15', 'expense', 'Material', 30000, null, null, null);
        EntryRepo::create(self::YEAR . '-03-01', 'expense', 'Miete', 20000, null, null, null);

        ContributionRepo::create($aId, self::YEAR . '-01-01', 'cash', 1000000, null, null);
        ContributionRepo::create($bId, self::YEAR . '-01-01', 'cash', 500000, null, null);

        $r = FinanceReport::forYear(self::YEAR);

        self::assertSame(150000, $r['income_cents']);
        self::assertSame(50000, $r['expense_cents']);
        self::assertSame(100000, $r['profit_cents']);

        // Januar (Index 0): Einnahme 1000,00 / Ausgabe 300,00 / Gewinn 700,00
        self::assertSame(100000, $r['months'][0]['income']);
        self::assertSame(30000, $r['months'][0]['expense']);
        self::assertSame(70000, $r['months'][0]['profit']);

        // Ausgaben nach Kategorie, absteigend
        self::assertSame('Material', $r['expense_by_category'][0]['category']);
        self::assertSame(30000, $r['expense_by_category'][0]['total']);

        // Gewinnverteilung: 60/40 von 1000,00 = 600,00 / 400,00
        $byName = [];
        foreach ($r['partners'] as $p) {
            $byName[$p['name']] = $p;
        }
        self::assertSame(60000, $byName['Alpha']['profit_share_cents']);
        self::assertSame(40000, $byName['Beta']['profit_share_cents']);
        self::assertSame(1000000, $byName['Alpha']['contributed_cents']);
        self::assertSame(500000, $byName['Beta']['contributed_cents']);

        self::assertSame(1500000, $r['contributions_total_cents']);
        self::assertSame(10000, $r['share_bps_total']);
    }

    public function testNegativeProfitDistributesProportionally(): void
    {
        $aId = $this->partnerId(PartnerRepo::create('Alpha', 5000, null));
        PartnerRepo::create('Beta', 5000, null);

        EntryRepo::create(self::YEAR . '-04-01', 'income', 'Verkauf', 10000, null, null, null);
        EntryRepo::create(self::YEAR . '-04-02', 'expense', 'Material', 30000, null, null, null);

        $r = FinanceReport::forYear(self::YEAR);
        self::assertSame(-20000, $r['profit_cents']);

        $byName = [];
        foreach ($r['partners'] as $p) {
            $byName[$p['name']] = $p;
        }
        // 50 % von -200,00 = -100,00
        self::assertSame(-10000, $byName['Alpha']['profit_share_cents']);
        self::assertSame(-10000, $byName['Beta']['profit_share_cents']);
        self::assertSame(0, $byName['Alpha']['contributed_cents']);
        unset($aId);
    }

    private function partnerId(string $publicId): int
    {
        $p = PartnerRepo::findByPublicId($publicId);
        self::assertNotNull($p);
        return (int) $p['id'];
    }
}
