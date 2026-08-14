<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\Clock;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Finance\Amount;
use Tpb\Domain\Finance\ContributionRepo;
use Tpb\Domain\Finance\EntryRepo;
use Tpb\Domain\Finance\FinanceReport;
use Tpb\Domain\Finance\PartnerRepo;

/**
 * Finanzmodul (Abweichung, docs/DECISIONS.md #14-18): Dashboard mit Statistiken,
 * Einnahmen/Ausgaben-Buchungen, Gesellschafter + Kapitaleinlagen.
 * Lesen: tpb_view_costs · Schreiben: tpb_manage_finance.
 */
final class FinanceController
{
    // -- Dashboard ---------------------------------------------------------

    /** @param array<string,string> $params */
    public function dashboard(array $params): void
    {
        $year = $this->year();
        $report = FinanceReport::forYear($year);

        Response::html(View::render('admin/finance/dashboard', [
            'title'   => 'Finanzen',
            'nav'     => 'finance',
            'year'    => $year,
            'years'   => $this->yearOptions($year),
            'report'  => $report,
            'chartJson' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
        ]));
    }

    // -- Buchungen (Einnahmen/Ausgaben) -----------------------------------

    /** @param array<string,string> $params */
    public function entries(array $params): void
    {
        $year = $this->year();
        Response::html(View::render('admin/finance/entries', [
            'title'    => 'Buchungen',
            'nav'      => 'finance',
            'year'     => $year,
            'years'    => $this->yearOptions($year),
            'entries'  => EntryRepo::listByYear($year),
            'partners' => PartnerRepo::allActive(),
            'flash'    => $this->takeFlash(),
            'today'    => Clock::toLux(Clock::nowUtc())->format('Y-m-d'),
        ]));
    }

    /** @param array<string,string> $params */
    public function storeEntry(array $params): void
    {
        try {
            $direction = (string) Request::post('direction', '');
            if (!in_array($direction, EntryRepo::DIRECTIONS, true)) {
                throw new \InvalidArgumentException('Ungültige Buchungsart.');
            }
            $date = $this->validDate((string) Request::post('entry_date', ''));
            $category = trim((string) Request::post('category', ''));
            if ($category === '') {
                throw new \InvalidArgumentException('Bitte eine Kategorie angeben.');
            }
            $amount = Amount::toCents((string) Request::post('amount', ''));
            $description = $this->nullTrim((string) Request::post('description', ''));
            $partnerId = $this->partnerIdFromPublic((string) Request::post('partner', ''));

            $publicId = EntryRepo::create($date, $direction, mb_substr($category, 0, 48), $amount, $description, $partnerId, Auth::id());
            Audit::log('finance_entry', $publicId, 'finance.entry.created', [
                'actor_user_id' => Auth::id(),
                'metadata'      => ['direction' => $direction, 'amount_cents' => $amount, 'category' => $category],
            ]);
            $this->flash('ok', 'Buchung gespeichert.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/finanzen/buchungen?year=' . $this->year());
    }

    /** @param array<string,string> $params */
    public function deleteEntry(array $params): void
    {
        $publicId = (string) Request::post('public_id', '');
        if ($publicId !== '') {
            EntryRepo::delete($publicId);
            Audit::log('finance_entry', $publicId, 'finance.entry.deleted', ['actor_user_id' => Auth::id()]);
            $this->flash('ok', 'Buchung gelöscht.');
        }
        Response::redirect('/admin/finanzen/buchungen?year=' . $this->year());
    }

    // -- Gesellschafter + Einlagen ----------------------------------------

    /** @param array<string,string> $params */
    public function partners(array $params): void
    {
        Response::html(View::render('admin/finance/partners', [
            'title'         => 'Gesellschafter',
            'nav'           => 'finance',
            'partners'      => PartnerRepo::all(),
            'contributions' => ContributionRepo::listRecent(),
            'totalsByName'  => $this->contributionTotals(),
            'shareTotalBps' => PartnerRepo::totalShareBps(),
            'flash'         => $this->takeFlash(),
            'today'         => Clock::toLux(Clock::nowUtc())->format('Y-m-d'),
        ]));
    }

    /** @param array<string,string> $params */
    public function storePartner(array $params): void
    {
        try {
            $name = trim((string) Request::post('name', ''));
            if ($name === '') {
                throw new \InvalidArgumentException('Bitte einen Namen angeben.');
            }
            $shareBps = Amount::percentToBps((string) Request::post('share', '0'));
            $publicId = (string) Request::post('public_id', '');

            if ($publicId !== '') {
                $status = Request::post('status') === 'inactive' ? 'inactive' : 'active';
                PartnerRepo::update($publicId, mb_substr($name, 0, 120), $shareBps, $status);
                Audit::log('finance_partner', $publicId, 'finance.partner.updated', ['actor_user_id' => Auth::id()]);
            } else {
                $newId = PartnerRepo::create(mb_substr($name, 0, 120), $shareBps, Auth::id());
                Audit::log('finance_partner', $newId, 'finance.partner.created', ['actor_user_id' => Auth::id()]);
            }
            $this->flash('ok', 'Gesellschafter gespeichert.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/finanzen/gesellschafter');
    }

    /** @param array<string,string> $params */
    public function storeContribution(array $params): void
    {
        try {
            $partnerId = $this->partnerIdFromPublic((string) Request::post('partner', ''));
            if ($partnerId === null) {
                throw new \InvalidArgumentException('Bitte einen Gesellschafter wählen.');
            }
            $date = $this->validDate((string) Request::post('contributed_on', ''));
            $kind = Request::post('kind') === 'asset' ? 'asset' : 'cash';
            $amount = Amount::toCents((string) Request::post('amount', ''));
            $note = $this->nullTrim((string) Request::post('note', ''));

            $publicId = ContributionRepo::create($partnerId, $date, $kind, $amount, $note, Auth::id());
            Audit::log('capital_contribution', $publicId, 'finance.contribution.created', [
                'actor_user_id' => Auth::id(),
                'metadata'      => ['amount_cents' => $amount, 'kind' => $kind],
            ]);
            $this->flash('ok', 'Einlage gespeichert.');
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        Response::redirect('/admin/finanzen/gesellschafter');
    }

    /** @param array<string,string> $params */
    public function deleteContribution(array $params): void
    {
        $publicId = (string) Request::post('public_id', '');
        if ($publicId !== '') {
            ContributionRepo::delete($publicId);
            Audit::log('capital_contribution', $publicId, 'finance.contribution.deleted', ['actor_user_id' => Auth::id()]);
            $this->flash('ok', 'Einlage gelöscht.');
        }
        Response::redirect('/admin/finanzen/gesellschafter');
    }

    // -- Helfer ------------------------------------------------------------

    private function year(): int
    {
        $q = (string) Request::query('year', '');
        if (preg_match('/^\d{4}$/', $q)) {
            return (int) $q;
        }
        return (int) Clock::toLux(Clock::nowUtc())->format('Y');
    }

    /** @return int[] */
    private function yearOptions(int $current): array
    {
        $years = EntryRepo::availableYears();
        if (!in_array($current, $years, true)) {
            $years[] = $current;
        }
        rsort($years);
        return $years;
    }

    private function validDate(string $raw): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($d === false) {
            throw new \InvalidArgumentException('Bitte ein gültiges Datum (JJJJ-MM-TT) angeben.');
        }
        return $d->format('Y-m-d');
    }

    private function nullTrim(string $raw): ?string
    {
        $t = trim($raw);
        return $t === '' ? null : mb_substr($t, 0, 255);
    }

    private function partnerIdFromPublic(string $publicId): ?int
    {
        if ($publicId === '') {
            return null;
        }
        $p = PartnerRepo::findByPublicId($publicId);
        return $p ? (int) $p['id'] : null;
    }

    /** @return array<string,int> name => Summe Cents */
    private function contributionTotals(): array
    {
        $byId = ContributionRepo::totalsByPartner();
        $out = [];
        foreach (PartnerRepo::all() as $p) {
            $out[(string) $p['name']] = $byId[(int) $p['id']] ?? 0;
        }
        return $out;
    }

    private function flash(string $type, string $text): void
    {
        $_SESSION['flash'] = ['type' => $type, 'text' => $text];
    }

    /** @return array{type:string,text:string}|null */
    private function takeFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($f) ? $f : null;
    }
}
